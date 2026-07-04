<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function (User $user, string $userId) {
    return (string) $user->uuid === $userId;
});
