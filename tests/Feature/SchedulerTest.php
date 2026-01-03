<?php

use App\Jobs\CheckMonitor;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Config::set('monitors.test_mode', false);
});

it('dispatches checks for monitors due for checking', function () {
    Queue::fake();

    $user = User::factory()->create();

    $dueMonitor = Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->subMinute(),
    ]));

    Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->addHour(),
    ]));

    Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => false,
        'next_check_at' => now()->subMinute(),
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertPushed(CheckMonitor::class, 1);
    Queue::assertPushed(CheckMonitor::class, fn ($job) => (string) $job->monitor->id === (string) $dueMonitor->id);
});

it('dispatches checks for monitors with null next_check_at', function () {
    Queue::fake();

    $user = User::factory()->create();

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => true,
        'next_check_at' => null,
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertPushed(CheckMonitor::class, 1);
    Queue::assertPushed(CheckMonitor::class, fn ($job) => (string) $job->monitor->id === (string) $monitor->id);
});

it('does not dispatch checks for inactive monitors', function () {
    Queue::fake();

    $user = User::factory()->create();

    Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => false,
        'next_check_at' => now()->subMinute(),
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertNothingPushed();
});

it('does not dispatch checks when no monitors are due', function () {
    Queue::fake();

    $user = User::factory()->create();

    Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->addHour(),
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertNothingPushed();
});

it('dispatches checks for heavily overdue monitors', function () {
    Queue::fake();

    $user = User::factory()->create();

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->subHours(24),
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertPushed(CheckMonitor::class, 1);
    Queue::assertPushed(CheckMonitor::class, fn ($job) => (string) $job->monitor->id === (string) $monitor->id);
});

it('respects dispatch limit to prevent stampeding', function () {
    Queue::fake();
    Config::set('monitors.dispatch_limit', 3);

    $user = User::factory()->create();

    Monitor::withoutEvents(fn () => Monitor::factory(5)->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->subMinute(),
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertPushed(CheckMonitor::class, 3);
});

it('respects dispatch limit from command option', function () {
    Queue::fake();

    $user = User::factory()->create();

    Monitor::withoutEvents(fn () => Monitor::factory(5)->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->subMinute(),
    ]));

    Artisan::call('monitors:dispatch-checks', ['--limit' => 2]);

    Queue::assertPushed(CheckMonitor::class, 2);
});

it('dispatches to monitors queue', function () {
    Queue::fake();

    $user = User::factory()->create();

    Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->subMinute(),
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertPushedOn('monitors', CheckMonitor::class);
});

it('skips dispatch when test mode is enabled', function () {
    Queue::fake();
    Config::set('monitors.test_mode', true);

    $user = User::factory()->create();

    Monitor::withoutEvents(fn () => Monitor::factory()->for($user)->create([
        'is_active' => true,
        'next_check_at' => now()->subMinute(),
    ]));

    Artisan::call('monitors:dispatch-checks');

    Queue::assertNothingPushed();
});
