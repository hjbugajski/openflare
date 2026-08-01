<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use App\Models\MonitorCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersistDailyRollups
{
    /**
     * Bulk-upsert rollup stats for a single date, deleting rows for monitors
     * absent from $stats — but only where the check log gives grounds for it.
     * Rollups are the long-term record and nothing can regenerate them once
     * the checks behind them are gone, so a missing stat is treated as missing
     * data unless both guards hold: $date falls inside the check-retention
     * window, and the monitor's earliest surviving check is at or before the
     * start of $date's window. Otherwise the absence may be pruned,
     * backfilled, or not-yet-recorded checks and the existing rollup is left
     * untouched. A monitor with no checks at all is never deleted.
     *
     * The earliest-check guard establishes only that the log reaches BACK past
     * $date — it says nothing about the far end, so a rollup dated after a
     * monitor's last check is still deletable. That is by design: a trailing
     * rollup with no in-window checks is either a stale row from a previous
     * timezone alignment (its boundary checks re-bucket to the neighbouring
     * day, so no data is lost) or an imported/seeded artifact, an accepted
     * residual. Guarding the upper bound too would break that cleanup.
     *
     * @param  Collection<int, string>  $monitorIds
     * @param  Collection<string, object>  $stats  keyed by monitor_id, as returned by ComputeRollupStats
     * @return array{created: int, updated: int}
     */
    public function handle(Collection $monitorIds, Carbon $date, Collection $stats): array
    {
        $deletable = $this->deletableMonitorIds($monitorIds, $date, $stats);

        if ($deletable->isNotEmpty()) {
            DailyUptimeRollup::query()
                ->whereIn('monitor_id', $deletable)
                ->whereDate('date', $date)
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

    /**
     * The subset of $monitorIds whose rollup for $date may be deleted: absent
     * from $stats, inside the retention window, and backed by a check log that
     * already reached back past this date. Only the lower bound is tested —
     * dates past the monitor's last check stay deletable, see handle(). One
     * grouped MIN(checked_at) covers every candidate; the
     * (monitor_id, checked_at) index serves it.
     *
     * @param  Collection<int, string>  $monitorIds
     * @param  Collection<string, object>  $stats
     * @return Collection<int, string>
     */
    private function deletableMonitorIds(Collection $monitorIds, Carbon $date, Collection $stats): Collection
    {
        if (! $this->isWithinRetentionWindow($date)) {
            return collect();
        }

        $absent = $monitorIds->diff($stats->keys())->values();

        if ($absent->isEmpty()) {
            return collect();
        }

        return MonitorCheck::query()
            ->select('monitor_id')
            ->whereIn('monitor_id', $absent)
            ->groupBy('monitor_id')
            ->havingRaw('MIN(checked_at) <= ?', [$date->copy()->startOfDay()->utc()->toDateTimeString()])
            ->pluck('monitor_id');
    }

    private function isWithinRetentionWindow(Carbon $date): bool
    {
        $retentionDays = (int) config('monitors.retention_days', 30);
        $prunedBefore = now()->subDays($retentionDays);

        return $date->copy()->startOfDay()->gte($prunedBefore);
    }
}
