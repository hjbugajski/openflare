<?php

declare(strict_types=1);

use App\Events\IncidentOpened;
use App\Events\IncidentResolved;
use App\Events\MonitorChecked;
use App\Jobs\CheckMonitor;
use App\Jobs\SendMonitorNotification;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\MonitorStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('creates a check record when monitor is up', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    CheckMonitor::dispatchSync($monitor);

    expect($monitor->checks)->toHaveCount(1);
    expect($monitor->checks->first())
        ->status->toBe('up')
        ->status_code->toBe(200)
        ->response_time_ms->toBeGreaterThanOrEqual(0)
        ->error_message->toBeNull();
});

it('creates a check record when monitor is down due to status code mismatch', function () {
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    CheckMonitor::dispatchSync($monitor);

    expect($monitor->checks)->toHaveCount(1);
    expect($monitor->checks->first())
        ->status->toBe('down')
        ->status_code->toBe(500)
        ->error_message->toBe('Expected status 200, got 500');
});

it('creates a check record when monitor is down due to connection error', function () {
    Http::fake([
        'https://example.com' => Http::failedConnection('Connection refused'),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    CheckMonitor::dispatchSync($monitor);

    expect($monitor->checks)->toHaveCount(1);
    expect($monitor->checks->first())
        ->status->toBe('down')
        ->status_code->toBe(0)
        ->error_message->toContain('Connection refused');
});

it('updates monitor timestamps after check', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'interval' => 300,
        'last_checked_at' => null,
        'next_check_at' => null,
    ]));

    CheckMonitor::dispatchSync($monitor);

    $monitor->refresh();

    expect($monitor->last_checked_at)->not->toBeNull();
    expect($monitor->next_check_at)->not->toBeNull();
    expect((int) $monitor->last_checked_at->diffInSeconds($monitor->next_check_at))->toBe(300);
});

it('creates an incident when status changes from up to down', function () {
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    // Create a previous "up" check
    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    expect(Incident::count())->toBe(1);
    expect($monitor->currentIncident)
        ->not->toBeNull()
        ->cause->toBe('Expected status 200, got 500')
        ->ended_at->toBeNull();
});

it('resolves an incident when status changes from down to up', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    // Create a previous "down" check
    MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    // Create an ongoing incident
    $incident = Incident::factory()->ongoing()->create([
        'monitor_id' => $monitor->id,
    ]);

    CheckMonitor::dispatchSync($monitor);

    $incident->refresh();

    expect($incident->ended_at)->not->toBeNull();
    expect($incident->isResolved())->toBeTrue();
});

it('does not create an incident when status remains up', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    expect(Incident::count())->toBe(0);
});

it('does not resolve an incident when status remains down', function () {
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    $incident = Incident::factory()->ongoing()->create([
        'monitor_id' => $monitor->id,
    ]);

    CheckMonitor::dispatchSync($monitor);

    $incident->refresh();

    expect($incident->ended_at)->toBeNull();
    expect($incident->isOngoing())->toBeTrue();
});

it('skips inactive monitors', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::factory()->inactive()->create([
        'url' => 'https://example.com',
    ]);

    CheckMonitor::dispatchSync($monitor);

    expect($monitor->checks)->toHaveCount(0);
    Http::assertNothingSent();
});

it('uses the correct HTTP method', function () {
    Http::fake([
        'https://example.com' => Http::response('', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'method' => 'HEAD',
    ]));

    CheckMonitor::dispatchSync($monitor);

    Http::assertSent(fn ($request) => $request->method() === 'HEAD');
});

it('dispatches notifications when status changes from up to down', function () {
    Bus::fake([SendMonitorNotification::class]);
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    $notifier = Notifier::factory()->discord()->create([
        'user_id' => $monitor->user_id,
    ]);
    $monitor->notifiers()->attach($notifier);

    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    Bus::assertDispatched(SendMonitorNotification::class, function ($job) use ($monitor, $notifier) {
        return (string) $job->monitor->id === (string) $monitor->id
            && (string) $job->notifier->id === (string) $notifier->id
            && $job->status === MonitorStatus::Down;
    });
});

it('dispatches notifications when status changes from down to up', function () {
    Bus::fake([SendMonitorNotification::class]);
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    $notifier = Notifier::factory()->email()->create([
        'user_id' => $monitor->user_id,
    ]);
    $monitor->notifiers()->attach($notifier);

    MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    Incident::factory()->ongoing()->create([
        'monitor_id' => $monitor->id,
    ]);

    CheckMonitor::dispatchSync($monitor);

    Bus::assertDispatched(SendMonitorNotification::class, function ($job) use ($monitor, $notifier) {
        return (string) $job->monitor->id === (string) $monitor->id
            && (string) $job->notifier->id === (string) $notifier->id
            && $job->status === MonitorStatus::Up;
    });
});

it('does not dispatch notifications when status remains unchanged', function () {
    Bus::fake([SendMonitorNotification::class]);
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    $notifier = Notifier::factory()->discord()->create([
        'user_id' => $monitor->user_id,
    ]);
    $monitor->notifiers()->attach($notifier);

    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    Bus::assertNotDispatched(SendMonitorNotification::class);
});

