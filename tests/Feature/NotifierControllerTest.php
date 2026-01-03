<?php

declare(strict_types=1);

use App\Mail\TestNotification;
use App\Models\Monitor;
use App\Models\Notifier;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withoutVite();
});

describe('index', function () {
    it('requires authentication', function () {
        $this->get(route('notifiers.index'))
            ->assertRedirect(route('login'));
    });

    it('returns notifiers for authenticated user', function () {
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        Notifier::factory()->create(); // other user's notifier

        $this->actingAs($this->user)
            ->get(route('notifiers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifiers/index', shouldExist: false)
                ->has('notifiers.data', 1)
                ->where('notifiers.data.0.id', (string) $notifier->id)
                ->has('types')
            );
    });

    it('includes monitor count', function () {
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->get(route('notifiers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifiers/index', shouldExist: false)
                ->where('notifiers.data.0.monitors_count', 1)
            );
    });

    it('includes is_default field', function () {
        Notifier::factory()->default()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->get(route('notifiers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifiers.data.0.is_default', true)
            );
    });
});

describe('create', function () {
    it('requires authentication', function () {
        $this->get(route('notifiers.create'))
            ->assertRedirect(route('login'));
    });

    it('returns create form with types', function () {
        $this->actingAs($this->user)
            ->get(route('notifiers.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifiers/create', shouldExist: false)
                ->has('types')
            );
    });
});

describe('store', function () {
    it('requires authentication', function () {
        $this->post(route('notifiers.store'), [])
            ->assertRedirect(route('login'));
    });

    it('creates a discord notifier with valid data', function () {
        $data = [
            'name' => 'Discord Alerts',
            'type' => 'discord',
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
            ],
            'is_active' => true,
            'is_default' => false,
        ];

        $this->actingAs($this->user)
            ->post(route('notifiers.store'), $data)
            ->assertRedirect(route('notifiers.index'));

        $this->assertDatabaseHas('notifiers', [
            'user_id' => $this->user->uuid,
            'name' => 'Discord Alerts',
            'type' => 'discord',
            'is_default' => false,
        ]);
    });

    it('creates an email notifier with valid data', function () {
        $data = [
            'name' => 'Email Alerts',
            'type' => 'email',
            'config' => [
                'email' => 'alerts@example.com',
            ],
            'is_active' => true,
            'is_default' => true,
        ];

        $this->actingAs($this->user)
            ->post(route('notifiers.store'), $data)
            ->assertRedirect(route('notifiers.index'));

        $this->assertDatabaseHas('notifiers', [
            'user_id' => $this->user->uuid,
            'name' => 'Email Alerts',
            'type' => 'email',
            'is_default' => true,
        ]);
    });

    it('validates required fields', function () {
        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [])
            ->assertSessionHasErrors(['name', 'type']);
    });

    it('validates type is in allowed values', function () {
        $this->actingAs($this->user)
            ->post(route('notifiers.store'), ['type' => 'slack'])
            ->assertSessionHasErrors('type');
    });

    it('validates discord webhook url format', function () {
        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Discord',
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://example.com/not-discord',
                ],
            ])
            ->assertSessionHasErrors('config.webhook_url');
    });

    it('requires webhook url for discord type', function () {
        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Discord',
                'type' => 'discord',
                'config' => [],
            ])
            ->assertSessionHasErrors('config.webhook_url');
    });

    it('validates email format', function () {
        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Email',
                'type' => 'email',
                'config' => [
                    'email' => 'not-an-email',
                ],
            ])
            ->assertSessionHasErrors('config.email');
    });

    it('requires email for email type', function () {
        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Email',
                'type' => 'email',
                'config' => [],
            ])
            ->assertSessionHasErrors('config.email');
    });

    it('attaches monitors when provided', function () {
        $monitors = Monitor::factory(3)->create(['user_id' => $this->user->uuid]);
        $otherUserMonitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Discord Alerts',
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
                ],
                'is_active' => true,
                'monitors' => $monitors->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            ])
            ->assertRedirect(route('notifiers.index'));

        $notifier = Notifier::where('name', 'Discord Alerts')->first();

        expect($notifier->monitors)->toHaveCount(3);
        expect($notifier->monitors->pluck('id')->sort()->values())
            ->toEqual($monitors->pluck('id')->sort()->values());

        // Should not attach to other user's monitors
        $this->assertDatabaseMissing('monitor_notifier', [
            'notifier_id' => $notifier->id,
            'monitor_id' => $otherUserMonitor->id,
        ]);
    });

    it('attaches all monitors when apply_to_existing is true', function () {
        $monitors = Monitor::factory(3)->create(['user_id' => $this->user->uuid]);
        Monitor::factory()->create(); // other user's monitor

        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Discord Alerts',
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
                ],
                'is_active' => true,
                'apply_to_existing' => true,
            ])
            ->assertRedirect(route('notifiers.index'));

        $notifier = Notifier::where('name', 'Discord Alerts')->first();

        expect($notifier->monitors)->toHaveCount(3);
        expect($notifier->monitors->pluck('id')->sort()->values())
            ->toEqual($monitors->pluck('id')->sort()->values());
    });

    it('does not attach monitors when none provided', function () {
        Monitor::factory(2)->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Discord Alerts',
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
                ],
                'is_active' => true,
            ])
            ->assertRedirect(route('notifiers.index'));

        $notifier = Notifier::where('name', 'Discord Alerts')->first();

        expect($notifier->monitors)->toHaveCount(0);
    });

    it('validates monitors belong to user', function () {
        $otherUserMonitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->post(route('notifiers.store'), [
                'name' => 'Discord Alerts',
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
                ],
                'monitors' => [$otherUserMonitor->id],
            ])
            ->assertSessionHasErrors('monitors.0');
    });
});

