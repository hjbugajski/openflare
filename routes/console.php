<?php

use App\Jobs\CheckMonitor;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $maxPerRun = (int) config('monitors.dispatch_limit', 500);
    $dispatched = 0;
    $skipped = 0;

    Monitor::query()
        ->where('is_active', true)
        ->where(function ($query) {
            $query->whereNull('next_check_at')
                ->orWhere('next_check_at', '<=', now());
        })
        ->select(['id', 'user_id', 'name', 'url', 'method', 'interval', 'timeout', 'expected_status_code', 'is_active', 'last_checked_at', 'next_check_at'])
        ->chunkById(100, function ($monitors) use ($maxPerRun, &$dispatched, &$skipped) {
            foreach ($monitors as $monitor) {
                if ($dispatched >= $maxPerRun) {
                    $skipped++;

                    continue;
                }

                CheckMonitor::dispatch($monitor);
                $dispatched++;
            }

            return $dispatched < $maxPerRun;
        });

    if ($dispatched > 0 || $skipped > 0) {
        Log::info('Monitor checks dispatched', [
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);
    }
})->everyMinute()
    ->name('dispatch-monitor-checks')
    ->withoutOverlapping(5);

Schedule::call(function () {
    $retentionDays = (int) config('monitors.retention_days', 30);
    $deleted = MonitorCheck::query()
        ->where('checked_at', '<', now()->subDays($retentionDays))
        ->delete();

    if ($deleted > 0) {
        Log::info('Pruned old monitor checks', ['deleted' => $deleted]);
    }
})->daily()->name('prune-monitor-checks');

Schedule::command('monitors:compute-rollups')
    ->dailyAt('00:15')
    ->name('compute-daily-uptime-rollups')
    ->withoutOverlapping();
