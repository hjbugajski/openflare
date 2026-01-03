<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use App\Models\MonitorCheck;
use App\MonitorStatus;
use Illuminate\Support\Collection;

class ComputeTodayRollup
{
    /**
     * @param  Collection<int, string>|array<string>  $monitorIds
     * @return Collection<string, DailyUptimeRollup>
     */
    public function handle(Collection|array $monitorIds): Collection
    {
        if (empty($monitorIds)) {
            return collect();
        }

        $today = now()->toDateString();
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();

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

        return $stats->mapWithKeys(function ($stat) use ($today) {
            $uptimePercentage = $stat->total_checks > 0
                ? round(($stat->successful_checks / $stat->total_checks) * 100, 2)
                : 100.00;

            $rollup = new DailyUptimeRollup([
                'monitor_id' => $stat->monitor_id,
                'date' => $today,
                'total_checks' => (int) $stat->total_checks,
                'successful_checks' => (int) $stat->successful_checks,
                'uptime_percentage' => $uptimePercentage,
                'avg_response_time_ms' => $stat->avg_response_time_ms ? (int) round($stat->avg_response_time_ms) : null,
                'min_response_time_ms' => $stat->min_response_time_ms,
                'max_response_time_ms' => $stat->max_response_time_ms,
            ]);

            return [$stat->monitor_id => $rollup];
        });
    }
}