describe('edit', function () {
    it('requires authentication', function () {
        $notifier = Notifier::factory()->create();

        $this->get(route('notifiers.edit', $notifier))
            ->assertRedirect(route('login'));
    });

    it('returns edit form with notifier data', function () {
        $notifier = Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
        ]);

        $this->actingAs($this->user)
            ->get(route('notifiers.edit', $notifier))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifiers/edit', shouldExist: false)
                ->where('notifier.id', (string) $notifier->id)
                ->has('types')
            );
    });

    it('denies access to other users notifiers', function () {
        $notifier = Notifier::factory()->create();

        $this->actingAs($this->user)
            ->get(route('notifiers.edit', $notifier))
            ->assertForbidden();
    });
});

describe('update', function () {
    it('requires authentication', function () {
        $notifier = Notifier::factory()->create();

        $this->put(route('notifiers.update', $notifier), [])
            ->assertRedirect(route('login'));
    });

    it('updates notifier with valid data', function () {
        $notifier = Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
            'is_default' => false,
        ]);

        $this->actingAs($this->user)
            ->put(route('notifiers.update', $notifier), [
                'name' => 'Updated Name',
                'is_active' => false,
                'is_default' => true,
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/456/def',
                ],
            ])
            ->assertRedirect(route('notifiers.index'));

        $notifier->refresh();
        expect($notifier->name)->toBe('Updated Name');
        expect($notifier->is_active)->toBeFalse();
        expect($notifier->is_default)->toBeTrue();
    });

    it('denies access to other users notifiers', function () {
        $notifier = Notifier::factory()->discord()->create();

        $this->actingAs($this->user)
            ->put(route('notifiers.update', $notifier), [
                'name' => 'Hacked',
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
                ],
            ])
            ->assertForbidden();
    });

    it('validates webhook url format on update', function () {
        $notifier = Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
        ]);

        $this->actingAs($this->user)
            ->put(route('notifiers.update', $notifier), [
                'config' => [
                    'webhook_url' => 'https://example.com/not-discord',
                ],
            ])
            ->assertSessionHasErrors('config.webhook_url');
    });

    it('syncs monitors on update', function () {
        $notifier = Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
        ]);
        $monitors = Monitor::factory(3)->create(['user_id' => $this->user->uuid]);
        $notifier->monitors()->attach($monitors->take(2)->pluck('id'));

        $this->actingAs($this->user)
            ->put(route('notifiers.update', $notifier), [
                'name' => $notifier->name,
                'config' => [
                    'webhook_url' => $notifier->config['webhook_url'],
                ],
                'monitors' => [(string) $monitors[1]->id, (string) $monitors[2]->id],
            ])
            ->assertRedirect(route('notifiers.index'));

        $notifier->refresh();
        expect($notifier->monitors)->toHaveCount(2);
        expect($notifier->monitors->pluck('id')->sort()->values())
            ->toEqual(collect([$monitors[1]->id, $monitors[2]->id])->sort()->values());
    });

    it('attaches all monitors when apply_to_existing is true on update', function () {
        $notifier = Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
        ]);
        $monitors = Monitor::factory(3)->create(['user_id' => $this->user->uuid]);
        Monitor::factory()->create(); // other user's monitor
        $notifier->monitors()->attach([$monitors->first()->id]);

        $this->actingAs($this->user)
            ->put(route('notifiers.update', $notifier), [
                'name' => $notifier->name,
                'config' => [
                    'webhook_url' => $notifier->config['webhook_url'],
                ],
                'apply_to_existing' => true,
            ])
            ->assertRedirect(route('notifiers.index'));

        $notifier->refresh();
        expect($notifier->monitors)->toHaveCount(3);
        expect($notifier->monitors->pluck('id')->sort()->values())
            ->toEqual($monitors->pluck('id')->sort()->values());
    });
});

