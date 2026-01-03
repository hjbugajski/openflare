<?php

use App\Actions\BackfillMissingRollups;
use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\MonitorStatus;
use Illuminate\Support\Facades\Artisan;

it('backfills missing rollups when no rollups exist', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    for ($i = 1; $i <= 30; $i++) {
        MonitorCheck::factory()->create([
            'monitor_id' => $monitor->id,
            'checked_at' => now()->subDays($i)->midDay(),
            'status' => MonitorStatus::Up,
        ]);
    }

    app(BackfillMissingRollups::class)->handle();

    expect(DailyUptimeRollup::count())->toBe(30);
});

it('backfills missing rollups when server was down for multiple days', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => now()->subDays(5)->toDateString(),
    ]);

    for ($i = 1; $i <= 4; $i++) {
        MonitorCheck::factory()->create([
            'monitor_id' => $monitor->id,
            'checked_at' => now()->subDays($i)->midDay(),
            'status' => MonitorStatus::Up,
        ]);
    }

    app(BackfillMissingRollups::class)->handle();

    expect(DailyUptimeRollup::count())->toBe(5);
});

it('does not backfill when rollups are up to date', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => now()->subDay()->toDateString(),
    ]);

    $initialCount = DailyUptimeRollup::count();

    app(BackfillMissingRollups::class)->handle();

    expect(DailyUptimeRollup::count())->toBe($initialCount);
});

it('handles missing rollup for exactly one day', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => now()->subDays(2)->toDateString(),
    ]);

    MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subDay()->midDay(),
        'status' => MonitorStatus::Up,
    ]);

    app(BackfillMissingRollups::class)->handle();

    expect(DailyUptimeRollup::count())->toBe(2);
});

it('computes accurate rollups during backfill', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    $yesterday = now()->subDay()->startOfDay();

    for ($i = 0; $i < 8; $i++) {
        MonitorCheck::factory()->create([
            'monitor_id' => $monitor->id,
            'checked_at' => $yesterday->copy()->setTime(10, $i * 5),
            'status' => MonitorStatus::Up,
            'response_time_ms' => 100 + $i,
        ]);
    }

    for ($i = 0; $i < 2; $i++) {
        MonitorCheck::factory()->create([
            'monitor_id' => $monitor->id,
            'checked_at' => $yesterday->copy()->setTime(11, $i * 5),
            'status' => MonitorStatus::Down,
            'response_time_ms' => null,
        ]);
    }

    Artisan::call('monitors:compute-rollups', ['--date' => $yesterday->toDateString()]);

    $rollup = DailyUptimeRollup::query()
        ->where('monitor_id', $monitor->id)
        ->whereDate('date', $yesterday)
        ->first();

    expect($rollup)->not->toBeNull();
    expect($rollup->total_checks)->toBe(10);
    expect($rollup->successful_checks)->toBe(8);
    expect((float) $rollup->uptime_percentage)->toBe(80.0);
    expect($rollup->avg_response_time_ms)->toBeGreaterThan(0);
});
