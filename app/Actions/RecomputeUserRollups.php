<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\MonitorStatus;

class RecomputeUserRollups
{
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
            $dateValue = $date->copy();
            $startOfDay = $date->copy()->startOfDay()->utc()->toDateTimeString();
            $endOfDay = $date->copy()->endOfDay()->utc()->toDateTimeString();

            $stats = MonitorCheck::query()
                ->whereIn('monitor_id', $monitorIds)
                ->whereBetween('checked_at', [$startOfDay, $endOfDay])
                ->groupBy('monitor_id')
                ->selectRaw('
                    monitor_id,
                    COUNT(*) as total_checks,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful_checks,
                    AVG(response_time_ms) as avg_response_time_ms,
                    MIN(response_time_ms) as min_response_time_ms,
                    MAX(response_time_ms) as max_response_time_ms
                ', [MonitorStatus::Up->value])
                ->get();

            $monitorsWithStats = $stats->pluck('monitor_id');

            if ($monitorsWithStats->isEmpty()) {
                DailyUptimeRollup::query()
                    ->whereIn('monitor_id', $monitorIds)
                    ->whereDate('date', $dateValue)
                    ->delete();
            } else {
                DailyUptimeRollup::query()
                    ->whereIn('monitor_id', $monitorIds)
                    ->whereDate('date', $dateValue)
                    ->whereNotIn('monitor_id', $monitorsWithStats)
                    ->delete();
            }

            foreach ($stats as $stat) {
                if ($stat->total_checks === 0) {
                    continue;
                }

                $uptimePercentage = $stat->total_checks > 0
                    ? round(($stat->successful_checks / $stat->total_checks) * 100, 2)
                    : 100.00;

                $rollup = DailyUptimeRollup::query()
                    ->where('monitor_id', $stat->monitor_id)
                    ->whereDate('date', $dateValue)
                    ->first();

                if ($rollup === null) {
                    $rollup = new DailyUptimeRollup([
                        'monitor_id' => $stat->monitor_id,
                        'date' => $dateValue,
                    ]);
                }

                $rollup->fill([
                    'total_checks' => $stat->total_checks,
                    'successful_checks' => $stat->successful_checks,
                    'uptime_percentage' => $uptimePercentage,
                    'avg_response_time_ms' => $stat->avg_response_time_ms
                        ? (int) round($stat->avg_response_time_ms)
                        : null,
                    'min_response_time_ms' => $stat->min_response_time_ms,
                    'max_response_time_ms' => $stat->max_response_time_ms,
                ]);
                $rollup->save();
            }
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
