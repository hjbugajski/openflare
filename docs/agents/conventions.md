# Conventions

## Backend (PHP/Laravel)

- `declare(strict_types=1);` in all PHP files
- UUIDv7 primary keys for monitor-related tables; users use an auto-increment id plus uuid
- Encrypted casts for sensitive model attributes (notifier config)
- Form Requests for validation
- Actions for complex logic
- Policies for authorization
- Queued jobs for background work (`ShouldBeUnique` for checks)

## Database

- Eloquent over raw queries
- `Model::query()` over `DB::`
- Foreign keys reference `users.uuid` for user relations

## Frontend

- TypeScript strict mode
- Zod schemas for form validation (`resources/js/lib/schemas/`)
- Types in `resources/js/types/index.ts`
- `@laravel/echo-react` for WebSocket subscriptions: `useEcho()`
- Tailwind v4 CSS-first configuration
- Dark mode via `dark:` prefix
- Lowercase text throughout UI (stylistic choice)
