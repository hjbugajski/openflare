<?php

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

it('deletes stale rows in batches and keeps recent rows', function () {
    $monitor = Monitor::factory()->create();

    MonitorCheck::factory()->count(2500)->for($monitor)->checkedAt(now()->subDays(60))->create();
    MonitorCheck::factory()->count(5)->for($monitor)->checkedAt(now()->subDay())->create();

    Artisan::call('monitors:prune-checks');

    expect(MonitorCheck::query()->where('checked_at', '<', now()->subDays(30))->count())->toBe(0);
    expect(MonitorCheck::count())->toBe(5);
});

it('logs the total deleted count across multiple batches', function () {
    $monitor = Monitor::factory()->create();

    MonitorCheck::factory()->count(2500)->for($monitor)->checkedAt(now()->subDays(60))->create();

    Log::spy();

    Artisan::call('monitors:prune-checks');

    Log::shouldHaveReceived('info')->with('Pruned old monitor checks', ['deleted' => 2500])->once();
});

it('does not log or delete when there are no stale rows', function () {
    $monitor = Monitor::factory()->create();

    MonitorCheck::factory()->count(5)->for($monitor)->checkedAt(now()->subDay())->create();

    Log::spy();

    Artisan::call('monitors:prune-checks');

    Log::shouldNotHaveReceived('info');
    expect(MonitorCheck::count())->toBe(5);
});

it('respects the configured retention_days', function () {
    Config::set('monitors.retention_days', 5);

    $monitor = Monitor::factory()->create();

    $stale = MonitorCheck::factory()->for($monitor)->checkedAt(now()->subDays(10))->create();
    $recent = MonitorCheck::factory()->for($monitor)->checkedAt(now()->subDays(3))->create();

    Artisan::call('monitors:prune-checks');

    expect(MonitorCheck::query()->find($stale->id))->toBeNull();
    expect(MonitorCheck::query()->find($recent->id))->not->toBeNull();
});
