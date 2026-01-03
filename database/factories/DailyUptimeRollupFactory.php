<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyUptimeRollup>
 */
class DailyUptimeRollupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalChecks = fake()->numberBetween(50, 200);
        $successfulChecks = fake()->numberBetween((int) ($totalChecks * 0.8), $totalChecks);
        $uptimePercentage = round(($successfulChecks / $totalChecks) * 100, 2);

        return [
            'id' => \Illuminate\Support\Str::uuid7(),
            'monitor_id' => Monitor::factory(),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'total_checks' => $totalChecks,
            'successful_checks' => $successfulChecks,
            'uptime_percentage' => $uptimePercentage,
            'avg_response_time_ms' => fake()->numberBetween(100, 500),
            'min_response_time_ms' => fake()->numberBetween(50, 100),
            'max_response_time_ms' => fake()->numberBetween(500, 1000),
        ];
    }

    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    public function perfect(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_checks' => 100,
            'successful_checks' => 100,
            'uptime_percentage' => 100.00,
        ]);
    }
}
