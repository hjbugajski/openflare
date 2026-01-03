<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorCheck>
 */
class MonitorCheckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid7(),
            'monitor_id' => Monitor::factory(),
            'status' => 'up',
            'status_code' => 200,
            'response_time_ms' => fake()->numberBetween(50, 500),
            'error_message' => null,
            'checked_at' => now(),
        ];
    }

    public function up(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'up',
            'status_code' => 200,
            'error_message' => null,
        ]);
    }

    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'down',
            'status_code' => fake()->randomElement([0, 500, 502, 503, 504]),
            'response_time_ms' => null,
            'error_message' => fake()->sentence(),
        ]);
    }

    public function checkedAt(\DateTimeInterface $dateTime): static
    {
        return $this->state(fn (array $attributes) => [
            'checked_at' => $dateTime,
        ]);
    }
}
