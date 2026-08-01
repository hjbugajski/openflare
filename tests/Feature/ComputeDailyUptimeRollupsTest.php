<?php

declare(strict_types=1);

use App\Actions\RecomputeUserRollups;
use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\MonitorStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

afterEach(fn () => Carbon::setTestNow());

it('preserves a rollup row for a date older than retention with zero checks that day', function () {
    config(['monitors.retention_days' => 30]);

    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    $oldDate = now()->subDays(60)->startOfDay();

    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => $oldDate->toDateString(),
        'total_checks' => 10,
        'successful_checks' => 10,
    ]);

    // No checks for $oldDate: within-retention would delete this as stale,
    // but the checks were pruned long ago, so it must survive.
    Artisan::call('monitors:compute-rollups', ['--date' => $oldDate->toDateString()]);

    expect(DailyUptimeRollup::query()
        ->where('monitor_id', $monitor->id)
        ->whereDate('date', $oldDate)
        ->exists())->toBeTrue();
});

it('still deletes a stale rollup row for a genuinely zero-check date within retention', function () {
    config(['monitors.retention_days' => 30]);

    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    $recentDate = now()->subDays(5)->startOfDay();

    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => $recentDate->toDateString(),
        'total_checks' => 10,
        'successful_checks' => 10,
    ]);

    Artisan::call('monitors:compute-rollups', ['--date' => $recentDate->toDateString()]);

    expect(DailyUptimeRollup::query()
        ->where('monitor_id', $monitor->id)
        ->whereDate('date', $recentDate)
        ->exists())->toBeFalse();
});

it('upserts distinct stats per monitor without cross-contaminating rows', function () {
    $user = User::factory()->create();
    $monitorA = Monitor::factory()->for($user)->create();
    $monitorB = Monitor::factory()->for($user)->create();
    $date = now()->subDay()->startOfDay();

    for ($i = 0; $i < 4; $i++) {
        MonitorCheck::factory()->create([
            'monitor_id' => $monitorA->id,
            'checked_at' => $date->copy()->addHours($i + 1),
            'status' => MonitorStatus::Up,
        ]);
    }

    for ($i = 0; $i < 2; $i++) {
        MonitorCheck::factory()->create([
            'monitor_id' => $monitorB->id,
            'checked_at' => $date->copy()->addHours($i + 1),
            'status' => $i === 0 ? MonitorStatus::Up : MonitorStatus::Down,
        ]);
    }

    Artisan::call('monitors:compute-rollups', ['--date' => $date->toDateString()]);

    $rollupA = DailyUptimeRollup::query()->where('monitor_id', $monitorA->id)->whereDate('date', $date)->first();
    $rollupB = DailyUptimeRollup::query()->where('monitor_id', $monitorB->id)->whereDate('date', $date)->first();

    expect($rollupA)->not->toBeNull();
    expect($rollupA->total_checks)->toBe(4);
    expect($rollupA->successful_checks)->toBe(4);
    expect($rollupA->uptime_percentage)->toEqual(100.0);

    expect($rollupB)->not->toBeNull();
    expect($rollupB->total_checks)->toBe(2);
    expect($rollupB->successful_checks)->toBe(1);
    expect($rollupB->uptime_percentage)->toEqual(50.0);
});

it('derives the same day window as the per-user recompute for a non-UTC owner', function () {
    // 2026-03-14 in America/New_York (UTC-4) is 04:00Z that day through
    // 03:59Z the next, so it and the UTC day disagree on two of these checks.
    Carbon::setTestNow(Carbon::parse('2026-03-15 12:00:00', 'UTC'));

    $user = User::factory()->create(['preferences' => ['timezone' => 'America/New_York']]);
    $monitor = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->for($monitor)->down()->checkedAt(Carbon::parse('2026-03-14 02:00:00', 'UTC'))->create();
    MonitorCheck::factory()->for($monitor)->up()->checkedAt(Carbon::parse('2026-03-14 12:00:00', 'UTC'))->create();
    MonitorCheck::factory()->for($monitor)->up()->checkedAt(Carbon::parse('2026-03-15 02:00:00', 'UTC'))->create();

    $runCommand = fn () => Artisan::call('monitors:compute-rollups', ['--date' => '2026-03-14']);
    $runRecompute = fn () => app(RecomputeUserRollups::class)->handle($user->fresh(), 'America/New_York', 2);
    $rollup = fn () => DailyUptimeRollup::query()
        ->where('monitor_id', $monitor->id)
        ->whereDate('date', '2026-03-14')
        ->first()
        ?->only(['total_checks', 'successful_checks', 'uptime_percentage']);

    $runCommand();
    $runRecompute();
    $commandThenRecompute = $rollup();

    $runRecompute();
    $runCommand();
    $recomputeThenCommand = $rollup();

    // The owner's timezone decides the window, whichever writer ran last.
    expect($commandThenRecompute)->toEqual($recomputeThenCommand);
    expect($commandThenRecompute['total_checks'])->toBe(2);
    expect($commandThenRecompute['successful_checks'])->toBe(2);
    expect((float) $commandThenRecompute['uptime_percentage'])->toBe(100.0);
});

it('keeps using the app timezone for owners without a timezone preference', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-15 12:00:00', 'UTC'));

    $user = User::factory()->create(['preferences' => null]);
    $monitor = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->for($monitor)->down()->checkedAt(Carbon::parse('2026-03-14 02:00:00', 'UTC'))->create();
    MonitorCheck::factory()->for($monitor)->up()->checkedAt(Carbon::parse('2026-03-14 12:00:00', 'UTC'))->create();
    MonitorCheck::factory()->for($monitor)->up()->checkedAt(Carbon::parse('2026-03-15 02:00:00', 'UTC'))->create();

    Artisan::call('monitors:compute-rollups', ['--date' => '2026-03-14']);

    $rollup = DailyUptimeRollup::query()
        ->where('monitor_id', $monitor->id)
        ->whereDate('date', '2026-03-14')
        ->first();

    expect($rollup->total_checks)->toBe(2);
    expect((float) $rollup->uptime_percentage)->toBe(50.0);
});
