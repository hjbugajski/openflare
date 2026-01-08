<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withoutVite();
});

describe('index', function () {
    it('requires authentication', function () {
        $this->get(route('monitors.index'))
            ->assertRedirect(route('login'));
    });

    it('returns monitors for authenticated user', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        Monitor::factory()->create(); // other user's monitor

        $this->actingAs($this->user)
            ->get(route('monitors.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('monitors/index', shouldExist: false)
                ->has('monitors', 1)
                ->where('monitors.0.id', (string) $monitor->id)
            );
    });

    it('loads latest check and current incident', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        MonitorCheck::factory()->up()->create(['monitor_id' => $monitor->id]);

        $this->actingAs($this->user)
            ->get(route('monitors.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('monitors.0.latest_check')
            );
    });
});

describe('create', function () {
    it('requires authentication', function () {
        $this->get(route('monitors.create'))
            ->assertRedirect(route('login'));
    });

    it('returns create form with notifiers', function () {
        Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('monitors.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('monitors/create', shouldExist: false)
                ->has('notifiers', 1)
                ->has('intervals')
                ->has('methods')
            );
    });

    it('excludes inactive notifiers', function () {
        Notifier::factory()->inactive()->create([
            'user_id' => $this->user->uuid,
        ]);

        $this->actingAs($this->user)
            ->get(route('monitors.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('monitors/create', shouldExist: false)
                ->has('notifiers', 0)
            );
    });
});

describe('store', function () {
    it('requires authentication', function () {
        $this->post(route('monitors.store'), [])
            ->assertRedirect(route('login'));
    });

    it('creates a monitor with valid data', function () {
        $data = [
            'name' => 'Test Monitor',
            'url' => 'https://example.com',
            'method' => 'GET',
            'interval' => 300,
            'timeout' => 30,
            'expected_status_code' => 200,
            'is_active' => true,
        ];

        $this->actingAs($this->user)
            ->post(route('monitors.store'), $data)
            ->assertRedirect();

        $this->assertDatabaseHas('monitors', [
            'user_id' => $this->user->uuid,
            'name' => 'Test Monitor',
            'url' => 'https://example.com',
        ]);
    });

    it('attaches notifiers', function () {
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
        ]);

        $data = [
            'name' => 'Test Monitor',
            'url' => 'https://example.com',
            'method' => 'GET',
            'interval' => 300,
            'timeout' => 30,
            'expected_status_code' => 200,
            'notifiers' => [(string) $notifier->id],
        ];

        $this->actingAs($this->user)
            ->post(route('monitors.store'), $data)
            ->assertRedirect();

        $monitor = Monitor::where('name', 'Test Monitor')->first();
        expect($monitor->notifiers)->toHaveCount(1);
    });

    it('auto-attaches default notifiers', function () {
        $defaultNotifier = Notifier::factory()->default()->create([
            'user_id' => $this->user->uuid,
        ]);
        $nonDefaultNotifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_default' => false,
        ]);

        $data = [
            'name' => 'Test Monitor',
            'url' => 'https://example.com',
            'method' => 'GET',
            'interval' => 300,
            'timeout' => 30,
            'expected_status_code' => 200,
            // No notifiers specified - should auto-attach defaults
        ];

        $this->actingAs($this->user)
            ->post(route('monitors.store'), $data)
            ->assertRedirect();

        $monitor = Monitor::where('name', 'Test Monitor')->first();
        expect($monitor->notifiers)->toHaveCount(1);
        expect((string) $monitor->notifiers->first()->id)->toBe((string) $defaultNotifier->id);
    });

    it('auto-attaches apply-to-all notifiers', function () {
        $applyToAllNotifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'apply_to_all' => true,
        ]);
        $regularNotifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'apply_to_all' => false,
        ]);

        $data = [
            'name' => 'Test Monitor',
            'url' => 'https://example.com',
            'method' => 'GET',
            'interval' => 300,
            'timeout' => 30,
            'expected_status_code' => 200,
        ];

        $this->actingAs($this->user)
            ->post(route('monitors.store'), $data)
            ->assertRedirect();

        $monitor = Monitor::where('name', 'Test Monitor')->first();
        expect($monitor->notifiers)->toHaveCount(1);
        expect((string) $monitor->notifiers->first()->id)->toBe((string) $applyToAllNotifier->id);
    });

    it('validates required fields', function () {
        $this->actingAs($this->user)
            ->post(route('monitors.store'), [])
            ->assertSessionHasErrors(['name', 'url', 'method', 'interval', 'timeout', 'expected_status_code']);
    });

    it('validates url format', function () {
        $this->actingAs($this->user)
            ->post(route('monitors.store'), ['url' => 'not-a-url'])
            ->assertSessionHasErrors('url');
    });

    it('validates interval is in allowed values', function () {
        $this->actingAs($this->user)
            ->post(route('monitors.store'), ['interval' => 999])
            ->assertSessionHasErrors('interval');
    });

    it('validates method is in allowed values', function () {
        $this->actingAs($this->user)
            ->post(route('monitors.store'), ['method' => 'POST'])
            ->assertSessionHasErrors('method');
    });

    it('validates timeout range', function () {
        $this->actingAs($this->user)
            ->post(route('monitors.store'), ['timeout' => 1])
            ->assertSessionHasErrors('timeout');

        $this->actingAs($this->user)
            ->post(route('monitors.store'), ['timeout' => 999])
            ->assertSessionHasErrors('timeout');
    });

    it('validates expected status code range', function () {
        $this->actingAs($this->user)
            ->post(route('monitors.store'), ['expected_status_code' => 50])
            ->assertSessionHasErrors('expected_status_code');

        $this->actingAs($this->user)
            ->post(route('monitors.store'), ['expected_status_code' => 700])
            ->assertSessionHasErrors('expected_status_code');
    });
});

