<?php

declare(strict_types=1);

use App\Jobs\CheckMonitor;
use App\Models\Monitor;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the database queue retry_after above CheckMonitor\'s job timeout', function () {
    $retryAfter = config('queue.connections.database.retry_after');
    $checkMonitorTimeout = (new CheckMonitor(new Monitor))->timeout;

    expect($retryAfter)->toBeGreaterThan($checkMonitorTimeout);
});
