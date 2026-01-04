# OpenFlare

A simple self-hostable uptime monitoring application.

This project started as a personal desire for a basic, straightforward uptime monitoring tool that I could easily self-host. While I've used and drawn inspiration from [Uptime Kuma](https://github.com/louislam/uptime-kuma) and [openstatus](https://www.openstatus.dev/), I wanted something much simpler with fewer features. Beyond solving a practical problem, this project served as an experiment in agentic development and an opportunity to understand the limits and capabilities of AI-assisted coding. It was built almost entirely (except for UI styling and UX tweaks) using Claude Opus 4.5 through [OpenCode](https://opencode.ai/) with the [Laravel Boost MCP](https://laravel.com/ai/boost), utilizing several custom primary agents and subagents. I chose Laravel for its full-stack capabilities and its first-party MCP support, making it an ideal framework for exploring agentic programming workflows.

## Features

- Monitor website uptime and response times
- Automatic incident tracking and resolution
- Discord and email notifications
- Real-time dashboard via WebSockets
- 30-day uptime history charts
- Two-factor authentication

## Tech Stack

- Laravel 12, PHP 8.4
- React 19, Inertia.js v2, TypeScript
- Tailwind CSS v4
- SQLite (MySQL/PostgreSQL compatible)
- Laravel Reverb (WebSockets)

## Quick Start

### Development

```bash
composer run setup
composer run dev
```

### Docker

```bash
docker run -d \
  -p 8000:8000 \
  -p 8080:8080 \
  -e APP_KEY=base64:$(openssl rand -base64 32) \
  -v openflare-data:/app/database \
  ghcr.io/hjbugajski/openflare:latest
```

Open http://localhost:8000 to get started.

### Docker Compose

```yaml
services:
  openflare:
    image: ghcr.io/hjbugajski/openflare:latest
    ports:
      - '8000:8000'
      - '8080:8080'
    environment:
      - APP_KEY=base64:your-generated-key
      - APP_URL=https://your-domain.com
      - REVERB_HOST=your-domain.com
      - REVERB_PORT=443
      - REVERB_SCHEME=https
    volumes:
      - openflare-data:/app/database

volumes:
  openflare-data:
```

## Environment Variables

| Variable         | Description                   | Default            |
| ---------------- | ----------------------------- | ------------------ |
| `APP_KEY`        | Encryption key (required)     | -                  |
| `APP_URL`        | Public URL                    | `http://localhost` |
| `REVERB_HOST`    | WebSocket hostname            | `localhost`        |
| `REVERB_PORT`    | WebSocket port                | `8080`             |
| `REVERB_SCHEME`  | WebSocket protocol            | `http`             |
| `MAIL_MAILER`    | Mail driver (`log`, `resend`) | `log`              |
| `RESEND_API_KEY` | Resend API key                | -                  |

Generate `APP_KEY`:

```bash
openssl rand -base64 32 | sed 's/^/base64:/'
```

See [.env.example](.env.example) for all options.
