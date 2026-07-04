<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\MonitorCheck;
use App\MonitorStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ComputeRollupStats
{
    /**
     * Aggregate monitor check stats for the given monitors within
     * [$startOfDay, $endOfDay]. Monitors with zero checks in the window are
     * absent from the returned collection (SQL GROUP BY only returns groups
     * that exist) — callers that persist rollups must treat "absent" as
     * "delete any existing rollup row for this monitor/date".
     *
     * @param  Collection<int, string>|array<string>  $monitorIds
     * @return Collection<string, object> keyed by monitor_id, each value has
     *                                    total_checks, successful_checks, uptime_percentage, avg_response_time_ms,
     *                                    min_response_time_ms, max_response_time_ms
     */
    public function handle(Collection|array $monitorIds, Carbon $startOfDay, Carbon $endOfDay): Collection
    {
        if (empty($monitorIds)) {
            return collect();
        }

        return MonitorCheck::query()
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
            ->get()
            ->mapWithKeys(function ($stat) {
                $stat->total_checks = (int) $stat->total_checks;
                $stat->successful_checks = (int) $stat->successful_checks;
                // total_checks is always >= 1 here: GROUP BY guarantees each
                // row represents at least one matching check, so this never
                // divides by zero.
                $stat->uptime_percentage = round(($stat->successful_checks / $stat->total_checks) * 100, 2);
                $stat->avg_response_time_ms = $stat->avg_response_time_ms !== null ? (int) round($stat->avg_response_time_ms) : null;
                $stat->min_response_time_ms = $stat->min_response_time_ms !== null ? (int) $stat->min_response_time_ms : null;
                $stat->max_response_time_ms = $stat->max_response_time_ms !== null ? (int) $stat->max_response_time_ms : null;

                return [$stat->monitor_id => $stat];
            });
    }
}
