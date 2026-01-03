<?php

use App\Actions\ComputeTodayRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\MonitorStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('computes today rollup for monitor with checks', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->count(8)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Up,
        'response_time_ms' => 150,
    ]);

    MonitorCheck::factory()->count(2)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Down,
        'response_time_ms' => null,
    ]);

    $result = app(ComputeTodayRollup::class)->handle([$monitor->id]);

    expect($result)->toHaveCount(1);

    $rollup = $result->first();
    expect($rollup->total_checks)->toBe(10);
    expect($rollup->successful_checks)->toBe(8);
    expect((float) $rollup->uptime_percentage)->toBe(80.0);
    expect($rollup->avg_response_time_ms)->toBe(150);
});

it('returns empty collection for empty monitor ids', function () {
    $result = app(ComputeTodayRollup::class)->handle([]);

    expect($result)->toBeEmpty();
});

it('excludes monitors with no checks today', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    // Create check for yesterday
    MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now()->subDay(),
        'status' => MonitorStatus::Up,
    ]);

    $result = app(ComputeTodayRollup::class)->handle([$monitor->id]);

    // Monitor with no checks today should not be in result
    expect($result)->toBeEmpty();
});

it('computes rollups for multiple monitors', function () {
    $user = User::factory()->create();
    $monitor1 = Monitor::factory()->for($user)->create();
    $monitor2 = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->count(5)->create([
        'monitor_id' => $monitor1->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Up,
        'response_time_ms' => 100,
    ]);

    MonitorCheck::factory()->count(10)->create([
        'monitor_id' => $monitor2->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Up,
        'response_time_ms' => 200,
    ]);

    $result = app(ComputeTodayRollup::class)->handle([$monitor1->id, $monitor2->id]);

    expect($result)->toHaveCount(2);

    $rollup1 = $result->firstWhere('monitor_id', $monitor1->id);
    $rollup2 = $result->firstWhere('monitor_id', $monitor2->id);

    expect($rollup1->total_checks)->toBe(5);
    expect($rollup2->total_checks)->toBe(10);
});

it('calculates average response time excluding null values', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    MonitorCheck::factory()->count(4)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Up,
        'response_time_ms' => 200,
    ]);

    // Failed checks with null response time
    MonitorCheck::factory()->count(2)->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
        'status' => MonitorStatus::Down,
        'response_time_ms' => null,
    ]);

    $result = app(ComputeTodayRollup::class)->handle([$monitor->id]);
    $rollup = $result->first();

    // SQLite AVG ignores NULL values, so avg should be 200 (from successful checks only)
    expect($rollup->avg_response_time_ms)->toBe(200);
});
