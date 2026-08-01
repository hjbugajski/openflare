<?php

declare(strict_types=1);

use App\Actions\RecomputeUserRollups;
use App\Jobs\RecomputeUserRollupsJob;
use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => Carbon::setTestNow());

it('deletes no rollups when the monitor has no checks at all', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-15 12:00:00', 'UTC'));

    $user = User::factory()->create(['preferences' => ['timezone' => 'UTC']]);
    $monitor = Monitor::factory()->for($user)->create();

    foreach ([1, 2, 3] as $daysAgo) {
        DailyUptimeRollup::factory()->perfect()->create([
            'monitor_id' => $monitor->id,
            'date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    // Seeded/imported history with the checks behind it long gone: the
    // recompute finds zero checks for every date and must not touch a row.
    app(RecomputeUserRollups::class)->handle($user, 'America/Los_Angeles');

    expect(DailyUptimeRollup::query()->where('monitor_id', $monitor->id)->count())->toBe(3);
});

it('keeps rollups predating the earliest check and drops stale ones after it', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-15 12:00:00', 'UTC'));

    $user = User::factory()->create(['preferences' => ['timezone' => 'UTC']]);
    $monitor = Monitor::factory()->for($user)->create();

    // The check log begins 5 days ago; both rollup dates are inside retention.
    MonitorCheck::factory()
        ->for($monitor)
        ->checkedAt(now()->subDays(5)->startOfDay()->addHours(6))
        ->create();

    $beforeHistory = now()->subDays(8)->startOfDay();
    $afterHistory = now()->subDays(3)->startOfDay();

    foreach ([$beforeHistory, $afterHistory] as $date) {
        DailyUptimeRollup::factory()->perfect()->create([
            'monitor_id' => $monitor->id,
            'date' => $date->toDateString(),
        ]);
    }

    app(RecomputeUserRollups::class)->handle($user, 'UTC');

    $exists = fn (Carbon $date) => DailyUptimeRollup::query()
        ->where('monitor_id', $monitor->id)
        ->whereDate('date', $date)
        ->exists();

    expect($exists($beforeHistory))->toBeTrue();
    expect($exists($afterHistory))->toBeFalse();
});

it('queues the recompute on a timezone change instead of running it inline', function () {
    Queue::fake();

    $user = User::factory()->create(['preferences' => ['timezone' => 'UTC']]);

    $this->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), ['timezone' => 'America/Los_Angeles'])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(
        RecomputeUserRollupsJob::class,
        fn (RecomputeUserRollupsJob $job) => $job->user->is($user)
    );

    // markRollupsRun is the recompute's last act; unset proves it never ran.
    expect($user->refresh()->getPreference('timezone_rollups_ran_at'))->toBeNull();
});

it('does not queue a recompute when the timezone is unchanged', function () {
    Queue::fake();

    $user = User::factory()->create(['preferences' => ['timezone' => 'UTC']]);

    $this->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), ['timezone' => 'UTC'])
        ->assertSessionHasNoErrors();

    Queue::assertNothingPushed();
});

it('resolves the timezone when the job runs, not when it is dispatched', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-15 12:00:00', 'UTC'));

    $user = User::factory()->create(['preferences' => ['timezone' => 'UTC']]);
    Monitor::factory()->for($user)->create();

    $job = new RecomputeUserRollupsJob($user);

    // The preference moves on while the job sits in the queue. $job->user still
    // holds the dispatch-time instance, so only a handle-time read sees this.
    $later = User::query()->find($user->getKey());
    $later->setPreference('timezone', 'America/Los_Angeles');
    $later->save();

    app()->call([$job, 'handle']);

    expect($user->refresh()->getPreference('timezone_rollups_timezone'))->toBe('America/Los_Angeles');
});
