<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notifier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notifier>
 */
class NotifierFactory extends Factory
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
            'type' => Notifier::TYPE_DISCORD,
            'name' => fake()->words(2, true).' Webhook',
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/'.fake()->numerify('##################').'/'.fake()->sha256(),
            ],
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function discord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Notifier::TYPE_DISCORD,
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/'.fake()->numerify('##################').'/'.fake()->sha256(),
            ],
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Notifier::TYPE_EMAIL,
            'config' => [
                'email' => fake()->safeEmail(),
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function applyToAll(): static
    {
        return $this->state(fn (array $attributes) => [
            'apply_to_all' => true,
        ]);
    }
}
