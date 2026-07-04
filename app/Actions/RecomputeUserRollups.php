<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Monitor;
use App\Models\User;

class RecomputeUserRollups
{
    public function __construct(
        private readonly ComputeRollupStats $computeRollupStats,
        private readonly PersistDailyRollups $persistDailyRollups,
    ) {}

    public function handle(User $user, string $timezone, int $days = 30): void
    {
        $monitorIds = Monitor::query()
            ->where('user_id', $user->uuid)
            ->pluck('id');

        if ($monitorIds->isEmpty()) {
            $this->markRollupsRun($user, $timezone);

            return;
        }

        $now = now()->setTimezone($timezone);

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $now->copy()->subDays($offset)->startOfDay();
            $startOfDay = $date->copy()->startOfDay()->utc();
            $endOfDay = $date->copy()->endOfDay()->utc();

            $stats = $this->computeRollupStats->handle($monitorIds, $startOfDay, $endOfDay);

            $this->persistDailyRollups->handle($monitorIds, $date, $stats);
        }

        $this->markRollupsRun($user, $timezone);
    }

    private function markRollupsRun(User $user, string $timezone): void
    {
        $user->setPreference('timezone_rollups_timezone', $timezone);
        $user->setPreference('timezone_rollups_ran_at', now($timezone)->toDateTimeString());
        $user->save();
    }
}
