<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

class RecomputeAllUserRollups
{
    public function __construct(
        private readonly RecomputeUserRollups $recomputeUserRollups,
    ) {}

    public function handle(): void
    {
        User::query()->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $timezone = $user->getPreference('timezone');

                if (! $timezone || $timezone === config('app.timezone')) {
                    continue;
                }

                $lastTimezone = $user->getPreference('timezone_rollups_timezone');
                $lastRanAt = $user->getPreference('timezone_rollups_ran_at');

                if ($lastTimezone === $timezone && $lastRanAt) {
                    continue;
                }

                $this->recomputeUserRollups->handle($user, $timezone);
            }
        }, 'id');
    }
}
