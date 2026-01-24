# Project

Self-hostable uptime monitoring application.

## Core Domain

- `Monitor` - HTTP endpoint to check (URL, method, interval, expected status)
- `MonitorCheck` - Individual check result (status, response time, error)
- `Incident` - Downtime period (started_at, ended_at, cause)
- `DailyUptimeRollup` - Aggregated daily statistics for 30-day charts
- `Notifier` - Discord webhook or email notification destination
- `User` - Account with monitors and notifiers

## Key Flows

1. Scheduler dispatches `CheckMonitor` jobs every minute for due monitors (schedule without overlapping + job uniqueness)
2. Job performs HTTP request, records check, creates/resolves incidents after confirmation thresholds
3. Status changes trigger `SendMonitorNotification` jobs
4. `MonitorChecked`, `IncidentOpened`, `IncidentResolved` events broadcast via Reverb
5. Frontend subscribes to WebSocket events for real-time updates

Monitor checks block private/link-local IP ranges and disallow redirects.

## Tech Stack

- Backend: Laravel 12, PHP 8.4
- Frontend: React 19, Inertia v2, TypeScript
- Styling: Tailwind CSS v4
- Auth: Fortify (2FA, 12+ char secure pass)
- WebSockets: Laravel Reverb
- Queue: Database driver
- Database: SQLite (default), PostgreSQL supported
- Testing: Pest v4
- Routing: Laravel Wayfinder (type-safe routes)
- Security: Headers middleware, rate limiting
