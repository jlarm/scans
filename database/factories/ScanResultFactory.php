<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Scan;
use App\Models\ScanResult;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScanResult>
 */
final class ScanResultFactory extends Factory
{
    protected $model = ScanResult::class;

    public function definition(): array
    {
        $checkTypes = [
            'security_header',
            'ssl_certificate',
            'port_scan',
            'cors_policy',
            'service_detection',
            'additional_header',
            'http_connection',
        ];

        $severityLevels = ['low', 'medium', 'high', 'critical'];
        $riskLevels = ['low', 'medium', 'high'];

        $checkType = fake()->randomElement($checkTypes);
        $passed = fake()->boolean(70); // 70% chance of passing

        return [
            'uuid' => Str::uuid(),
            'scan_id' => Scan::factory(),
            'target' => fake()->randomElement([
                fake()->url(),
                fake()->ipv4(),
            ]),
            'target_type' => fake()->randomElement(['url', 'ip']),
            'check_type' => $checkType,
            'check_name' => $this->generateCheckName($checkType),
            'passed' => $passed,
            'severity' => $passed ? null : fake()->randomElement($severityLevels),
            'risk_level' => fake()->optional(0.3)->randomElement($riskLevels),
            'message' => $passed ?
                'Check passed successfully' :
                fake()->sentence(6, true),
            'description' => fake()->optional(0.5)->sentence(10),
            'check_data' => $this->generateCheckData($checkType, $passed),
            'recommendations' => $passed ?
                null :
                fake()->sentences(fake()->numberBetween(1, 3)),
            'vulnerabilities' => fake()->optional(0.1)->randomElements([
                [
                    'cve' => 'CVE-2021-44228',
                    'severity' => 'critical',
                    'description' => 'Remote code execution vulnerability',
                ],
                [
                    'cve' => 'CVE-2021-41773',
                    'severity' => 'high',
                    'description' => 'Path traversal vulnerability',
                ],
            ], fake()->numberBetween(0, 2)),
            'scanned_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function passed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => true,
            'severity' => null,
            'message' => 'Check passed successfully',
            'recommendations' => null,
            'vulnerabilities' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => false,
            'severity' => fake()->randomElement(['medium', 'high', 'critical']),
            'message' => fake()->sentence(6, true),
            'recommendations' => fake()->sentences(fake()->numberBetween(1, 3)),
        ]);
    }

    public function highRisk(): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => false,
            'severity' => fake()->randomElement(['high', 'critical']),
            'risk_level' => 'high',
            'vulnerabilities' => [
                [
                    'cve' => 'CVE-2021-44228',
                    'severity' => 'critical',
                    'description' => 'Remote code execution vulnerability',
                ],
            ],
        ]);
    }

    public function withVulnerabilities(?array $vulnerabilities = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => false,
            'vulnerabilities' => $vulnerabilities ?? [
                [
                    'cve' => 'CVE-2021-44228',
                    'severity' => 'critical',
                    'description' => 'Log4j remote code execution',
                ],
            ],
        ]);
    }

    public function securityHeader(): static
    {
        return $this->state(fn (array $attributes): array => [
            'check_type' => 'security_header',
            'check_name' => fake()->randomElement([
                'X-Frame-Options',
                'X-Content-Type-Options',
                'Strict-Transport-Security',
            ]),
        ]);
    }

    public function portScan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'check_type' => 'port_scan',
            'check_name' => fake()->randomElement(['HTTP', 'HTTPS', 'SSH', 'FTP']),
            'target_type' => 'ip',
            'target' => fake()->ipv4(),
        ]);
    }

    private function generateCheckName(string $checkType): ?string
    {
        return match ($checkType) {
            'security_header' => fake()->randomElement([
                'X-Frame-Options',
                'X-Content-Type-Options',
                'Strict-Transport-Security',
                'Content-Security-Policy',
            ]),
            'ssl_certificate' => 'Certificate Validity',
            'port_scan' => fake()->randomElement(['HTTP', 'HTTPS', 'SSH', 'FTP', 'SMTP']),
            'cors_policy' => fake()->randomElement([
                'Access-Control-Allow-Origin',
                'Access-Control-Allow-Methods',
            ]),
            'service_detection' => fake()->randomElement(['SSH', 'HTTP', 'FTP']),
            'additional_header' => fake()->randomElement([
                'X-Permitted-Cross-Domain-Policies',
                'Permissions-Policy',
            ]),
            default => null,
        };
    }

    private function generateCheckData(string $checkType, bool $passed): array
    {
        $baseData = [
            'type' => $checkType,
            'passed' => $passed,
            'timestamp' => now()->toIso8601String(),
        ];

        return match ($checkType) {
            'security_header' => array_merge($baseData, [
                'name' => 'X-Frame-Options',
                'value' => $passed ? 'SAMEORIGIN' : null,
                'expected' => 'SAMEORIGIN',
            ]),
            'port_scan' => array_merge($baseData, [
                'port' => fake()->numberBetween(1, 65535),
                'service' => 'HTTP',
                'status' => $passed ? 'closed' : 'open',
                'response_time_ms' => fake()->randomFloat(2, 1, 100),
            ]),
            'ssl_certificate' => array_merge($baseData, [
                'name' => 'Certificate Validity',
                'valid_from' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'valid_to' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            ]),
            default => $baseData,
        };
    }
}
