<?php

declare(strict_types=1);

use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->withoutVite();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * A persisted rollup on every local day from today-30 through today-1: 30 rows,
 * one more than a 30-entry window ending on a live today may keep. The oldest
 * day is a total outage, so wrongly keeping it moves the headline percentage.
 */
function seedRollupWindow(Monitor $monitor, Carbon $today): void
{
    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => $today->copy()->subDays(30)->toDateString(),
        'total_checks' => 100,
        'successful_checks' => 0,
        'uptime_percentage' => 0,
        'avg_response_time_ms' => 100,
    ]);

    foreach (range(29, 1) as $daysAgo) {
        DailyUptimeRollup::factory()->perfect()->create([
            'monitor_id' => $monitor->id,
            'date' => $today->copy()->subDays($daysAgo)->toDateString(),
            'avg_response_time_ms' => 100,
        ]);
    }
}

/** Ten checks at $at: 8 up, 2 down. */
function seedDayChecks(Monitor $monitor, Carbon $at): void
{
    MonitorCheck::factory()->count(8)->for($monitor)->up()->checkedAt($at)->create(['response_time_ms' => 100]);
    MonitorCheck::factory()->count(2)->for($monitor)->down()->checkedAt($at)->create();
}

/**
 * @return array<int, mixed>
 */
function inertiaProp(TestResponse $response, string $key): array
{
    return json_decode(json_encode($response->viewData('page')['props'][$key]), true);
}

/**
 * @param  array<int, array<string, mixed>>  $rollups
 * @return array<int, string>
 */
function rollupDates(array $rollups): array
{
    return array_map(fn (array $rollup) => Carbon::parse($rollup['date'])->toDateString(), $rollups);
}

it('serves exactly 30 rollup entries on the monitors index', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    seedRollupWindow($monitor, Carbon::parse('2026-08-01', 'UTC'));
    seedDayChecks($monitor, Carbon::parse('2026-08-01 09:00:00', 'UTC'));

    $response = $this->actingAs($user)->get(route('monitors.index'))->assertOk();
    $dates = rollupDates(inertiaProp($response, 'monitors')[0]['daily_rollups']);

    expect($dates)->toHaveCount(30)
        ->and($dates[0])->toBe('2026-07-03')
        ->and($dates[29])->toBe('2026-08-01')
        ->and($dates)->not->toContain('2026-07-02');
});

it('serves exactly 30 rollup entries on the monitor page, ending on a live today', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    seedRollupWindow($monitor, Carbon::parse('2026-08-01', 'UTC'));
    seedDayChecks($monitor, Carbon::parse('2026-08-01 09:00:00', 'UTC'));

    $response = $this->actingAs($user)->get(route('monitors.show', $monitor))->assertOk();
    $rollups = inertiaProp($response, 'dailyRollups');
    $dates = rollupDates($rollups);

    expect($dates)->toHaveCount(30)
        ->and($dates[0])->toBe('2026-07-03')
        ->and($dates[29])->toBe('2026-08-01')
        ->and($dates)->not->toContain('2026-07-02')
        ->and($rollups[29]['total_checks'])->toBe(10)
        ->and($rollups[29]['successful_checks'])->toBe(8);
});

it('serves the same 30-day window from the api, replacing a stale persisted today row', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    seedRollupWindow($monitor, Carbon::parse('2026-08-01', 'UTC'));
    seedDayChecks($monitor, Carbon::parse('2026-08-01 09:00:00', 'UTC'));

    // What a mid-day recompute left behind for today: partial, and already stale.
    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => '2026-08-01',
        'total_checks' => 4,
        'successful_checks' => 2,
        'uptime_percentage' => 50,
        'avg_response_time_ms' => 100,
    ]);

    $rollups = $this->actingAs($user)
        ->getJson(route('api.monitors.rollups', $monitor))
        ->assertOk()
        ->json('rollups');

    expect($rollups)->toHaveCount(30)
        ->and($rollups[0]['date'])->toBe('2026-07-03')
        ->and($rollups[29]['date'])->toBe('2026-08-01')
        ->and($rollups[29]['total_checks'])->toBe(10)
        ->and($rollups[29]['successful_checks'])->toBe(8);
});

it('reports the same uptime percentage from the api summary as the web series', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    seedRollupWindow($monitor, Carbon::parse('2026-08-01', 'UTC'));
    seedDayChecks($monitor, Carbon::parse('2026-08-01 09:00:00', 'UTC'));

    $webSeries = inertiaProp(
        $this->actingAs($user)->get(route('monitors.show', $monitor))->assertOk(),
        'dailyRollups',
    );
    $summary = $this->actingAs($user)
        ->getJson(route('api.monitors.rollups', $monitor))
        ->assertOk()
        ->json('summary');

    // The web headline weights every entry by its check count
    // (resources/js/components/monitors/uptime-percentage.tsx).
    $webUptime = round(
        array_sum(array_column($webSeries, 'successful_checks'))
        / array_sum(array_column($webSeries, 'total_checks'))
        * 100,
        2,
    );

    expect($webUptime)->toBe(99.93)
        ->and($summary['uptime_percentage'])->toBe($webUptime)
        ->and($summary['total_checks'])->toBe(2910);
});

it('derives the window in the owner timezone rather than the app timezone', function () {
    // 20:00 on 2026-07-31 in Los Angeles: UTC has already rolled into 08-01, so
    // an app-timezone window would cut the local day that is still in progress.
    Carbon::setTestNow(Carbon::parse('2026-08-01 03:00:00', 'UTC'));

    $user = User::factory()->create(['preferences' => ['timezone' => 'America/Los_Angeles']]);
    $monitor = Monitor::factory()->for($user)->create();

    DailyUptimeRollup::factory()->perfect()->create([
        'monitor_id' => $monitor->id,
        'date' => '2026-07-30',
        'avg_response_time_ms' => 100,
    ]);

    // A stale persisted row for the local day in progress; the live compute replaces it.
    DailyUptimeRollup::factory()->create([
        'monitor_id' => $monitor->id,
        'date' => '2026-07-31',
        'total_checks' => 10,
        'successful_checks' => 5,
        'uptime_percentage' => 50,
        'avg_response_time_ms' => 100,
    ]);

    // Local 23:00 on 07-30 — before the local day starts.
    MonitorCheck::factory()->for($monitor)->up()->checkedAt(Carbon::parse('2026-07-31 06:00:00', 'UTC'))->create();
    // Local 01:00 and 19:00 on 07-31.
    MonitorCheck::factory()->for($monitor)->up()->checkedAt(Carbon::parse('2026-07-31 08:00:00', 'UTC'))->create();
    MonitorCheck::factory()->for($monitor)->up()->checkedAt(Carbon::parse('2026-08-01 02:00:00', 'UTC'))->create();

    $rollups = inertiaProp(
        $this->actingAs($user)->get(route('monitors.show', $monitor))->assertOk(),
        'dailyRollups',
    );

    expect(rollupDates($rollups))->toBe(['2026-07-30', '2026-07-31'])
        ->and($rollups[1]['total_checks'])->toBe(2)
        ->and($rollups[1]['successful_checks'])->toBe(2);
});
