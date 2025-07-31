<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

final readonly class ScannerService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    public function scan(string $target, string $type): array
    {
        $results = [
            'target' => $target,
            'type' => $type,
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        if ($type === 'url') {
            // Get the HTTP response first so we can reuse it across checks
            $httpResponse = $this->getHttpResponse($target);

            $results['checks'] = array_merge(
                $this->checkHttpSecurity($httpResponse),
                $this->checkSslCertificate($target),
                $this->checkHeaders($httpResponse),
                $this->checkCors($target)
            );
        } else {
            $results['checks'] = array_merge(
                $this->checkOpenPorts($target),
                $this->checkServices($target)
            );
        }

        return $results;
    }

    private function getHttpResponse(string $url): object
    {
        try {
            $response = $this->client->request('GET', $url, [
                'http_errors' => false,
                'timeout' => 30,
            ]);

            return (object) [
                'response' => $response,
                'headers' => $response->getHeaders(),
                'status_code' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
            ];
        } catch (RequestException $e) {
            return (object) [
                'error' => $e->getMessage(),
                'response' => null,
                'headers' => [],
                'status_code' => null,
                'body' => null,
            ];
        }
    }

    private function checkHttpSecurity(?object $httpResponse = null): array
    {
        $checks = [];

        if ($httpResponse && ! isset($httpResponse->error)) {
            $headers = $httpResponse->headers;

            $securityHeaders = [
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
                'X-XSS-Protection' => '1; mode=block',
                'Strict-Transport-Security' => null,
                'Content-Security-Policy' => null,
                'Referrer-Policy' => null,
            ];

            foreach ($securityHeaders as $header => $expectedValue) {
                $value = $headers[$header][0] ?? null;
                $checks[] = [
                    'type' => 'security_header',
                    'name' => $header,
                    'value' => $value,
                    'expected' => $expectedValue,
                    'passed' => $value !== null && ($expectedValue === null || $value === $expectedValue),
                ];
            }
        } else {
            $checks[] = [
                'type' => 'http_connection',
                'error' => $httpResponse->error ?? 'Failed to connect to target',
                'passed' => false,
            ];
        }

        return $checks;
    }

    private function checkSslCertificate(string $url): array
    {
        $checks = [];

        if (! str_starts_with($url, 'https://')) {
            $checks[] = [
                'type' => 'ssl',
                'name' => 'HTTPS',
                'passed' => false,
                'message' => 'Site is not using HTTPS',
            ];
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $stream = @stream_socket_client(
            'ssl://'.parse_url($url, PHP_URL_HOST).':443',
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($stream) {
            $params = stream_context_get_params($stream);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

            $checks[] = [
                'type' => 'ssl_certificate',
                'name' => 'Certificate Validity',
                'valid_from' => date('Y-m-d', $cert['validFrom_time_t']),
                'valid_to' => date('Y-m-d', $cert['validTo_time_t']),
                'passed' => $cert['validTo_time_t'] > time(),
            ];
        }

        return $checks;
    }

    private function checkHeaders(?object $httpResponse = null): array
    {
        $checks = [];

        // Additional security headers that weren't checked in checkHttpSecurity
        $additionalHeaders = [
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'X-Download-Options' => 'noopen',
            'Permissions-Policy' => null, // Should exist but value varies
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if ($httpResponse && ! isset($httpResponse->error)) {
            $headers = $httpResponse->headers;

            foreach ($additionalHeaders as $header => $expectedValue) {
                $value = $headers[$header][0] ?? null;
                $passed = $value !== null && ($expectedValue === null || $value === $expectedValue);

                $checks[] = [
                    'type' => 'additional_header',
                    'name' => $header,
                    'value' => $value,
                    'expected' => $expectedValue,
                    'passed' => $passed,
                    'severity' => $this->getHeaderSeverity($header),
                    'recommendations' => $this->getHeaderRecommendations($header, $value),
                ];
            }
        } else {
            $checks[] = [
                'type' => 'additional_headers',
                'error' => $httpResponse->error ?? 'No HTTP response available',
                'passed' => false,
            ];
        }

        return $checks;
    }

    private function getHeaderSeverity(string $header): string
    {
        return match ($header) {
            'X-Permitted-Cross-Domain-Policies' => 'high',
            'Cross-Origin-Embedder-Policy', 'Cross-Origin-Opener-Policy', 'Cross-Origin-Resource-Policy' => 'high',
            'Permissions-Policy' => 'medium',
            'X-Download-Options' => 'low',
            default => 'medium',
        };
    }

    private function getHeaderRecommendations(string $header, ?string $value): array
    {
        if ($value === null) {
            return match ($header) {
                'X-Permitted-Cross-Domain-Policies' => ['Add header to prevent Flash/PDF cross-domain access'],
                'Cross-Origin-Embedder-Policy' => ['Add COEP header for additional security isolation'],
                'Cross-Origin-Opener-Policy' => ['Add COOP header to protect against cross-origin attacks'],
                'Cross-Origin-Resource-Policy' => ['Add CORP header to control resource sharing'],
                'Permissions-Policy' => ['Add Permissions-Policy to control browser features'],
                'X-Download-Options' => ['Add header to prevent IE from executing downloads'],
                default => ['Consider adding this security header'],
            };
        }

        return ['Header is properly configured'];
    }

    private function checkCors(string $url): array
    {
        $checks = [];

        try {
            // CORS preflight request
            $corsHeaders = [
                'Origin' => 'https://example.com',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type',
            ];

            $response = $this->client->request('OPTIONS', $url, [
                'headers' => $corsHeaders,
                'http_errors' => false,
            ]);

            $responseHeaders = $response->getHeaders();

            // Check CORS policy headers
            $corsChecks = [
                'Access-Control-Allow-Origin' => [
                    'severity' => 'high',
                    'description' => 'Controls which origins can access the resource',
                ],
                'Access-Control-Allow-Methods' => [
                    'severity' => 'medium',
                    'description' => 'Specifies allowed HTTP methods',
                ],
                'Access-Control-Allow-Headers' => [
                    'severity' => 'medium',
                    'description' => 'Specifies allowed request headers',
                ],
                'Access-Control-Allow-Credentials' => [
                    'severity' => 'high',
                    'description' => 'Controls credential inclusion in requests',
                ],
                'Access-Control-Max-Age' => [
                    'severity' => 'low',
                    'description' => 'Cache duration for preflight requests',
                ],
            ];

            foreach ($corsChecks as $header => $config) {
                $value = $responseHeaders[$header][0] ?? null;
                $isWildcard = $value === '*';
                $allowsCredentials = isset($responseHeaders['Access-Control-Allow-Credentials'][0])
                    && $responseHeaders['Access-Control-Allow-Credentials'][0] === 'true';

                $checks[] = [
                    'type' => 'cors_policy',
                    'name' => $header,
                    'value' => $value,
                    'description' => $config['description'],
                    'severity' => $config['severity'],
                    'passed' => $value !== null,
                    'security_risk' => $header === 'Access-Control-Allow-Origin' && $isWildcard && $allowsCredentials,
                    'recommendations' => $this->getCorsRecommendations($header, $value),
                ];
            }

        } catch (RequestException $e) {
            $checks[] = [
                'type' => 'cors_check',
                'error' => 'Failed to perform CORS check: '.$e->getMessage(),
                'passed' => false,
            ];
        }

        return $checks;
    }

    private function getCorsRecommendations(string $header, ?string $value): array
    {
        return match ($header) {
            'Access-Control-Allow-Origin' => $value === '*'
                ? ['Avoid using wildcard (*) for production APIs', 'Specify explicit origins when possible']
                : ['Configuration appears secure'],
            'Access-Control-Allow-Methods' => is_null($value)
                ? ['Consider specifying allowed HTTP methods explicitly']
                : ['Review if all listed methods are necessary'],
            'Access-Control-Allow-Credentials' => $value === 'true'
                ? ['Ensure Access-Control-Allow-Origin is not set to wildcard (*)']
                : ['Credentials are disabled, which is secure for public APIs'],
            default => ['Review CORS configuration for security implications'],
        };
    }

    private function checkOpenPorts(string $target): array
    {
        $checks = [];

        // Common ports to scan
        $commonPorts = [
            21 => 'FTP',
            22 => 'SSH',
            23 => 'Telnet',
            25 => 'SMTP',
            53 => 'DNS',
            80 => 'HTTP',
            110 => 'POP3',
            143 => 'IMAP',
            443 => 'HTTPS',
            993 => 'IMAPS',
            995 => 'POP3S',
            1433 => 'MSSQL',
            3306 => 'MySQL',
            3389 => 'RDP',
            5432 => 'PostgreSQL',
            6379 => 'Redis',
            27017 => 'MongoDB',
        ];

        $host = parse_url($target, PHP_URL_HOST) ?? $target;

        foreach ($commonPorts as $port => $service) {
            $startTime = microtime(true);
            $connection = @fsockopen($host, $port, $errno, $errstr, 3);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($connection) {
                fclose($connection);
                $isOpen = true;
                $risk = $this->assessPortRisk($port);
            } else {
                $isOpen = false;
                $risk = 'none';
            }

            $checks[] = [
                'type' => 'port_scan',
                'port' => $port,
                'service' => $service,
                'status' => $isOpen ? 'open' : 'closed',
                'response_time_ms' => $isOpen ? $responseTime : null,
                'risk_level' => $risk,
                'passed' => ! $isOpen || $risk === 'low',
                'recommendations' => $this->getPortRecommendations($port, $isOpen),
            ];
        }

        return $checks;
    }

    private function assessPortRisk(int $port): string
    {
        $highRiskPorts = [21, 23, 1433, 3306, 3389, 5432, 6379, 27017];
        $mediumRiskPorts = [22, 25, 110, 143];
        $lowRiskPorts = [80, 443, 53, 993, 995];

        if (in_array($port, $highRiskPorts)) {
            return 'high';
        }
        if (in_array($port, $mediumRiskPorts)) {
            return 'medium';
        }
        if (in_array($port, $lowRiskPorts)) {
            return 'low';
        }

        return 'medium'; // Default for unknown ports
    }

    private function getPortRecommendations(int $port, bool $isOpen): array
    {
        if (! $isOpen) {
            return ['Port is properly closed'];
        }

        return match ($port) {
            21 => ['FTP is insecure', 'Use SFTP (port 22) instead', 'Consider disabling if not needed'],
            22 => ['Ensure SSH uses key-based authentication', 'Disable password authentication', 'Use non-standard port if possible'],
            23 => ['Telnet is extremely insecure', 'Disable immediately', 'Use SSH instead'],
            25 => ['Ensure SMTP requires authentication', 'Use TLS encryption', 'Monitor for spam relay'],
            80 => ['Consider redirecting to HTTPS', 'Disable if only HTTPS is needed'],
            443 => ['Ensure valid SSL certificate', 'Use strong cipher suites', 'Enable HSTS'],
            1433 => ['SQL Server should not be internet-facing', 'Use firewall restrictions', 'Enable encryption'],
            3306 => ['MySQL should not be internet-facing', 'Use firewall restrictions', 'Disable if not needed'],
            3389 => ['RDP is high-risk when exposed', 'Use VPN access instead', 'Enable NLA and strong passwords'],
            5432 => ['PostgreSQL should not be internet-facing', 'Use firewall restrictions', 'Require SSL connections'],
            6379 => ['Redis should not be internet-facing', 'Enable authentication', 'Use firewall restrictions'],
            27017 => ['MongoDB should not be internet-facing', 'Enable authentication', 'Use firewall restrictions'],
            default => ['Review if this service needs to be publicly accessible', 'Apply security hardening'],
        };
    }

    private function checkServices(string $target): array
    {
        $checks = [];
        $host = parse_url($target, PHP_URL_HOST) ?? $target;

        // Service detection through banner grabbing and response analysis
        $servicesToCheck = [
            22 => ['service' => 'SSH', 'method' => 'banner'],
            21 => ['service' => 'FTP', 'method' => 'banner'],
            25 => ['service' => 'SMTP', 'method' => 'banner'],
            80 => ['service' => 'HTTP', 'method' => 'http'],
            443 => ['service' => 'HTTPS', 'method' => 'http'],
            110 => ['service' => 'POP3', 'method' => 'banner'],
            143 => ['service' => 'IMAP', 'method' => 'banner'],
        ];

        foreach ($servicesToCheck as $port => $config) {
            $serviceInfo = $this->detectService($host, $port, $config);
            if ($serviceInfo !== null && $serviceInfo !== []) {
                $checks[] = $serviceInfo;
            }
        }

        return $checks;
    }

    private function detectService(string $host, int $port, array $config): ?array
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        if (! $connection) {
            return null;
        }

        $serviceInfo = [
            'type' => 'service_detection',
            'port' => $port,
            'service' => $config['service'],
            'status' => 'detected',
            'banner' => null,
            'version' => null,
            'vulnerabilities' => [],
            'recommendations' => [],
        ];

        try {
            if ($config['method'] === 'banner') {
                // Read banner/greeting from service
                stream_set_timeout($connection, 3);
                $banner = fread($connection, 1024);

                if ($banner) {
                    $serviceInfo['banner'] = trim($banner);
                    $serviceInfo['version'] = $this->extractVersion($banner, $config['service']);
                    $serviceInfo['vulnerabilities'] = $this->checkKnownVulnerabilities($serviceInfo['version'], $config['service']);
                    $serviceInfo['recommendations'] = $this->getServiceRecommendations($config['service']);
                }
            } elseif ($config['method'] === 'http' && in_array($port, [80, 443])) {
                // HTTP service detection
                $protocol = $port === 443 ? 'https' : 'http';
                $serviceInfo = array_merge($serviceInfo, $this->detectHttpService($host, $port, $protocol));
            }
        } catch (Exception $e) {
            $serviceInfo['error'] = $e->getMessage();
        } finally {
            fclose($connection);
        }

        return $serviceInfo;
    }

    private function extractVersion(string $banner, string $service): ?string
    {
        $patterns = [
            'SSH' => '/SSH-([0-9.]+)/i',
            'FTP' => '/FTP.*?([0-9.]+)/i',
            'SMTP' => '/SMTP.*?([0-9.]+)/i',
            'POP3' => '/POP3.*?([0-9.]+)/i',
            'IMAP' => '/IMAP.*?([0-9.]+)/i',
        ];

        if (! isset($patterns[$service])) {
            return null;
        }
        if (preg_match($patterns[$service], $banner, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function detectHttpService(string $host, int $port, string $protocol): array
    {
        try {
            $response = $this->client->request('GET', "$protocol://$host:$port", [
                'timeout' => 5,
                'http_errors' => false,
            ]);

            $headers = $response->getHeaders();
            $server = $headers['Server'][0] ?? 'Unknown';
            $powered = $headers['X-Powered-By'][0] ?? null;

            return [
                'server' => $server,
                'powered_by' => $powered,
                'version' => $this->extractHttpVersion($server),
                'status_code' => $response->getStatusCode(),
                'vulnerabilities' => $this->checkHttpVulnerabilities($server),
                'recommendations' => $this->getHttpRecommendations($server),
            ];
        } catch (Exception $e) {
            return ['error' => 'HTTP detection failed: '.$e->getMessage()];
        }
    }

    private function extractHttpVersion(string $server): ?string
    {
        if (preg_match('/([a-zA-Z-]+)\/([0-9.]+)/', $server, $matches)) {
            return $matches[2];
        }

        return null;
    }

    private function checkKnownVulnerabilities(?string $version, string $service): array
    {
        if ($version === null || $version === '' || $version === '0') {
            return [];
        }

        $vulnerabilities = [];
        $vulnDatabase = $this->getVulnerabilityDatabase();

        if (isset($vulnDatabase[$service])) {
            foreach ($vulnDatabase[$service] as $vuln) {
                if ($this->isVersionVulnerable($version, $vuln['affected_versions'])) {
                    $vulnerabilities[] = [
                        'id' => $vuln['id'],
                        'cve' => $vuln['cve'],
                        'severity' => $vuln['severity'],
                        'score' => $vuln['cvss_score'],
                        'description' => $vuln['description'],
                        'published' => $vuln['published'],
                        'references' => $vuln['references'],
                        'patch_available' => $vuln['patch_available'],
                        'recommended_action' => $vuln['recommended_action'],
                    ];
                }
            }
        }

        return $vulnerabilities;
    }

    private function getVulnerabilityDatabase(): array
    {
        return [
            'SSH' => [
                [
                    'id' => 'SSH-1.x-PROTOCOL',
                    'cve' => 'CVE-1999-1010',
                    'severity' => 'high',
                    'cvss_score' => 7.5,
                    'description' => 'SSH protocol version 1.x contains fundamental security flaws',
                    'affected_versions' => ['<2.0'],
                    'published' => '1999-01-01',
                    'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-1999-1010'],
                    'patch_available' => true,
                    'recommended_action' => 'Upgrade to SSH protocol version 2.0 or higher',
                ],
                [
                    'id' => 'OPENSSH-USER-ENUM',
                    'cve' => 'CVE-2018-15473',
                    'severity' => 'medium',
                    'cvss_score' => 5.3,
                    'description' => 'OpenSSH user enumeration vulnerability',
                    'affected_versions' => ['>=2.3', '<=7.7'],
                    'published' => '2018-08-15',
                    'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2018-15473'],
                    'patch_available' => true,
                    'recommended_action' => 'Update to OpenSSH 7.8 or later',
                ],
                [
                    'id' => 'OPENSSH-GSSAPI',
                    'cve' => 'CVE-2021-41617',
                    'severity' => 'high',
                    'cvss_score' => 7.0,
                    'description' => 'OpenSSH privilege escalation via supplemental groups',
                    'affected_versions' => ['>=6.2', '<=8.7'],
                    'published' => '2021-09-26',
                    'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-41617'],
                    'patch_available' => true,
                    'recommended_action' => 'Update to OpenSSH 8.8 or later',
                ],
            ],
            'FTP' => [
                [
                    'id' => 'FTP-PLAINTEXT',
                    'cve' => 'N/A',
                    'severity' => 'high',
                    'cvss_score' => 7.5,
                    'description' => 'FTP transmits credentials and data in plaintext',
                    'affected_versions' => ['*'],
                    'published' => '1971-01-01',
                    'references' => ['https://tools.ietf.org/rfc/rfc959.txt'],
                    'patch_available' => false,
                    'recommended_action' => 'Use SFTP or FTPS instead of FTP',
                ],
                [
                    'id' => 'VSFTPD-BACKDOOR',
                    'cve' => 'CVE-2011-2523',
                    'severity' => 'critical',
                    'cvss_score' => 10.0,
                    'description' => 'vsftpd 2.3.4 backdoor command execution',
                    'affected_versions' => ['=2.3.4'],
                    'published' => '2011-07-07',
                    'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2011-2523'],
                    'patch_available' => true,
                    'recommended_action' => 'Immediately update vsftpd to version 2.3.5 or later',
                ],
            ],
            'SMTP' => [
                [
                    'id' => 'SMTP-STARTTLS',
                    'cve' => 'CVE-2011-0411',
                    'severity' => 'medium',
                    'cvss_score' => 4.3,
                    'description' => 'SMTP STARTTLS plaintext injection vulnerability',
                    'affected_versions' => ['*'],
                    'published' => '2011-03-16',
                    'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2011-0411'],
                    'patch_available' => true,
                    'recommended_action' => 'Enforce TLS and disable plaintext fallback',
                ],
            ],
            'HTTP' => [
                [
                    'id' => 'APACHE-LOG4J',
                    'cve' => 'CVE-2021-44228',
                    'severity' => 'critical',
                    'cvss_score' => 10.0,
                    'description' => 'Apache Log4j2 remote code execution vulnerability',
                    'affected_versions' => ['>=2.0-beta9', '<=2.14.1'],
                    'published' => '2021-12-09',
                    'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-44228'],
                    'patch_available' => true,
                    'recommended_action' => 'Update Log4j to version 2.15.0 or later',
                ],
                [
                    'id' => 'APACHE-HTTPOXY',
                    'cve' => 'CVE-2016-5387',
                    'severity' => 'high',
                    'cvss_score' => 8.1,
                    'description' => 'Apache HTTP Proxy header vulnerability (HTTPoxy)',
                    'affected_versions' => ['>=2.0', '<=2.4.23'],
                    'published' => '2016-07-18',
                    'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2016-5387'],
                    'patch_available' => true,
                    'recommended_action' => 'Update Apache to 2.4.24 or later',
                ],
            ],
            'TELNET' => [
                [
                    'id' => 'TELNET-PLAINTEXT',
                    'cve' => 'N/A',
                    'severity' => 'critical',
                    'cvss_score' => 9.8,
                    'description' => 'Telnet transmits all data including passwords in plaintext',
                    'affected_versions' => ['*'],
                    'published' => '1969-01-01',
                    'references' => ['https://tools.ietf.org/rfc/rfc854.txt'],
                    'patch_available' => false,
                    'recommended_action' => 'Disable Telnet and use SSH instead',
                ],
            ],
        ];
    }

    private function isVersionVulnerable(string $version, array $affectedVersions): bool
    {
        foreach ($affectedVersions as $constraint) {
            if ($constraint === '*') {
                return true;
            }

            // Parse version constraints like ">=2.0", "<=7.7", "=2.3.4"
            if (preg_match('/^([<>=]+)(.+)$/', (string) $constraint, $matches)) {
                $operator = $matches[1];
                $constraintVersion = $matches[2];

                switch ($operator) {
                    case '>=':
                        if (version_compare($version, $constraintVersion, '<')) {
                            continue 2;
                        }
                        break;
                    case '<=':
                        if (version_compare($version, $constraintVersion, '>')) {
                            continue 2;
                        }
                        break;
                    case '>':
                        if (version_compare($version, $constraintVersion, '<=')) {
                            continue 2;
                        }
                        break;
                    case '<':
                        if (version_compare($version, $constraintVersion, '>=')) {
                            continue 2;
                        }
                        break;
                    case '=':
                        if (version_compare($version, $constraintVersion, '!=')) {
                            continue 2;
                        }
                        break;
                }
            }
        }

        return true; // If all constraints pass, version is vulnerable
    }

    private function checkHttpVulnerabilities(string $server): array
    {
        $vulnerabilities = [];

        // Extract server type and version
        if (preg_match('/([a-zA-Z-]+)\/([0-9.]+[a-zA-Z0-9\-.]*)/', $server, $matches)) {
            $serverType = mb_strtolower($matches[1]);
            $version = $matches[2];

            // Map server types to our vulnerability database keys
            $serviceMap = [
                'apache' => 'HTTP',
                'nginx' => 'HTTP',
                'microsoft-iis' => 'HTTP',
                'lighttpd' => 'HTTP',
            ];

            if (isset($serviceMap[$serverType])) {
                $vulns = $this->checkKnownVulnerabilities($version, $serviceMap[$serverType]);
                $vulnerabilities = array_merge($vulnerabilities, $vulns);
            }

            // Check for specific server vulnerabilities
            $vulnerabilities = array_merge($vulnerabilities, $this->checkServerSpecificVulns($serverType, $version));
        }

        return $vulnerabilities;
    }

    private function checkServerSpecificVulns(string $serverType, string $version): array
    {
        $vulnerabilities = [];

        switch ($serverType) {
            case 'apache':
                // Check Apache-specific vulnerabilities
                if (version_compare($version, '2.4.49', '>=') && version_compare($version, '2.4.50', '<')) {
                    $vulnerabilities[] = [
                        'id' => 'APACHE-PATH-TRAVERSAL',
                        'cve' => 'CVE-2021-41773',
                        'severity' => 'critical',
                        'score' => 7.5,
                        'description' => 'Apache HTTP Server path traversal vulnerability',
                        'published' => '2021-10-05',
                        'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-41773'],
                        'patch_available' => true,
                        'recommended_action' => 'Update to Apache 2.4.50 or later',
                    ];
                }
                if (version_compare($version, '2.4.0', '<')) {
                    $vulnerabilities[] = [
                        'id' => 'APACHE-OLD-VERSION',
                        'cve' => 'Multiple CVEs',
                        'severity' => 'high',
                        'score' => 7.0,
                        'description' => 'Apache version contains multiple known vulnerabilities',
                        'published' => '2012-02-21',
                        'references' => ['https://httpd.apache.org/security/vulnerabilities_24.html'],
                        'patch_available' => true,
                        'recommended_action' => 'Update to Apache 2.4.x or later',
                    ];
                }
                break;

            case 'nginx':
                if (version_compare($version, '1.20.1', '<')) {
                    $vulnerabilities[] = [
                        'id' => 'NGINX-DNS-RESOLVER',
                        'cve' => 'CVE-2021-23017',
                        'severity' => 'high',
                        'score' => 7.7,
                        'description' => 'Nginx DNS resolver off-by-one heap write vulnerability',
                        'published' => '2021-05-25',
                        'references' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-23017'],
                        'patch_available' => true,
                        'recommended_action' => 'Update to Nginx 1.20.1 or later',
                    ];
                }
                break;

            case 'microsoft-iis':
                if (version_compare($version, '10', '<')) {
                    $vulnerabilities[] = [
                        'id' => 'IIS-OLD-VERSION',
                        'cve' => 'Multiple CVEs',
                        'severity' => 'high',
                        'score' => 7.5,
                        'description' => 'IIS version contains known security vulnerabilities',
                        'published' => '2015-07-29',
                        'references' => ['https://www.microsoft.com/security/'],
                        'patch_available' => true,
                        'recommended_action' => 'Update to IIS 10 or later',
                    ];
                }
                break;
        }

        return $vulnerabilities;
    }

    private function getServiceRecommendations(string $service): array
    {
        return match ($service) {
            'SSH' => [
                'Use key-based authentication only',
                'Disable root login',
                'Change default port if possible',
                'Keep SSH server updated',
            ],
            'FTP' => [
                'Consider using SFTP instead',
                'Enable encryption if FTP is required',
                'Restrict access by IP if possible',
                'Use strong authentication',
            ],
            'SMTP' => [
                'Require authentication',
                'Use TLS encryption',
                'Implement rate limiting',
                'Monitor for spam abuse',
            ],
            default => ['Keep service updated', 'Apply security hardening', 'Monitor access logs'],
        };
    }

    private function getHttpRecommendations(string $server): array
    {
        $recommendations = [
            'Hide server version information',
            'Remove X-Powered-By headers',
            'Use security headers',
            'Keep web server updated',
        ];

        if (mb_stripos($server, 'apache') !== false) {
            $recommendations[] = 'Configure Apache security modules';
            $recommendations[] = 'Disable unnecessary Apache modules';
        } elseif (mb_stripos($server, 'nginx') !== false) {
            $recommendations[] = 'Configure Nginx security settings';
            $recommendations[] = 'Use proper SSL/TLS configuration';
        }

        return $recommendations;
    }
}
