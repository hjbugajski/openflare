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

        $sort = request()->string('sort', 'started_at')->toString();
        $direction = strtolower(request()->string('direction', 'desc')->toString());
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $sortOptions = ['monitor', 'status', 'cause', 'duration', 'started_at', 'ended_at'];
        $sort = in_array($sort, $sortOptions, true) ? $sort : 'started_at';

        $incidentsQuery = Incident::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', $sevenDaysAgo)
            ->with('monitor:id,name,url');

        if ($sort === 'monitor') {
            $incidentsQuery->orderBy(
                Monitor::query()->select('name')->whereColumn('monitors.id', 'incidents.monitor_id'),
                $direction,
            );
        } elseif ($sort === 'status') {
            $incidentsQuery->orderByRaw("ended_at is null {$direction}");
        } elseif ($sort === 'duration') {
            $incidentsQuery->orderByRaw(
                "(julianday(coalesce(ended_at, CURRENT_TIMESTAMP)) - julianday(started_at)) {$direction}",
            );
        } else {
            $incidentsQuery->orderBy($sort, $direction);
        }

        $incidents = $incidentsQuery
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dashboard/index', [
            'counts' => $counts,
            'incidents' => $incidents,
            'monitorIds' => $monitorIds->values(),
        ]);
    }
}
