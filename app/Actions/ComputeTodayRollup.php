<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use Illuminate\Support\Collection;

class ComputeTodayRollup
{
    public function __construct(
        private readonly ComputeRollupStats $computeRollupStats,
    ) {}

    /**
     * @param  Collection<int, string>|array<string>  $monitorIds
     * @return Collection<string, DailyUptimeRollup>
     */
    public function handle(Collection|array $monitorIds, ?string $timezone = null): Collection
    {
        if (empty($monitorIds)) {
            return collect();
        }

        $timezone = $timezone ?? config('app.timezone');
        $now = now($timezone);
        $today = $now->toDateString();
        $startOfDay = $now->copy()->startOfDay()->utc();
        $endOfDay = $now->copy()->endOfDay()->utc();

        return $this->computeRollupStats
            ->handle($monitorIds, $startOfDay, $endOfDay)
            ->map(fn ($stat) => new DailyUptimeRollup([
                'monitor_id' => $stat->monitor_id,
                'date' => $today,
                'total_checks' => $stat->total_checks,
                'successful_checks' => $stat->successful_checks,
                'uptime_percentage' => $stat->uptime_percentage,
                'avg_response_time_ms' => $stat->avg_response_time_ms,
                'min_response_time_ms' => $stat->min_response_time_ms,
                'max_response_time_ms' => $stat->max_response_time_ms,
            ]));
    }
}
