# Openflare

A self-hostable uptime monitoring application.

[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/template/openflare?referralCode=hjbugajski)

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