it('only dispatches notifications to active channels', function () {
    Bus::fake([SendMonitorNotification::class]);
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    $activeNotifier = Notifier::factory()->discord()->create([
        'user_id' => $monitor->user_id,
        'is_active' => true,
    ]);
    $inactiveNotifier = Notifier::factory()->email()->inactive()->create([
        'user_id' => $monitor->user_id,
    ]);
    $monitor->notifiers()->attach([$activeNotifier->id, $inactiveNotifier->id]);

    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    Bus::assertDispatchedTimes(SendMonitorNotification::class, 1);
    Bus::assertDispatched(SendMonitorNotification::class, function ($job) use ($activeNotifier) {
        return (string) $job->notifier->id === (string) $activeNotifier->id;
    });
});

it('broadcasts MonitorChecked event after every check', function () {
    Event::fake([MonitorChecked::class]);
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    CheckMonitor::dispatchSync($monitor);

    Event::assertDispatched(MonitorChecked::class, function ($event) use ($monitor) {
        return (string) $event->monitor->id === (string) $monitor->id
            && $event->check->status === 'up';
    });
});

it('broadcasts IncidentOpened event when status changes from up to down', function () {
    Event::fake([IncidentOpened::class, MonitorChecked::class]);
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    Event::assertDispatched(IncidentOpened::class, function ($event) use ($monitor) {
        return (string) $event->monitor->id === (string) $monitor->id
            && $event->incident->cause === 'Expected status 200, got 500';
    });
});

it('broadcasts IncidentResolved event when status changes from down to up', function () {
    Event::fake([IncidentResolved::class, MonitorChecked::class]);
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    $incident = Incident::factory()->ongoing()->create([
        'monitor_id' => $monitor->id,
    ]);

    CheckMonitor::dispatchSync($monitor);

    Event::assertDispatched(IncidentResolved::class, function ($event) use ($monitor, $incident) {
        return (string) $event->monitor->id === (string) $monitor->id
            && (string) $event->incident->id === (string) $incident->id
            && $event->incident->ended_at !== null;
    });
});

it('does not broadcast IncidentOpened when status remains up', function () {
    Event::fake([IncidentOpened::class, MonitorChecked::class]);
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    Event::assertNotDispatched(IncidentOpened::class);
    Event::assertDispatched(MonitorChecked::class);
});

it('does not broadcast IncidentResolved when status remains down', function () {
    Event::fake([IncidentResolved::class, MonitorChecked::class]);
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    Incident::factory()->ongoing()->create([
        'monitor_id' => $monitor->id,
    ]);

    CheckMonitor::dispatchSync($monitor);

    Event::assertNotDispatched(IncidentResolved::class);
    Event::assertDispatched(MonitorChecked::class);
});

it('does not dispatch notifications to channels with missing config', function () {
    Bus::fake([SendMonitorNotification::class]);
    Http::fake([
        'https://example.com' => Http::response('Server Error', 500),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'expected_status_code' => 200,
    ]));

    $validNotifier = Notifier::factory()->discord()->create([
        'user_id' => $monitor->user_id,
        'is_active' => true,
    ]);

    $invalidNotifier = Notifier::factory()->create([
        'user_id' => $monitor->user_id,
        'type' => 'discord',
        'config' => [],
        'is_active' => true,
    ]);

    $monitor->notifiers()->attach([$validNotifier->id, $invalidNotifier->id]);

    MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subMinutes(5),
    ]);

    CheckMonitor::dispatchSync($monitor);

    Bus::assertDispatchedTimes(SendMonitorNotification::class, 1);
    Bus::assertDispatched(SendMonitorNotification::class, function ($job) use ($validNotifier) {
        return (string) $job->notifier->id === (string) $validNotifier->id;
    });
});

it('calculates next_check_at based on scheduled time, not execution time', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    $scheduledAt = now()->subSeconds(30);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'interval' => 60,
        'next_check_at' => $scheduledAt,
    ]));

    CheckMonitor::dispatchSync($monitor);

    $monitor->refresh();

    // next_check_at should be scheduledAt + 60s, not now() + 60s
    expect($monitor->next_check_at->timestamp)->toBe($scheduledAt->addSeconds(60)->timestamp);
});

it('catches up to next future slot when significantly behind schedule', function () {
    Http::fake([
        'https://example.com' => Http::response('OK', 200),
    ]);

    // Simulate being 2.5 intervals behind
    $scheduledAt = now()->subSeconds(150);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
        'url' => 'https://example.com',
        'interval' => 60,
        'next_check_at' => $scheduledAt,
    ]));

    CheckMonitor::dispatchSync($monitor);

    $monitor->refresh();

    // Should skip ahead to the next future slot
    expect($monitor->next_check_at->isFuture())->toBeTrue();
    // Should be within the next interval from now
    expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThanOrEqual(60);
});
