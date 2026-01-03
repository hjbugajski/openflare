# Openflare Agent Guidelines

Self-hostable uptime monitoring application. Laravel 12 backend, React 19 + Inertia v2 frontend, SQLite database.

## Project Overview

**Purpose**: Monitor website uptime, track response times, manage incidents, send notifications when status changes.

**Core Domain**:

- `Monitor` - HTTP endpoint to check (URL, method, interval, expected status)
- `MonitorCheck` - Individual check result (status, response time, error)
- `Incident` - Downtime period (started_at, ended_at, cause)
- `DailyUptimeRollup` - Aggregated daily statistics for 30-day charts
- `Notifier` - Discord webhook or email notification destination
- `User` - Account with monitors and notifiers

**Key Flows**:

1. Scheduler dispatches `CheckMonitor` jobs every minute for due monitors
2. Job performs HTTP request, records check, creates/resolves incidents
3. Status changes trigger `SendMonitorNotification` jobs
4. `MonitorChecked`, `IncidentOpened`, `IncidentResolved` events broadcast via Reverb
5. Frontend subscribes to WebSocket events for real-time updates

## Tech Stack

| Layer      | Technology                           |
| ---------- | ------------------------------------ |
| Backend    | Laravel 12, PHP 8.4                  |
| Frontend   | React 19, Inertia v2, TypeScript     |
| Styling    | Tailwind CSS v4                      |
| Auth       | Laravel Fortify (2FA supported)      |
| WebSockets | Laravel Reverb                       |
| Queue      | Database driver                      |
| Database   | SQLite (MySQL/Postgres compatible)   |
| Testing    | Pest v4                              |
| Routing    | Laravel Wayfinder (type-safe routes) |

## Directory Structure

```
app/
  Actions/          # Reusable business logic (rollup computation)
  Console/Commands/ # Artisan commands (auto-registered)
  Events/           # Broadcast events (MonitorChecked, Incident*)
  Http/
    Controllers/    # Web & API controllers
    Middleware/     # Appearance, Inertia handlers
    Requests/       # Form request validation
  Jobs/             # CheckMonitor, SendMonitorNotification
  Mail/             # Mailable classes
  Models/           # Eloquent models (UUID primary keys)
  Observers/        # MonitorObserver
  Policies/         # Authorization policies
  Providers/        # Service providers
  Rules/            # Custom validation rules
  MonitorStatus.php # Up/Down enum

resources/js/
  components/       # Reusable React components
    ui/             # Base UI components (Button, Card, Dialog, etc.)
    icons/          # SVG icon components
  layouts/          # AppLayout, AuthLayout
  lib/              # Utilities, schemas (Zod), constants
  pages/            # Inertia page components
  types/            # TypeScript interfaces

tests/
  Feature/          # Integration tests (controllers, jobs, events)
  Unit/             # Unit tests
```

## Conventions

### PHP/Laravel

- `declare(strict_types=1);` at top of every PHP file
- PHP 8 constructor property promotion
- Explicit return type declarations
- PHPDoc for complex methods, no inline comments unless necessary
- Model UUIDs via `uuid7()` in `creating` event
- Casts defined in `casts()` method, not `$casts` property
- Form Request classes for validation (check sibling files for array vs string rules)
- Actions for complex business logic
- Policies for authorization
- Queued jobs for background work

### Database

- Eloquent over raw queries
- `Model::query()` over `DB::`
- Eager loading to prevent N+1
- Foreign keys reference `users.uuid` for user relations

### Frontend

- TypeScript strict mode
- Zod schemas for form validation (`resources/js/lib/schemas/`)
- Types in `resources/js/types/index.ts`
- `@laravel/echo-react` for WebSocket subscriptions: `useEcho()`
- Tailwind v4 CSS-first configuration
- Dark mode via `dark:` prefix
- Lowercase text throughout UI (stylistic choice)

### Testing

- Pest v4 for all tests
- Feature tests for controllers, jobs, events
- Factories for model creation in tests
- `Http::fake()` and `Http::preventStrayRequests()` for HTTP mocking
- `Bus::fake()`, `Event::fake()` for asserting dispatches
- Datasets for validation rule tests

## Key Files Reference

| Purpose               | File(s)                                               |
| --------------------- | ----------------------------------------------------- |
| Monitor check logic   | `app/Jobs/CheckMonitor.php`                           |
| Notification dispatch | `app/Jobs/SendMonitorNotification.php`                |
| Scheduler commands    | `routes/console.php`                                  |
| Broadcast channels    | `routes/channels.php`                                 |
| WebSocket events      | `app/Events/Monitor*.php`, `app/Events/Incident*.php` |
| API endpoints         | `app/Http/Controllers/Api/MonitorController.php`      |
| TypeScript types      | `resources/js/types/index.ts`                         |
| Form schemas          | `resources/js/lib/schemas/`                           |
| Monitor config        | `config/monitors.php`                                 |

## Commands

```bash
# Development
composer run dev              # Start all services (server, queue, scheduler, Vite, Reverb)

# Testing
php artisan test              # All tests
php artisan test --filter=X   # Filtered tests
vendor/bin/pint --dirty       # Format changed PHP files

# Queue
php artisan queue:work --queue=default,monitors,notifications

# Manual rollups
php artisan app:compute-daily-uptime-rollups
```

## Queue Architecture

Three queues by priority:

1. `monitors` - Check jobs (high priority)
2. `notifications` - Email/Discord notifications
3. `default` - Other background jobs

Worker command: `--queue=default,monitors,notifications --tries=3 --timeout=120`

## Database Schema (Key Tables)

```
monitors
  id (uuid), user_id (fk users.uuid), name, url, method, interval, timeout,
  expected_status_code, is_active, last_checked_at, next_check_at

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
  monitor_id, notifier_id
```

## API Endpoints (Session Auth)

```
GET /api/monitors/{monitor}         # Monitor summary
GET /api/monitors/{monitor}/checks  # Recent checks (limit 100)
GET /api/monitors/{monitor}/rollups # 30-day rollup data
```

## Environment Variables

Key variables (see `.env.example`):

- `MONITORS_TEST_MODE` - Disable actual HTTP checks
- `MONITORS_DISPATCH_LIMIT` - Max checks per scheduler run
- `MONITORS_USER_AGENT` - User-Agent header for checks
- `REVERB_*` - WebSocket configuration
- `VITE_REVERB_*` - Frontend WebSocket connection

## Development Workflow

1. Create feature branch
2. Write/update tests first (TDD encouraged)
3. Implement feature following existing patterns
4. Run `vendor/bin/pint --dirty`
5. Run relevant tests: `php artisan test --filter=FeatureName`
6. Run full suite before PR: `php artisan test`

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
