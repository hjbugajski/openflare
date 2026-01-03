<?php

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('monitors.{monitorId}', function (User $user, string $monitorId) {
    $monitor = Monitor::find($monitorId);

    return $monitor && $monitor->user_id === (string) $user->uuid;
});
