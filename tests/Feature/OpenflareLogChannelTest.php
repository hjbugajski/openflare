<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * `openflare` is the default channel, so if it fails to build Laravel silently
 * swaps in the emergency logger and the stdout/stderr split that Railway (and
 * any other platform that classifies by stream) relies on is dead in
 * production. These tests assert the resulting routing, not just the wiring.
 */
function openflareHandlers(): array
{
    Log::forgetChannel('openflare');

    return Log::channel('openflare')->getLogger()->getHandlers();
}

function logRecordAt(Level $level): LogRecord
{
    return new LogRecord(new DateTimeImmutable, 'openflare', $level, 'test message');
}

test('the channel builds a stdout filter plus a stderr stream', function () {
    $handlers = openflareHandlers();

    expect($handlers)->toHaveCount(2)
        ->and($handlers[0])->toBeInstanceOf(FilterHandler::class)
        ->and($handlers[1])->toBeInstanceOf(StreamHandler::class);
});

test('info records go to stdout only', function () {
    [$stdout, $stderr] = openflareHandlers();

    expect($stdout->isHandling(logRecordAt(Level::Info)))->toBeTrue()
        ->and($stderr->isHandling(logRecordAt(Level::Info)))->toBeFalse();
});

test('error records go to stderr only', function () {
    [$stdout, $stderr] = openflareHandlers();

    expect($stderr->isHandling(logRecordAt(Level::Error)))->toBeTrue()
        ->and($stdout->isHandling(logRecordAt(Level::Error)))->toBeFalse();
});

test('building the channel does not fall back to the emergency logger', function () {
    $path = storage_path('logs/emergency-fallback-test.log');
    @unlink($path);
    config(['logging.channels.emergency.path' => $path]);

    openflareHandlers();
    $fellBack = file_exists($path);
    @unlink($path);

    expect($fellBack)->toBeFalse();
});
