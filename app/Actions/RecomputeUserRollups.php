<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Str;

class RecomputeUserRollups
{
    public function __construct(
        private readonly ComputeRollupStats $computeRollupStats,
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

            // Zero-check policy (see plan 017): delete any existing rollup
            // row for monitors absent from $stats for this date. whereNotIn
            // with an empty collection matches every row, so this single
            // query also covers the "no monitor had any checks" case.
            DailyUptimeRollup::query()
                ->whereIn('monitor_id', $monitorIds)
                ->whereDate('date', $date)
                ->whereNotIn('monitor_id', $stats->keys())
                ->delete();

            if ($stats->isEmpty()) {
                continue;
            }

            $rows = $stats->map(fn ($stat) => [
                'id' => (string) Str::uuid7(),
                'monitor_id' => $stat->monitor_id,
                'date' => $date->toDateString(),
                'total_checks' => $stat->total_checks,
                'successful_checks' => $stat->successful_checks,
                'uptime_percentage' => $stat->uptime_percentage,
                'avg_response_time_ms' => $stat->avg_response_time_ms,
                'min_response_time_ms' => $stat->min_response_time_ms,
                'max_response_time_ms' => $stat->max_response_time_ms,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->all();

            DailyUptimeRollup::query()->upsert(
                $rows,
                ['monitor_id', 'date'],
                ['total_checks', 'successful_checks', 'uptime_percentage', 'avg_response_time_ms', 'min_response_time_ms', 'max_response_time_ms', 'updated_at']
            );
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
