<?php

declare(strict_types=1);

use App\Actions\BackfillMissingRollups;
use App\Actions\RecomputeAllUserRollups;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;

function bindRollupActions(int $backfillCalls, int $recomputeCalls, ?Throwable $backfillThrows = null): void
{
    $backfill = Mockery::mock(BackfillMissingRollups::class);
    $expectation = $backfill->shouldReceive('handle')->times($backfillCalls);

    if ($backfillThrows !== null) {
        $expectation->andThrow($backfillThrows);
    }

    $recompute = Mockery::mock(RecomputeAllUserRollups::class);
    $recompute->shouldReceive('handle')->times($recomputeCalls);

    app()->instance(BackfillMissingRollups::class, $backfill);
    app()->instance(RecomputeAllUserRollups::class, $recompute);
}

function runStartupRollupMaintenance(): void
{
    app()->getProvider(AppServiceProvider::class)->runStartupRollupMaintenance();
}

it('runs rollup maintenance at most once per day across console boots', function () {
    bindRollupActions(backfillCalls: 1, recomputeCalls: 1);

    runStartupRollupMaintenance();
    runStartupRollupMaintenance();
    runStartupRollupMaintenance();
});

it('releases the guard when maintenance fails so the next boot retries', function () {
    bindRollupActions(backfillCalls: 2, recomputeCalls: 0, backfillThrows: new RuntimeException('transient'));

    runStartupRollupMaintenance();

    expect(Cache::has('startup:rollup-maintenance'))->toBeFalse();

    runStartupRollupMaintenance();
});