describe('destroy', function () {
    it('requires authentication', function () {
        $notifier = Notifier::factory()->create();

        $this->delete(route('notifiers.destroy', $notifier))
            ->assertRedirect(route('login'));
    });

    it('deletes the notifier', function () {
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->delete(route('notifiers.destroy', $notifier))
            ->assertRedirect(route('notifiers.index'));

        $this->assertDatabaseMissing('notifiers', ['id' => $notifier->id]);
    });

    it('denies access to other users notifiers', function () {
        $notifier = Notifier::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('notifiers.destroy', $notifier))
            ->assertForbidden();
    });

    it('detaches from monitors when deleted', function () {
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->delete(route('notifiers.destroy', $notifier))
            ->assertRedirect();

        $this->assertDatabaseMissing('monitor_notifier', [
            'notifier_id' => $notifier->id,
        ]);
    });
});

describe('test', function () {
    it('requires authentication', function () {
        $this->postJson(route('notifiers.test'), [])
            ->assertUnauthorized();
    });

    it('sends test discord notification', function () {
        Http::fake([
            'discord.com/*' => Http::response(['ok' => true], 204),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('notifiers.test'), [
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'discord.com/api/webhooks'));
    });

    it('sends test email notification', function () {
        Mail::fake();

        $this->actingAs($this->user)
            ->postJson(route('notifiers.test'), [
                'type' => 'email',
                'config' => [
                    'email' => 'test@example.com',
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        Mail::assertSent(TestNotification::class, fn ($mail) => $mail->hasTo('test@example.com'));
    });

    it('validates discord webhook url', function () {
        $this->actingAs($this->user)
            ->postJson(route('notifiers.test'), [
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://example.com/not-discord',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('config.webhook_url');
    });

    it('validates email address', function () {
        $this->actingAs($this->user)
            ->postJson(route('notifiers.test'), [
                'type' => 'email',
                'config' => [
                    'email' => 'not-an-email',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('config.email');
    });

    it('returns error when discord webhook fails', function () {
        Http::fake([
            'discord.com/*' => Http::response(['message' => 'Invalid webhook'], 400),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('notifiers.test'), [
                'type' => 'discord',
                'config' => [
                    'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
                ],
            ])
            ->assertUnprocessable()
            ->assertJson(['success' => false]);
    });
});
