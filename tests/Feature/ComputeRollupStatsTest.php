<?php

use App\Actions\ComputeRollupStats;
use App\Actions\ComputeTodayRollup;
use App\Actions\RecomputeUserRollups;
use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\MonitorStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('rounds uptime percentage correctly', function (int $successful, int $total, float $expected) {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->count($successful)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Up,
    ]);
    MonitorCheck::factory()->count($total - $successful)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Down,
    ]);

    $result = app(ComputeRollupStats::class)->handle(
        [$monitor->id],
        now()->startOfDay()->utc(),
        now()->endOfDay()->utc(),
    );

    expect((float) $result->get((string) $monitor->id)->uptime_percentage)->toBe($expected);
})->with([
    '1/3' => [1, 3, 33.33],
    '2/3' => [2, 3, 66.67],
    '99/100' => [99, 100, 99.00],
]);

it('returns no entry for a monitor with zero checks in the window', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subDay(),
        'status' => MonitorStatus::Up,
    ]);

    $result = app(ComputeRollupStats::class)->handle(
        [$monitor->id],
        now()->startOfDay()->utc(),
        now()->endOfDay()->utc(),
    );

    expect($result)->toBeEmpty();
});

it('agrees across all three rollup call sites', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->count(8)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Up,
    ]);
    MonitorCheck::factory()->count(2)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Down,
    ]);

    $today = app(ComputeTodayRollup::class)->handle([$monitor->id])->first();
    expect((float) $today->uptime_percentage)->toBe(80.0);

    app(RecomputeUserRollups::class)->handle($user, config('app.timezone'), 1);
    $recomputed = DailyUptimeRollup::query()->where('monitor_id', $monitor->id)->first();
    expect((float) $recomputed->uptime_percentage)->toBe(80.0);

    DailyUptimeRollup::query()->where('monitor_id', $monitor->id)->delete();

    Artisan::call('monitors:compute-rollups', ['--date' => now()->toDateString()]);
    $commandRollup = DailyUptimeRollup::query()->where('monitor_id', $monitor->id)->first();
    expect((float) $commandRollup->uptime_percentage)->toBe(80.0);
});
