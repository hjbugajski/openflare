<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Incident;
use App\Models\MonitorCheck;
use App\Models\User;
use App\MonitorStatus;

class GetStatusToolbarSummary
{
    /**
     * @return array{state: string, totalMonitors: int, activeMonitors: int, activeIncidentCount: int, recentFailureCount: int}
     */
    public function forUser(User $user): array
    {
        $totalMonitors = $user->monitors()->count();
        $activeMonitors = $user->monitors()->where('is_active', true)->count();

        $activeIncidentCount = Incident::query()
            ->whereNull('ended_at')
            ->whereHas('monitor', fn ($query) => $query->where('user_id', $user->uuid))
            ->count();

        $recentFailureCount = MonitorCheck::query()
            ->where('status', MonitorStatus::Down->value)
            ->where('checked_at', '>=', now()->subMinutes(15))
            ->whereHas('monitor', fn ($query) => $query->where('user_id', $user->uuid))
            ->count();

        $state = 'operational';

        if ($activeIncidentCount > 0) {
            $state = 'incident';
        } elseif ($recentFailureCount > 0) {
            $state = 'degraded';
        }

        return [
            'state' => $state,
            'totalMonitors' => $totalMonitors,
            'activeMonitors' => $activeMonitors,
            'activeIncidentCount' => $activeIncidentCount,
            'recentFailureCount' => $recentFailureCount,
        ];
    }
}
