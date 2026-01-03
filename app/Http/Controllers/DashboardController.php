<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $userId = Auth::user()->uuid;

        $monitors = Monitor::query()
            ->where('user_id', $userId)
            ->with('latestCheck')
            ->get();

        $counts = [
            'up' => 0,
            'down' => 0,
            'inactive' => 0,
        ];

        foreach ($monitors as $monitor) {
            if (! $monitor->is_active) {
                $counts['inactive']++;
            } elseif ($monitor->latestCheck?->status === 'up') {
                $counts['up']++;
            } elseif ($monitor->latestCheck?->status === 'down') {
                $counts['down']++;
            } else {
                $counts['inactive']++;
            }
        }

        $monitorIds = $monitors->pluck('id');
        $sevenDaysAgo = now()->subDays(7);

        $incidents = Incident::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', $sevenDaysAgo)
            ->with('monitor:id,name,url')
            ->latest('started_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dashboard/index', [
            'counts' => $counts,
            'incidents' => $incidents,
        ]);
    }
}
