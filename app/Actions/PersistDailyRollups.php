<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersistDailyRollups
{
    /**
     * Bulk-upsert rollup stats for a single date, deleting stale rows for
     * monitors absent from $stats. Stale rows are only deleted when $date
     * falls within the check-retention window (see plan 018) — outside it,
     * an absent stat may simply mean checks were pruned, not that uptime
     * was genuinely zero, so existing rollups are left untouched.
     *
     * @param  Collection<int, string>  $monitorIds
     * @param  Collection<string, object>  $stats  keyed by monitor_id, as returned by ComputeRollupStats
     * @return array{created: int, updated: int}
     */
    public function handle(Collection $monitorIds, Carbon $date, Collection $stats): array
    {
        if ($this->isWithinRetentionWindow($date)) {
            DailyUptimeRollup::query()
                ->whereIn('monitor_id', $monitorIds)
                ->whereDate('date', $date)
                ->whereNotIn('monitor_id', $stats->keys())
                ->delete();
        }

        if ($stats->isEmpty()) {
            return ['created' => 0, 'updated' => 0];
        }

        $existingMonitorIds = DailyUptimeRollup::query()
            ->whereIn('monitor_id', $stats->keys())
            ->whereDate('date', $date)
            ->pluck('monitor_id');

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

        return [
            'created' => $stats->keys()->diff($existingMonitorIds)->count(),
            'updated' => $stats->keys()->intersect($existingMonitorIds)->count(),
        ];
    }

    private function isWithinRetentionWindow(Carbon $date): bool
    {
        $retentionDays = (int) config('monitors.retention_days', 30);
        $prunedBefore = now()->subDays($retentionDays);

        return $date->copy()->startOfDay()->gte($prunedBefore);
    }
}
