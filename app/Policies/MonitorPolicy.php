<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;

class MonitorPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Monitor $monitor): bool
    {
        return (string) $user->uuid === (string) $monitor->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Monitor $monitor): bool
    {
        return (string) $user->uuid === (string) $monitor->user_id;
    }

    public function delete(User $user, Monitor $monitor): bool
    {
        return (string) $user->uuid === (string) $monitor->user_id;
    }
}
