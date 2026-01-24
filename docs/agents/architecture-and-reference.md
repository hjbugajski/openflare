# Architecture and Reference

## Queue Architecture

Three queues used:

1. `monitors` - Check jobs (high priority)
2. `notifications` - Email/Discord notifications
3. `default` - Other background jobs

Worker command (current order): `--queue=default,monitors,notifications --tries=3 --timeout=120` (order defines priority)

## Database Schema (Key Tables)

```
monitors
  id (uuid), user_id (fk users.uuid), name, url, method, interval, timeout,
  expected_status_code, failure_confirmation_threshold, recovery_confirmation_threshold,
  is_active, last_checked_at, next_check_at

monitor_checks
  id (uuid), monitor_id, status (up/down), status_code, response_time_ms,
  error_message, checked_at

incidents
  id (uuid), monitor_id, started_at, ended_at, cause

daily_uptime_rollups
  id (uuid), monitor_id, date, total_checks, successful_checks,
  uptime_percentage, avg/min/max_response_time_ms

notifiers
  id (uuid), user_id, type (discord/email), name, config (json),
  is_active, is_default, apply_to_all

monitor_notifier (pivot)
  monitor_id, notifier_id, is_excluded
```

## API Endpoints (Session Auth + Verified)

GET /api/monitors/{monitor} # Monitor summary
GET /api/monitors/{monitor}/checks # Recent checks (limit 100)
GET /api/monitors/{monitor}/rollups # 30-day rollup data

## Environment Variables

Essential (see `.env.example`):

- `APP_KEY` - Encryption key (required)
- `APP_URL` - Application URL
- `APP_ENV` - Environment (local/production)
- `APP_DEBUG` - Debug mode
- `MAIL_MAILER` - Email driver (log/resend)
- `MAIL_FROM_ADDRESS` - Sender email
- `RESEND_API_KEY` - Resend API key (if using resend mailer)
- `REVERB_APP_KEY`, `REVERB_APP_SECRET` - WebSocket auth
- `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT` - Reverb server binding overrides (client connection derived from `APP_URL`)

Optional monitor settings:

- `MONITORS_TEST_MODE` - Disable actual HTTP checks
- `MONITORS_DISPATCH_LIMIT` - Max checks per scheduler run
- `MONITORS_USER_AGENT` - User-Agent for check requests
- `MONITORS_RETENTION_DAYS` - Days to keep check history
- `MONITORS_MAX_PER_USER` - Max monitors per user
- `MONITORS_FAILURE_CONFIRMATION_THRESHOLD` - Consecutive down checks to open incident
- `MONITORS_RECOVERY_CONFIRMATION_THRESHOLD` - Consecutive up checks to resolve incident

## Key Files Reference

- Monitor check logic: `app/Jobs/CheckMonitor.php`
- Notification dispatch: `app/Jobs/SendMonitorNotification.php`
- Scheduler commands: `routes/console.php`
- Broadcast channels: `routes/channels.php`
- WebSocket events: `app/Events/Monitor*.php`, `app/Events/Incident*.php`
- API endpoints: `app/Http/Controllers/Api/MonitorController.php`
- TypeScript types: `resources/js/types/index.ts`
- Form schemas: `resources/js/lib/schemas/`
- Monitor config: `config/monitors.php`

## Common Patterns

### Creating a new model check

```php
MonitorCheck::factory()->up()->create(['monitor_id' => $monitor->id]);
MonitorCheck::factory()->down()->create(['monitor_id' => $monitor->id]);
```

### Testing job dispatch

```php
Bus::fake([SendMonitorNotification::class]);
// ... action that dispatches job
Bus::assertDispatched(SendMonitorNotification::class, fn ($job) =>
    $job->monitor->id === $monitor->id
);
```

### Real-time subscriptions (React)

```tsx
useEcho<MonitorCheckedEvent>(`monitors.${monitor.id}`, '.monitor.checked', (event) => {
  /* handle update */
});
```
