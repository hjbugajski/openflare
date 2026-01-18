<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $directionKeyword = $direction === 'asc' ? 'asc' : 'desc';

        if ($sort === 'monitor') {
            $incidentsQuery->orderBy(
                Monitor::query()->select('name')->whereColumn('monitors.id', 'incidents.monitor_id'),
                $direction,
            );
        } elseif ($sort === 'status') {
            $incidentsQuery->orderByRaw('CASE WHEN ended_at IS NULL THEN 0 ELSE 1 END '.$directionKeyword);
        } elseif ($sort === 'duration') {
            $driver = DB::connection()->getDriverName();

            $durationExpression = match ($driver) {
                'sqlite' => '(julianday(COALESCE(ended_at, CURRENT_TIMESTAMP)) - julianday(started_at))',
                'pgsql' => 'EXTRACT(EPOCH FROM (COALESCE(ended_at, NOW()) - started_at))',
                'mysql' => 'TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW()))',
                default => '(COALESCE(ended_at, NOW()) - started_at)',
            };

            $incidentsQuery->orderByRaw($durationExpression.' '.$directionKeyword);
        } else {
            $incidentsQuery->orderBy($sort, $direction);
        }

        $incidentsTotal = (clone $incidentsQuery)->count();

        $incidents = $incidentsQuery
            ->orderBy('incidents.id', $direction)
            ->cursorPaginate(10, ['*'], 'incidents_cursor')
            ->withQueryString();

        return Inertia::render('dashboard/index', [
            'counts' => $counts,
            'incidents' => array_merge($incidents->toArray(), ['total' => $incidentsTotal]),
            'monitorIds' => $monitorIds->values(),
        ]);
    }
}
