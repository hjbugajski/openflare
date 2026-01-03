<?php

use App\Jobs\CheckMonitor;
use App\Models\Monitor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    Config::set('monitors.test_mode', false);
});

describe('creation', function () {
    it('sets next_check_at to now when creating active monitor', function () {
        $monitor = Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => null,
        ]);

        expect($monitor->next_check_at)->not->toBeNull();
        expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThan(2);
    });

    it('dispatches check job when active monitor is created', function () {
        $monitor = Monitor::factory()->create([
            'is_active' => true,
        ]);

        Queue::assertPushed(CheckMonitor::class, fn ($job) => (string) $job->monitor->id === (string) $monitor->id);
    });

    it('does not dispatch check job when inactive monitor is created', function () {
        Monitor::factory()->inactive()->create();

        Queue::assertNothingPushed();
    });

    it('does not set next_check_at when creating inactive monitor', function () {
        $monitor = Monitor::factory()->inactive()->create([
            'next_check_at' => null,
        ]);

        expect($monitor->next_check_at)->toBeNull();
    });
});

describe('pause/resume', function () {
    it('clears next_check_at when pausing monitor', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => now()->addHour(),
        ]));

        $monitor->update(['is_active' => false]);

        $monitor->refresh();
        expect($monitor->next_check_at)->toBeNull();
    });

    it('sets next_check_at to now when resuming monitor', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => false,
            'next_check_at' => null,
        ]));

        $monitor->update(['is_active' => true]);

        $monitor->refresh();
        expect($monitor->next_check_at)->not->toBeNull();
        expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThan(2);
    });

    it('dispatches check job when resuming monitor', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => false,
            'next_check_at' => null,
        ]));

        $monitor->update(['is_active' => true]);

        Queue::assertPushed(CheckMonitor::class, fn ($job) => (string) $job->monitor->id === (string) $monitor->id);
    });

    it('does not dispatch check job when pausing monitor', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => now()->addHour(),
        ]));

        $monitor->update(['is_active' => false]);

        Queue::assertNothingPushed();
    });
});

describe('update recompute', function () {
    it('resets next_check_at when interval changes', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => now()->addHour(),
        ]));

        $monitor->update(['interval' => 60]);

        $monitor->refresh();
        expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThan(2);
    });

    it('resets next_check_at when timeout changes', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => now()->addHour(),
        ]));

        $monitor->update(['timeout' => 60]);

        $monitor->refresh();
        expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThan(2);
    });

    it('resets next_check_at when expected_status_code changes', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => now()->addHour(),
        ]));

        $monitor->update(['expected_status_code' => 201]);

        $monitor->refresh();
        expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThan(2);
    });

    it('resets next_check_at when url changes', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => now()->addHour(),
        ]));

        $monitor->update(['url' => 'https://example.org']);

        $monitor->refresh();
        expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThan(2);
    });

    it('resets next_check_at when method changes', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'method' => 'GET',
            'next_check_at' => now()->addHour(),
        ]));

        $monitor->update(['method' => 'HEAD']);

        $monitor->refresh();
        expect($monitor->next_check_at->diffInSeconds(now()))->toBeLessThan(2);
    });

    it('does not reset next_check_at when name changes', function () {
        $originalNextCheck = now()->addHour();
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'next_check_at' => $originalNextCheck,
        ]));

        $monitor->update(['name' => 'New Name']);

        $monitor->refresh();
        expect($monitor->next_check_at->diffInSeconds($originalNextCheck))->toBeLessThan(2);
    });

    it('does not reset next_check_at on inactive monitor', function () {
        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => false,
            'next_check_at' => null,
        ]));

        $monitor->update(['interval' => 60]);

        $monitor->refresh();
        expect($monitor->next_check_at)->toBeNull();
    });
});

describe('deleted monitor guard', function () {
    it('skips check when monitor is deleted', function () {
        Http::fake([
            'https://example.com' => Http::response('OK', 200),
        ]);

        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'url' => 'https://example.com',
        ]));

        $job = new CheckMonitor($monitor);
        $monitor->delete();

        $job->handle();

        Http::assertNothingSent();
    });

    it('skips check when monitor becomes inactive', function () {
        Http::fake([
            'https://example.com' => Http::response('OK', 200),
        ]);

        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => true,
            'url' => 'https://example.com',
        ]));

        $job = new CheckMonitor($monitor);

        Monitor::withoutEvents(fn () => $monitor->update(['is_active' => false]));

        $job->handle();

        Http::assertNothingSent();
    });
});

describe('test mode', function () {
    it('does not dispatch check job when creating monitor in test mode', function () {
        Config::set('monitors.test_mode', true);

        Monitor::factory()->create([
            'is_active' => true,
        ]);

        Queue::assertNothingPushed();
    });

    it('does not dispatch check job when resuming monitor in test mode', function () {
        Config::set('monitors.test_mode', true);

        $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create([
            'is_active' => false,
            'next_check_at' => null,
        ]));

        $monitor->update(['is_active' => true]);

        Queue::assertNothingPushed();
    });
});
