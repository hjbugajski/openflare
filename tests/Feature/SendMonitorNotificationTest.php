<?php

declare(strict_types=1);

use App\Jobs\SendMonitorNotification;
use App\Mail\MonitorStatusChanged;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\MonitorStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
});

it('sends discord notification when monitor goes down', function () {
    Http::fake([
        'discord.com/*' => Http::response(null, 204),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create(['name' => 'Test Monitor']));
    $check = MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'error_message' => 'Connection timeout',
    ]);
    $notifier = Notifier::factory()->discord()->create([
        'config' => ['webhook_url' => 'https://discord.com/api/webhooks/123/abc'],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Down);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/123/abc'
            && str_contains($request->body(), 'Monitor Down')
            && str_contains($request->body(), 'Test Monitor');
    });
});

it('sends discord notification when monitor recovers', function () {
    Http::fake([
        'discord.com/*' => Http::response(null, 204),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create(['name' => 'Test Monitor']));
    $check = MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
    ]);
    $notifier = Notifier::factory()->discord()->create([
        'config' => ['webhook_url' => 'https://discord.com/api/webhooks/123/abc'],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Up);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/123/abc'
            && str_contains($request->body(), 'Monitor Up')
            && str_contains($request->body(), 'Test Monitor');
    });
});

it('sends email notification when monitor goes down', function () {
    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create(['name' => 'Test Monitor']));
    $check = MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
    ]);
    $notifier = Notifier::factory()->email()->create([
        'config' => ['email' => 'user@example.com'],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Down);

    Mail::assertSent(MonitorStatusChanged::class, function ($mail) {
        return $mail->hasTo('user@example.com')
            && $mail->status === MonitorStatus::Down;
    });
});

it('sends email notification when monitor recovers', function () {
    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create(['name' => 'Test Monitor']));
    $check = MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
    ]);
    $notifier = Notifier::factory()->email()->create([
        'config' => ['email' => 'user@example.com'],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Up);

    Mail::assertSent(MonitorStatusChanged::class, function ($mail) {
        return $mail->hasTo('user@example.com')
            && $mail->status === MonitorStatus::Up;
    });
});

it('does not send discord notification when webhook url is missing', function () {
    Http::fake();

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create());
    $check = MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
    ]);
    $notifier = Notifier::factory()->discord()->create([
        'config' => [],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Down);

    Http::assertNothingSent();
});

it('does not send email notification when email is missing', function () {
    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create());
    $check = MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
    ]);
    $notifier = Notifier::factory()->email()->create([
        'config' => [],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Down);

    Mail::assertNothingSent();
});

it('includes error message in discord notification when down', function () {
    Http::fake([
        'discord.com/*' => Http::response(null, 204),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create());
    $check = MonitorCheck::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'error_message' => 'Server returned 503',
    ]);
    $notifier = Notifier::factory()->discord()->create([
        'config' => ['webhook_url' => 'https://discord.com/api/webhooks/123/abc'],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Down);

    Http::assertSent(function ($request) {
        return str_contains($request->body(), 'Server returned 503');
    });
});

it('includes response time in discord notification when up', function () {
    Http::fake([
        'discord.com/*' => Http::response(null, 204),
    ]);

    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create());
    $check = MonitorCheck::factory()->up()->create([
        'monitor_id' => $monitor->id,
        'response_time_ms' => 150,
    ]);
    $notifier = Notifier::factory()->discord()->create([
        'config' => ['webhook_url' => 'https://discord.com/api/webhooks/123/abc'],
    ]);

    SendMonitorNotification::dispatchSync($monitor, $check, $notifier, MonitorStatus::Up);

    Http::assertSent(function ($request) {
        return str_contains($request->body(), '150ms');
    });
});
