<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules the dispatch command every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->firstWhere('description', 'dispatch-monitor-checks');

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('* * * * *');
    expect($event->command)->toContain('monitors:dispatch-checks');
    expect($event->withoutOverlapping)->toBeTrue();
});