describe('show', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();

        $this->get(route('monitors.show', $monitor))
            ->assertRedirect(route('login'));
    });

    it('displays monitor details', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->get(route('monitors.show', $monitor))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('monitors/show', shouldExist: false)
                ->where('monitor.id', (string) $monitor->id)
                ->has('checks')
                ->has('incidents')
                ->has('notifiers')
            );
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->get(route('monitors.show', $monitor))
            ->assertForbidden();
    });
});

describe('edit', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();

        $this->get(route('monitors.edit', $monitor))
            ->assertRedirect(route('login'));
    });

    it('returns edit form with monitor data', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
        ]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->get(route('monitors.edit', $monitor))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('monitors/edit', shouldExist: false)
                ->where('monitor.id', (string) $monitor->id)
                ->has('monitor.notifiers', 1)
                ->has('notifiers')
                ->has('intervals')
                ->has('methods')
            );
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->get(route('monitors.edit', $monitor))
            ->assertForbidden();
    });
});

describe('update', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();

        $this->put(route('monitors.update', $monitor), [])
            ->assertRedirect(route('login'));
    });

    it('updates monitor with valid data', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->put(route('monitors.update', $monitor), [
                'name' => 'Updated Name',
                'url' => 'https://updated.com',
            ])
            ->assertRedirect(route('monitors.show', $monitor));

        $monitor->refresh();
        expect($monitor->name)->toBe('Updated Name');
        expect($monitor->url)->toBe('https://updated.com');
    });

    it('syncs notifiers', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $oldNotifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        $newNotifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        $monitor->notifiers()->attach($oldNotifier);

        $this->actingAs($this->user)
            ->put(route('monitors.update', $monitor), [
                'notifiers' => [(string) $newNotifier->id],
            ])
            ->assertRedirect();

        $monitor->refresh();
        expect($monitor->notifiers->pluck('id')->map(fn ($id) => (string) $id)->toArray())->toBe([(string) $newNotifier->id]);
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->put(route('monitors.update', $monitor), ['name' => 'Hacked'])
            ->assertForbidden();
    });

    it('validates url format', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->put(route('monitors.update', $monitor), ['url' => 'not-a-url'])
            ->assertSessionHasErrors('url');
    });
});

