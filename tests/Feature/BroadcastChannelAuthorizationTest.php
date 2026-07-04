<?php

use App\Events\IncidentOpened;
use App\Events\IncidentResolved;
use App\Events\MonitorChecked;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

it('authorizes users channel for the matching user', function () {
    $user = User::factory()->create();

    $channels = app('Illuminate\Broadcasting\BroadcastManager')->getChannels();
    $callback = $channels['users.{userId}'];

    $authorized = $callback($user, (string) $user->uuid);

    expect($authorized)->toBeTrue();
});

it('denies users channel for a different user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $channels = app('Illuminate\Broadcasting\BroadcastManager')->getChannels();
    $callback = $channels['users.{userId}'];

    $authorized = $callback($user, (string) $otherUser->uuid);

    expect($authorized)->toBeFalse();
});

it('broadcasts monitor and incident events on the owning user\'s private channel', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);
    $check = MonitorCheck::factory()->create(['monitor_id' => $monitor->id]);
    $incident = Incident::factory()->create(['monitor_id' => $monitor->id]);

    expect((new MonitorChecked($monitor, $check))->broadcastOn())
        ->toEqual([new PrivateChannel('users.'.$monitor->user_id)]);
    expect((new IncidentOpened($monitor, $incident))->broadcastOn())
        ->toEqual([new PrivateChannel('users.'.$monitor->user_id)]);
    expect((new IncidentResolved($monitor, $incident))->broadcastOn())
        ->toEqual([new PrivateChannel('users.'.$monitor->user_id)]);
});
