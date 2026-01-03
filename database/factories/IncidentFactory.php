<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-7 days', '-1 hour');

        return [
            'id' => \Illuminate\Support\Str::uuid7(),
            'monitor_id' => Monitor::factory(),
            'started_at' => $startedAt,
            'ended_at' => fake()->dateTimeBetween($startedAt, 'now'),
            'cause' => fake()->sentence(),
        ];
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function resolved(): static
    {
        $startedAt = fake()->dateTimeBetween('-7 days', '-1 hour');

        return $this->state(fn (array $attributes) => [
            'started_at' => $startedAt,
            'ended_at' => fake()->dateTimeBetween($startedAt, 'now'),
        ]);
    }
}
