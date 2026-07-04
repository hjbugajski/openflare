<?php

declare(strict_types=1);

use App\Actions\RecomputeAllUserRollups;
use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

it('skips app-default-timezone users', function () {
    $user = User::factory()->create(['preferences' => ['timezone' => config('app.timezone')]]);

    app(RecomputeAllUserRollups::class)->handle();

    expect($user->refresh()->getPreference('timezone_rollups_ran_at'))->toBeNull();
});

it('recomputes a non-default-timezone user that never ran', function () {
    $user = User::factory()->create(['preferences' => ['timezone' => 'America/Los_Angeles']]);
    $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);

    MonitorCheck::factory()->for($monitor)->checkedAt(now())->create();

    app(RecomputeAllUserRollups::class)->handle();

    $user->refresh();
    expect($user->getPreference('timezone_rollups_ran_at'))->not->toBeNull();
    expect($user->getPreference('timezone_rollups_timezone'))->toBe('America/Los_Angeles');
});

it('skips a user already recomputed earlier today', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 20:00:00', 'America/Los_Angeles'));

    $user = User::factory()->create(['preferences' => [
        'timezone' => 'America/Los_Angeles',
        'timezone_rollups_timezone' => 'America/Los_Angeles',
        'timezone_rollups_ran_at' => '2026-01-10 04:00:00',
    ]]);
    $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);

    DailyUptimeRollup::factory()->forDate('2026-01-10')->create([
        'monitor_id' => $monitor->id,
        'total_checks' => 5,
        'successful_checks' => 5,
        'uptime_percentage' => 100.00,
    ]);

    app(RecomputeAllUserRollups::class)->handle();

    expect($user->refresh()->getPreference('timezone_rollups_ran_at'))->toBe('2026-01-10 04:00:00');
    expect(
        DailyUptimeRollup::query()
            ->where('monitor_id', $monitor->id)
            ->whereDate('date', '2026-01-10')
            ->first()
            ->total_checks
    )->toBe(5);
});

it('recomputes a user last run yesterday', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 20:00:00', 'America/Los_Angeles'));

    $user = User::factory()->create(['preferences' => [
        'timezone' => 'America/Los_Angeles',
        'timezone_rollups_timezone' => 'America/Los_Angeles',
        'timezone_rollups_ran_at' => '2026-01-09 04:00:00',
    ]]);
    $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);

    MonitorCheck::factory()->for($monitor)->checkedAt(now())->create();

    app(RecomputeAllUserRollups::class)->handle();

    $ranAt = $user->refresh()->getPreference('timezone_rollups_ran_at');
    expect($ranAt)->not->toBe('2026-01-09 04:00:00');
    expect(Carbon::parse($ranAt, 'America/Los_Angeles')->toDateString())->toBe('2026-01-10');
});

it('recomputes when the timezone changed since the last run', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 20:00:00', 'America/New_York'));

    $user = User::factory()->create(['preferences' => [
        'timezone' => 'America/New_York',
        'timezone_rollups_timezone' => 'America/Los_Angeles',
        'timezone_rollups_ran_at' => now('America/Los_Angeles')->toDateTimeString(),
    ]]);
    $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);

    MonitorCheck::factory()->for($monitor)->checkedAt(now())->create();

    app(RecomputeAllUserRollups::class)->handle();

    expect($user->refresh()->getPreference('timezone_rollups_timezone'))->toBe('America/New_York');
});
