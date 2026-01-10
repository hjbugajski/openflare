# openflare

self-hostable uptime monitoring.

i built openflare because i wanted a straightforward uptime tool that was easy to self-host. inspired by [uptime kuma](https://github.com/louislam/uptime-kuma) and [openstatus](https://www.openstatus.dev/), openflare focuses on the essentials. this project also served as an experiment in agentic programming; it was written almost entirely by claude opus 4.5 using [opencode](https://opencode.ai/) and the [laravel boost mcp](https://laravel.com/ai/boost), with additional assistance from openai gpt-5.2 and gpt-5.2 codex. laravel's full-stack capabilities and first-party mcp support made it a natural fit for exploring ai-native development workflows.

## features

- monitor website uptime and response times
- automatic incident tracking and resolution
- discord and email notifications
- real-time dashboard via websockets
- 30-day uptime history charts
- two-factor authentication

## tech stack

- laravel 12, php 8.4
- react 19, inertia.js v2, typescript
- tailwind css v4
- sqlite or postgresql
- laravel reverb (websockets)

## development

[installing php and the laravel installer](https://laravel.com/docs/12.x/installation).

```bash
composer run setup
composer run dev
```

## environment variables

required

- `APP_KEY` (encryption key). generate: `php artisan key:generate --show`
- `APP_URL` (public url). default: `http://localhost:8000`
- `REVERB_APP_KEY` (websocket key). generate: `openssl rand -hex 16`
- `REVERB_APP_SECRET` (websocket secret). generate: `openssl rand -hex 32`

email notifications (optional)

- `MAIL_MAILER` (`log` default, or `resend` for production)
- `MAIL_FROM_ADDRESS` (sender address)
- `RESEND_API_KEY` (required when `MAIL_MAILER=resend`)

database (optional)

- `DB_CONNECTION` (`sqlite` default, `pgsql` for postgres)
- `DB_DATABASE` (sqlite path, local `database/database.sqlite`, production `/data/database.sqlite`)
- `DATABASE_URL` (postgres connection url alternative)

see [.env.example](.env.example) for full list and deployment notes.
