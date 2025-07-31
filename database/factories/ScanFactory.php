<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScanStatus;
use App\Models\Company;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Scan>
 */
final class ScanFactory extends Factory
{
    protected $model = Scan::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'company_id' => Company::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'urls' => null,
            'ip_addresses' => null,
            'send_notification' => false,
            'notification_email' => null,
            'schedule_type' => 'immediate',
            'scheduled_at' => now(),
            'cron_expression' => null,
            'frequency' => null,
            'day_of_week' => null,
            'schedule_time' => null,
            'status' => ScanStatus::PENDING->value,
            'risk_grade' => null,
            'summary' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function withUrls(array $urls = ['https://example.com']): static
    {
        return $this->state(fn (array $attributes): array => [
            'urls' => $urls,
        ]);
    }

    public function withIpAddresses(array $ips = ['192.168.1.1']): static
    {
        return $this->state(fn (array $attributes): array => [
            'ip_addresses' => $ips,
        ]);
    }

    public function once(?string $scheduledAt = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'schedule_type' => 'once',
            'scheduled_at' => $scheduledAt !== null && $scheduledAt !== '' && $scheduledAt !== '0' ? $scheduledAt : now()->addHour(),
        ]);
    }

    public function recurring(string $cronExpression = '0 9 * * *'): static
    {
        return $this->state(fn (array $attributes): array => [
            'schedule_type' => 'recurring',
            'cron_expression' => $cronExpression,
            'frequency' => 'daily',
            'schedule_time' => '09:00',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScanStatus::COMPLETED->value,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
            'risk_grade' => 'A',
            'summary' => [
                'total_targets' => 1,
                'total_checks' => 5,
                'passed_checks' => 5,
                'failed_checks' => 0,
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScanStatus::FAILED->value,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
            'summary' => [
                'error' => 'Scanner service error',
                'failed_at' => now()->subMinutes(30)->toIso8601String(),
            ],
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScanStatus::RUNNING->value,
            'started_at' => now()->subMinutes(10),
        ]);
    }

    public function withNotification(string $email = 'test@example.com'): static
    {
        return $this->state(fn (array $attributes): array => [
            'send_notification' => true,
            'notification_email' => $email,
        ]);
    }
}
