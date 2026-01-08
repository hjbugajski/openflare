# openflare

self-hostable uptime monitoring.

## features

- monitor website uptime and response times
- automatic incident tracking and resolution
- discord and email notifications
- real-time dashboard via websockets
- 30-day uptime history charts
- two-factor authentication
- secure by default (security headers, encrypted config, rate limiting)

## tech stack

- laravel 12, php 8.4
- react 19, inertia.js v2, typescript
- tailwind css v4
- sqlite or postgresql
- laravel reverb (websockets)

## deployment

### development

```bash
composer run setup
composer run dev
```

### docker (simple)

single container with sqlite:

```bash
docker run -d \
  -p 8000:8000 \
  -p 8080:8080 \
  -e APP_KEY=base64:$(openssl rand -base64 32) \
  -v openflare-data:/app/data \
  ghcr.io/hjbugajski/openflare:latest
```

open http://localhost:8000 to get started.

### docker compose with sqlite

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
      - openflare-data:/app/data

volumes:
  openflare-data:
```

### railway

deploy using the "majestic monolith" architecture. see `railway.toml` and `railway/` directory for configuration.

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
