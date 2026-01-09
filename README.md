# openflare

self-hostable uptime monitoring.

i built openflare because i wanted a straightforward uptime tool that was easy to self-host. inspired by [uptime kuma](https://github.com/louislam/uptime-kuma) and [openstatus](https://www.openstatus.dev/), openflare focuses on the essentials. this project also served as an experiment in agentic programming; it was written almost entirely by claude opus 4.5 using [opencode](https://opencode.ai/) and the [laravel boost mcp](https://laravel.com/ai/boost). laravel's full-stack capabilities and first-party mcp support made it a natural fit for exploring ai-native development workflows.

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

| variable                | description                         | default            |
| ----------------------- | ----------------------------------- | ------------------ |
| `APP_KEY`               | encryption key (required)           | -                  |
| `APP_URL`               | public url                          | `http://localhost` |
| `DB_CONNECTION`         | database driver (`sqlite`, `pgsql`) | `sqlite`           |
| `REVERB_HOST`           | websocket hostname                  | `localhost`        |
| `REVERB_PORT`           | websocket port                      | `8080`             |
| `REVERB_SCHEME`         | websocket protocol                  | `http`             |
| `MAIL_MAILER`           | mail driver (`log`, `resend`)       | `log`              |
| `MONITORS_MAX_PER_USER` | maximum monitors per user           | `100`              |

see [.env.example](.env.example) for all options.
