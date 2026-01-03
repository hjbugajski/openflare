<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notifier;
use App\Models\User;

class NotifierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notifier $notifier): bool
    {
        return (string) $user->uuid === (string) $notifier->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Notifier $notifier): bool
    {
        return (string) $user->uuid === (string) $notifier->user_id;
    }

    public function delete(User $user, Notifier $notifier): bool
    {
        return (string) $user->uuid === (string) $notifier->user_id;
    }
}
