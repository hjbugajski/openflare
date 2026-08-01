<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsPaginatedResults;
use App\Http\Requests\StoreNotifierRequest;
use App\Http\Requests\TestNotifierRequest;
use App\Http\Requests\UpdateNotifierRequest;
use App\Mail\TestNotification;
use App\Models\Monitor;
use App\Models\Notifier;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class NotifierController extends Controller
{
    use SortsPaginatedResults;

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

        $notifiers = $this->finalizePage(
            $notifiersQuery->orderBy($sort, $direction),
            'notifiers.id',
            $direction,
            'notifiers_page',
        );

        return Inertia::render('notifiers/index', [
            'notifiers' => $notifiers,
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

        $webhookUrl = $notifier->getWebhookUrl();

        return Inertia::render('notifiers/edit', [
            'notifier' => $notifier,
            /*
             * The webhook URL is a bearer credential — sending it back would put
             * it in the page props, the `data-page` HTML and window.history,
             * where it outlives the session. The email address is the account
             * holder's own, so it stays editable.
             */
            'config_meta' => [
                'has_webhook_url' => filled($webhookUrl),
                'webhook_url_preview' => filled($webhookUrl) ? '…'.mb_substr($webhookUrl, -4) : null,
                'email' => $notifier->getEmail(),
            ],
            'monitors' => $monitors,
            'types' => Notifier::TYPES,
        ]);
    }

    /**
     * Absent config keys keep their stored value (the client never receives the
     * credential to resubmit), and keys the final type does not use are dropped.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function mergeNotifierConfig(Notifier $notifier, string $type, array $config): array
    {
        $keys = match ($type) {
            Notifier::TYPE_DISCORD => ['webhook_url'],
            Notifier::TYPE_EMAIL => ['email'],
        };

        return Arr::only([...($notifier->config ?? []), ...$config], $keys);
    }

    public function update(UpdateNotifierRequest $request, Notifier $notifier): RedirectResponse
    {
        $this->authorize('update', $notifier);

        $applyToAll = $request->validated('apply_to_existing', false);
        $attributes = $request->safe()->except(['monitors', 'apply_to_existing', 'excluded_monitors', 'config']);
        $type = $attributes['type'] ?? $notifier->type;

        $notifier->update([
            ...$attributes,
            'config' => $this->mergeNotifierConfig($notifier, $type, $request->validated('config', [])),
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
        } catch (RequestException $e) {
            return $this->testFailed($type, $e, 'Discord webhook returned status: '.$e->response->status());
        } catch (Throwable $e) {
            return $this->testFailed($type, $e, match ($type) {
                Notifier::TYPE_DISCORD => 'Could not reach the Discord webhook.',
                Notifier::TYPE_EMAIL => 'Could not send the test email.',
            });
        }
    }

    /**
     * The exception text can carry mailer internals or the webhook credential,
     * so it goes to the log and the browser gets a fixed message. The log
     * context stays credential-free for the same reason.
     */
    private function testFailed(string $type, Throwable $e, string $error): JsonResponse
    {
        Log::error('Notifier test failed', ['type' => $type, 'exception' => $e]);

        return response()->json([
            'success' => false,
            'error' => $error,
        ], 422);
    }

    protected function sendTestDiscord(string $webhookUrl): void
    {
        $embed = [
            'title' => 'Test Notification',
            'description' => 'This is a test notification from Openflare. Your Discord webhook is configured correctly!',
            'color' => 0x5865F2, // Discord blurple
            'timestamp' => now()->toIso8601String(),
        ];

        Http::timeout(10)->post($webhookUrl, ['embeds' => [$embed]])->throw();
    }

    protected function sendTestEmail(string $email): void
    {
        Mail::to($email)->send(new TestNotification);
    }
}
