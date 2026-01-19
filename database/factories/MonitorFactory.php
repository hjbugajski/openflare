<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid7(),
            'user_id' => function (array $attributes) {
                return User::factory()->create()->uuid;
            },
            'name' => fake()->domainWord().' Monitor',
            'url' => fake()->url(),
            'method' => fake()->randomElement(Monitor::METHODS),
            'interval' => fake()->randomElement(Monitor::INTERVALS),
            'timeout' => 30,
            'expected_status_code' => 200,
            'failure_confirmation_threshold' => 3,
            'recovery_confirmation_threshold' => 3,
            'is_active' => true,
            'last_checked_at' => null,
            'next_check_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withInterval(int $seconds): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => $seconds,
        ]);
    }
}
