<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The daily uptime series behind every sparkline, headline percentage and API
 * summary: the persisted rows for [today - 29, today) plus a freshly computed
 * today, so the window is at most 30 entries in ascending date order and the
 * final entry is never a stale mid-day recompute.
 *
 * Timezone contract: daily_uptime_rollups.date holds the owner-local calendar
 * date while check timestamps are UTC, so "today" — and therefore the whole
 * window — is derived in the user's timezone preference, falling back to
 * config('app.timezone').
 */
class GetMonitorRollupSeries
{
    private const DAYS = 30;

    public function __construct(
        private readonly ComputeTodayRollup $computeTodayRollup,
    ) {}

    /**
     * @param  Collection<int, string>|array<string>  $monitorIds
     * @return Collection<string, Collection<int, DailyUptimeRollup>> keyed by monitor_id
     */
    public function handle(Collection|array $monitorIds, User $user): Collection
    {
        $monitorIds = collect($monitorIds);

        if ($monitorIds->isEmpty()) {
            return collect();
        }

        $timezone = $user->getPreference('timezone', config('app.timezone'));
        $now = now($timezone);
        $today = $now->toDateString();
        $windowStart = $now->copy()->subDays(self::DAYS - 1)->toDateString();

        $persisted = DailyUptimeRollup::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('date', '>=', $windowStart)
            ->where('date', '<', $today)
            ->orderBy('date')
            ->get()
            ->groupBy('monitor_id');

        $todayRollups = $this->computeTodayRollup->handle($monitorIds, $timezone);

        return $monitorIds->mapWithKeys(function ($monitorId) use ($persisted, $todayRollups) {
            $series = $persisted->get($monitorId, collect())->values();

            if ($todayRollups->has($monitorId)) {
                $series->push($todayRollups->get($monitorId));
            }

            return [$monitorId => $series];
        });
    }
}
