<?php

declare(strict_types=1);

use App\Actions\GetStatusToolbarSummary;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->monitor = Monitor::factory()->create([
        'user_id' => $this->user->uuid,
        'is_active' => true,
    ]);
});

test('status toolbar summary is operational by default', function () {
    $summary = app(GetStatusToolbarSummary::class)->forUser($this->user);

    expect($summary)->toMatchArray([
        'state' => 'operational',
        'totalMonitors' => 1,
        'activeMonitors' => 1,
        'activeIncidentCount' => 0,
        'recentFailureCount' => 0,
    ]);
});

test('status toolbar summary is degraded with recent failures', function () {
    MonitorCheck::factory()
        ->down()
        ->checkedAt(now()->subMinutes(5))
        ->create(['monitor_id' => $this->monitor->id]);

    $summary = app(GetStatusToolbarSummary::class)->forUser($this->user);

    expect($summary['state'])->toBe('degraded')
        ->and($summary['recentFailureCount'])->toBe(1)
        ->and($summary['activeIncidentCount'])->toBe(0);
});

test('status toolbar summary is incident when an incident is active', function () {
    Incident::factory()
        ->ongoing()
        ->create(['monitor_id' => $this->monitor->id]);

    $summary = app(GetStatusToolbarSummary::class)->forUser($this->user);

    expect($summary['state'])->toBe('incident')
        ->and($summary['activeIncidentCount'])->toBe(1);
});
