<?php

use App\Models\Monitor;
use App\Models\User;

it('authorizes monitor channel for monitor owner', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'user_id' => $user->uuid,
    ]);

    $channels = app('Illuminate\Broadcasting\BroadcastManager')->getChannels();
    $callback = $channels['monitors.{monitorId}'];

    $authorized = $callback($user, $monitor->id);

    expect($authorized)->toBeTrue();
});

it('denies monitor channel for non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $monitor = Monitor::factory()->create([
        'user_id' => $owner->uuid,
    ]);

    $channels = app('Illuminate\Broadcasting\BroadcastManager')->getChannels();
    $callback = $channels['monitors.{monitorId}'];

    $authorized = $callback($otherUser, $monitor->id);

    expect($authorized)->toBeFalse();
});

it('denies monitor channel for non-existent monitor', function () {
    $user = User::factory()->create();

    $channels = app('Illuminate\Broadcasting\BroadcastManager')->getChannels();
    $callback = $channels['monitors.{monitorId}'];

    $authorized = $callback($user, 'non-existent-id');

    expect($authorized)->toBeFalse();
});
