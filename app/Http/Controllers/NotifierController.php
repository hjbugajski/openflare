<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsCursorPaginatedResults;
use App\Http\Requests\StoreNotifierRequest;
use App\Http\Requests\TestNotifierRequest;
use App\Http\Requests\UpdateNotifierRequest;
use App\Mail\TestNotification;
use App\Models\Monitor;
use App\Models\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class NotifierController extends Controller
{
    use SortsCursorPaginatedResults;

    public function index(): Response
    {
        [$sort, $direction] = $this->resolveSort('sort', 'direction', [
            'name' => 'name',
            'status' => 'is_active',
            'type' => 'type',
            'default' => 'is_default',
            'monitors_count' => 'monitors_count',
            'excluded' => 'excluded_monitors_count',
        ], 'name', 'asc');

        $notifiersQuery = Notifier::query()
            ->where('user_id', Auth::user()->uuid)
            ->withCount('monitors')
            ->withCount([
                'monitors as excluded_monitors_count' => fn ($query) => $query->where('monitor_notifier.is_excluded', true),
            ]);
        $notifiersTotal = (clone $notifiersQuery)->count();

        $notifiers = $this->finalizeCursorPage(
            $notifiersQuery->orderBy($sort, $direction),
            'notifiers.id',
            $direction,
            'notifiers_cursor',
        );

        return Inertia::render('notifiers/index', [
            'notifiers' => array_merge($notifiers->toArray(), ['total' => $notifiersTotal]),
            'types' => Notifier::TYPES,
        ]);
    }

    public function create(): Response
    {
        $monitors = Monitor::query()
            ->where('user_id', Auth::user()->uuid)
            ->get(['id', 'name', 'url']);

        return Inertia::render('notifiers/create', [
            'monitors' => $monitors,
            'types' => Notifier::TYPES,
        ]);
    }

    private function syncMonitorAttachments(Notifier $notifier, bool $applyToAll, Collection $monitorIds, Collection $excludedMonitorIds, bool $shouldSyncMonitors): void
    {
        if ($applyToAll) {
            $allMonitorIds = Monitor::where('user_id', Auth::user()->uuid)->pluck('id');
            $syncData = $allMonitorIds->mapWithKeys(fn (string $id) => [
                $id => ['is_excluded' => $excludedMonitorIds->contains($id)],
            ]);
            $notifier->monitors()->sync($syncData);
        } elseif ($shouldSyncMonitors) {
            $notifier->monitors()->sync($monitorIds);
        }
    }

    public function store(StoreNotifierRequest $request): RedirectResponse
    {
        $this->authorize('create', Notifier::class);

        $applyToAll = $request->validated('apply_to_existing', false);

        $notifier = Notifier::create([
            'user_id' => Auth::user()->uuid,
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'config' => $request->validated('config'),
            'is_active' => $request->validated('is_active', true),
            'is_default' => $request->validated('is_default', false),
            'apply_to_all' => $applyToAll,
        ]);

        $monitorIds = collect($request->validated('monitors', []));
        $this->syncMonitorAttachments(
            $notifier,
            $applyToAll,
            $monitorIds,
            collect($request->validated('excluded_monitors', [])),
            $monitorIds->isNotEmpty(),
        );

        return redirect()->route('notifiers.index')
            ->with('success', 'Notifier created successfully.');
    }

    public function edit(Notifier $notifier): Response
    {
        $this->authorize('update', $notifier);

        $notifier->load('monitors:id,name,url');

        $monitors = Monitor::query()
            ->where('user_id', Auth::user()->uuid)
            ->get(['id', 'name', 'url']);

        return Inertia::render('notifiers/edit', [
            'notifier' => $notifier,
            'monitors' => $monitors,
            'types' => Notifier::TYPES,
        ]);
    }

    public function update(UpdateNotifierRequest $request, Notifier $notifier): RedirectResponse
    {
        $this->authorize('update', $notifier);

        $applyToAll = $request->validated('apply_to_existing', false);

        $notifier->update([
            ...$request->safe()->except(['monitors', 'apply_to_existing', 'excluded_monitors']),
            'apply_to_all' => $applyToAll,
        ]);

        $this->syncMonitorAttachments(
            $notifier,
            $applyToAll,
            collect($request->validated('monitors', [])),
            collect($request->validated('excluded_monitors', [])),
            $request->has('monitors'),
        );

        return redirect()->route('notifiers.index')
            ->with('success', 'Notifier updated successfully.');
    }

    public function destroy(Notifier $notifier): RedirectResponse
    {
        $this->authorize('delete', $notifier);

        $notifier->delete();

        return redirect()->route('notifiers.index')
            ->with('success', 'Notifier deleted successfully.');
    }

    public function toggle(Notifier $notifier): RedirectResponse
    {
        $this->authorize('update', $notifier);

        $notifier->update(['is_active' => ! $notifier->is_active]);

        $status = $notifier->is_active ? 'enabled' : 'disabled';

        return redirect()->back()
            ->with('success', "Notifier {$status} successfully.");
    }

    public function test(TestNotifierRequest $request): JsonResponse
    {
        $type = $request->validated('type');
        $config = $request->validated('config');

        try {
            match ($type) {
                Notifier::TYPE_DISCORD => $this->sendTestDiscord($config['webhook_url']),
                Notifier::TYPE_EMAIL => $this->sendTestEmail($config['email']),
            };

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    protected function sendTestDiscord(string $webhookUrl): void
    {
        $embed = [
            'title' => 'Test Notification',
            'description' => 'This is a test notification from Openflare. Your Discord webhook is configured correctly!',
            'color' => 0x5865F2, // Discord blurple
            'timestamp' => now()->toIso8601String(),
        ];

        $response = Http::timeout(10)->post($webhookUrl, ['embeds' => [$embed]]);

        if ($response->failed()) {
            throw new \Exception('Discord webhook returned status: '.$response->status());
        }
    }

    protected function sendTestEmail(string $email): void
    {
        Mail::to($email)->send(new TestNotification);
    }
}
