<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ComputeTodayRollup;
use App\Http\Requests\StoreMonitorRequest;
use App\Http\Requests\UpdateMonitorRequest;
use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    public function __construct(
        private ComputeTodayRollup $computeTodayRollup,
    ) {}

    public function index(): Response
    {
        $monitors = Monitor::query()
            ->where('user_id', Auth::user()->uuid)
            ->with(['latestCheck', 'currentIncident'])
            ->withCount('checks')
            ->latest()
            ->get();

        $monitorIds = $monitors->pluck('id');
        $today = now()->toDateString();
        $thirtyDaysAgo = now()->subDays(30)->toDateString();

        // Get historical rollups (excluding today)
        $rollups = DailyUptimeRollup::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('date', '<', $today)
            ->orderBy('date')
            ->get()
            ->groupBy('monitor_id');

        // Compute today's rollups on-the-fly
        $todayRollups = $this->computeTodayRollup->handle($monitorIds);

        // Attach rollups to monitors
        $monitorsWithRollups = $monitors->map(function ($monitor) use ($rollups, $todayRollups) {
            $monitorRollups = $rollups->get($monitor->id, collect())->values();

            // Append today's rollup if it exists
            if ($todayRollups->has($monitor->id)) {
                $monitorRollups = $monitorRollups->push($todayRollups->get($monitor->id));
            }

            $monitor->daily_rollups = $monitorRollups;

            return $monitor;
        });

        return Inertia::render('monitors/index', [
            'monitors' => $monitorsWithRollups,
        ]);
    }

    public function create(): Response
    {
        $notifiers = Notifier::query()
            ->where('user_id', Auth::user()->uuid)
            ->where('is_active', true)
            ->get(['id', 'name', 'type', 'is_active', 'is_default']);

        return Inertia::render('monitors/create', [
            'notifiers' => $notifiers,
            'intervals' => Monitor::INTERVALS,
            'methods' => Monitor::METHODS,
        ]);
    }

    public function store(StoreMonitorRequest $request): RedirectResponse
    {
        $this->authorize('create', Monitor::class);

        $monitor = Monitor::create([
            'user_id' => Auth::user()->uuid,
            'name' => $request->validated('name'),
            'url' => $request->validated('url'),
            'method' => $request->validated('method'),
            'interval' => $request->validated('interval'),
            'timeout' => $request->validated('timeout'),
            'expected_status_code' => $request->validated('expected_status_code'),
            'is_active' => $request->validated('is_active', true),
        ]);

        $selectedIds = collect($request->validated('notifiers', []));
        $autoAttachNotifierIds = Notifier::query()
            ->where('user_id', Auth::user()->uuid)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('is_default', true)->orWhere('apply_to_all', true))
            ->pluck('id');

        $allNotifierIds = $selectedIds->merge($autoAttachNotifierIds)->unique();

        if ($allNotifierIds->isNotEmpty()) {
            $monitor->notifiers()->sync($allNotifierIds);
        }

        return redirect()->route('monitors.show', $monitor)
            ->with('success', 'Monitor created successfully.');
    }

    public function show(Monitor $monitor): Response
    {
        $this->authorize('view', $monitor);

        $monitor->load(['latestCheck', 'currentIncident']);

        $checks = $monitor->checks()
            ->latest('checked_at')
            ->paginate(10, ['*'], 'checks_page')
            ->withQueryString();

        $incidents = $monitor->incidents()
            ->latest('started_at')
            ->paginate(10, ['*'], 'incidents_page')
            ->withQueryString();

        $notifiers = $monitor->notifiers()
            ->paginate(10, ['*'], 'notifiers_page')
            ->withQueryString();

        $today = now()->toDateString();
        $thirtyDaysAgo = now()->subDays(30)->toDateString();

        // Get historical rollups (excluding today)
        $dailyRollups = $monitor->dailyUptimeRollups()
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('date', '<', $today)
            ->orderBy('date')
            ->get()
            ->values()
            ->toArray();

        // Compute today's rollup on-the-fly
        $todayRollups = $this->computeTodayRollup->handle([$monitor->id]);
        if ($todayRollups->has($monitor->id)) {
            $dailyRollups[] = $todayRollups->get($monitor->id);
        }

        return Inertia::render('monitors/show', [
            'monitor' => $monitor,
            'checks' => $checks,
            'incidents' => $incidents,
            'notifiers' => $notifiers,
            'dailyRollups' => $dailyRollups,
        ]);
    }

    public function edit(Monitor $monitor): Response
    {
        $this->authorize('update', $monitor);

        $monitor->load('notifiers');

        $notifiers = Notifier::query()
            ->where('user_id', Auth::user()->uuid)
            ->get(['id', 'name', 'type', 'is_active', 'is_default']);

        return Inertia::render('monitors/edit', [
            'monitor' => $monitor,
            'notifiers' => $notifiers,
            'intervals' => Monitor::INTERVALS,
            'methods' => Monitor::METHODS,
        ]);
    }

    public function update(UpdateMonitorRequest $request, Monitor $monitor): RedirectResponse
    {
        $this->authorize('update', $monitor);

        DB::transaction(function () use ($request, $monitor) {
            $monitor->update($request->safe()->except('notifiers'));

            if ($request->has('notifiers')) {
                $monitor->notifiers()->sync($request->validated('notifiers'));
            }
        });

        return redirect()->route('monitors.show', $monitor)
            ->with('success', 'Monitor updated successfully.');
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        $this->authorize('delete', $monitor);

        $monitor->delete();

        return redirect()->route('monitors.index')
            ->with('success', 'Monitor deleted successfully.');
    }

    public function attachNotifier(Monitor $monitor, Notifier $notifier): RedirectResponse
    {
        $this->authorize('update', $monitor);
        $this->authorize('view', $notifier);

        $monitor->notifiers()->syncWithoutDetaching([
            $notifier->id => ['is_excluded' => false],
        ]);

        return redirect()->back()
            ->with('success', 'Notifier enabled for this monitor.');
    }

    public function detachNotifier(Monitor $monitor, Notifier $notifier): RedirectResponse
    {
        $this->authorize('update', $monitor);
        $this->authorize('view', $notifier);

        if ($notifier->apply_to_all) {
            $monitor->notifiers()->syncWithoutDetaching([
                $notifier->id => ['is_excluded' => true],
            ]);
        } else {
            $monitor->notifiers()->detach($notifier->id);
        }

        return redirect()->back()
            ->with('success', 'Notifier disabled for this monitor.');
    }
}
