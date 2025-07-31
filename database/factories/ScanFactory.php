<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Scan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ScanFactory extends Factory
{
    protected $model = Scan::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'company_id' => Company::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'agent' => $this->faker->optional()->randomElement(['nmap', 'nessus', 'openvas']),
            'send_notification' => $this->faker->boolean(30),
            'notification_email' => $this->faker->optional(0.3)->email(),
            'schedule_type' => $this->faker->optional()->randomElement(['once', 'daily', 'weekly', 'monthly']),
            'scheduled_at' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'cron_expression' => $this->faker->optional()->randomElement(['0 0 * * *', '0 12 * * 0', '0 9 * * 1']),
            'status' => $this->faker->randomElement(['pending', 'running', 'completed', 'failed']),
            'risk_grade' => $this->faker->optional()->randomElement(['A', 'B', 'C', 'D', 'F']),
            'summary' => $this->faker->optional()->randomElement([
                ['total_vulnerabilities' => $this->faker->numberBetween(0, 100), 'critical' => $this->faker->numberBetween(0, 10)],
                ['hosts_scanned' => $this->faker->numberBetween(1, 50), 'open_ports' => $this->faker->numberBetween(0, 200)],
            ]),
            'started_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'completed_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ];
    }
}
