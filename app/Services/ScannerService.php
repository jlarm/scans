<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ScannerService
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
            $results['checks'] = array_merge(
                $this->checkHttpSecurity($target),
                $this->checkSslCertificate($target),
                $this->checkHeaders($target),
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

    private function checkHttpSecurity(string $url): array
    {
        $checks = [];

        try {
            $response = $this->client->request('GET', $url);
            $headers = $response->getHeaders();

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
        } catch (RequestException $e) {
            $checks[] = [
                'type' => 'http_connection',
                'error' => $e->getMessage(),
                'passed' => false,
            ];
        }
        return $checks;
    }

    private function checkSslCertificate(string $url): array
    {
        $checks = [];

        if (!str_starts_with($url, 'https://')) {
            $checks[] = [
                'type' => 'ssl',
                'name' => 'HTTPS',
                'passed' => false,
                'message' => 'Site is not using HTTPS'
            ];
        }

        $context = stream_context_create([
           'ssl' => [
               'capture_peer_cert' => true,
               'verify_peer' => false,
               'verify_peer_name' => false,
           ] ,
        ]);

        $stream = @stream_socket_client(
            "ssl://" . parse_url($url, PHP_URL_HOST) . ":443",
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

    private function checkHeaders(string $url): array
    {
        // Implementation for checking additional headers
        return [];
    }

    private function checkCors(string $url): array
    {
        // Implementation for CORS checks
        return [];
    }

    private function checkOpenPorts(string $ip): array
    {
        // Implementation for port scanning
        return [];
    }

    private function checkServices(string $ip): array
    {
        // Implementation for service detection
        return [];
    }

}