describe('destroy', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();

        $this->delete(route('monitors.destroy', $monitor))
            ->assertRedirect(route('login'));
    });

    it('deletes the monitor', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->delete(route('monitors.destroy', $monitor))
            ->assertRedirect(route('monitors.index'));

        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('monitors.destroy', $monitor))
            ->assertForbidden();
    });
});

describe('attachNotifier', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();
        $notifier = Notifier::factory()->create();

        $this->post(route('monitors.notifiers.attach', [$monitor, $notifier]))
            ->assertRedirect(route('login'));
    });

    it('attaches a notifier to a monitor', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->post(route('monitors.notifiers.attach', [$monitor, $notifier]))
            ->assertRedirect();

        expect($monitor->notifiers->pluck('id')->map(fn ($id) => (string) $id)->toArray())
            ->toContain((string) $notifier->id);
    });

    it('does not duplicate when attaching same notifier twice', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->post(route('monitors.notifiers.attach', [$monitor, $notifier]))
            ->assertRedirect();

        expect($monitor->fresh()->notifiers)->toHaveCount(1);
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->post(route('monitors.notifiers.attach', [$monitor, $notifier]))
            ->assertForbidden();
    });

    it('denies access to other users notifiers', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create();

        $this->actingAs($this->user)
            ->post(route('monitors.notifiers.attach', [$monitor, $notifier]))
            ->assertForbidden();
    });
});

describe('detachNotifier', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();
        $notifier = Notifier::factory()->create();

        $this->delete(route('monitors.notifiers.detach', [$monitor, $notifier]))
            ->assertRedirect(route('login'));
    });

    it('detaches a notifier from a monitor', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->delete(route('monitors.notifiers.detach', [$monitor, $notifier]))
            ->assertRedirect();

        expect($monitor->fresh()->notifiers)->toHaveCount(0);
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->delete(route('monitors.notifiers.detach', [$monitor, $notifier]))
            ->assertForbidden();
    });

    it('denies access to other users notifiers', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create();
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->delete(route('monitors.notifiers.detach', [$monitor, $notifier]))
            ->assertForbidden();
    });

    it('excludes apply-to-all notifier instead of detaching', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'apply_to_all' => true,
        ]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->delete(route('monitors.notifiers.detach', [$monitor, $notifier]))
            ->assertRedirect();

        expect($notifier->fresh()->apply_to_all)->toBeTrue();
        $this->assertDatabaseHas('monitor_notifier', [
            'monitor_id' => $monitor->id,
            'notifier_id' => $notifier->id,
            'is_excluded' => true,
        ]);
    });

    it('detaches non-apply-to-all notifier completely', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'apply_to_all' => false,
        ]);
        $monitor->notifiers()->attach($notifier);

        $this->actingAs($this->user)
            ->delete(route('monitors.notifiers.detach', [$monitor, $notifier]))
            ->assertRedirect();

        $this->assertDatabaseMissing('monitor_notifier', [
            'monitor_id' => $monitor->id,
            'notifier_id' => $notifier->id,
        ]);
    });

    it('clears exclusion when re-attaching notifier', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'apply_to_all' => true,
        ]);
        $monitor->notifiers()->attach($notifier, ['is_excluded' => true]);

        $this->actingAs($this->user)
            ->post(route('monitors.notifiers.attach', [$monitor, $notifier]))
            ->assertRedirect();

        $this->assertDatabaseHas('monitor_notifier', [
            'monitor_id' => $monitor->id,
            'notifier_id' => $notifier->id,
            'is_excluded' => false,
        ]);
    });
});
