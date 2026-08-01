<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitors:dispatch-checks')
    ->everyMinute()
    ->name('dispatch-monitor-checks')
    ->withoutOverlapping(5);

Schedule::command('monitors:prune-checks')
    ->daily()
    ->name('prune-monitor-checks')
    ->withoutOverlapping();

Schedule::command('monitors:compute-rollups')
    ->dailyAt('00:15')
    ->name('compute-daily-uptime-rollups')
    ->withoutOverlapping();
