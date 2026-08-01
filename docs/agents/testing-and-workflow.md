# Testing and Workflow

## Testing

- Pest v4 for all tests
- Feature tests for controllers, jobs, events
- Factories for model creation in tests
- `Http::fake()` and `Http::preventStrayRequests()` for HTTP mocking
- `Bus::fake()`, `Event::fake()` for asserting dispatches
- Datasets for validation rule tests
- Frontend: vitest + happy-dom, compiled with the React Compiler (vitest.config.ts) so component tests match production memoization; `resources/js/test/compiler-check.test.tsx` guards that parity
- `scripts/test-pgsql.sh` runs the Pest suite against an ephemeral PostgreSQL container (requires Docker) — use it for anything touching raw SQL, casts, or migrations. It bind-mounts this checkout and installs nothing, so `composer install` and `pnpm run build` must have run on the host first (the script fails fast if they have not). CI covers the same driver in its `test-postgres` job, but by a different route: host PHP against a `postgres` service container, not `docker/testing/*`. `tests/Feature/DatabaseDriverTest.php` asserts the suite really is on the driver `DB_CONNECTION` asks for, so a lost env block fails instead of silently falling back to SQLite.

## Environment gotchas

- `.env` ships without `APP_ENV`, so bare `php artisan ...` boots as production and the Reverb-credential guard refuses. Prefix ad hoc artisan commands with `APP_ENV=local`.
- Prefixing the test runner with `APP_ENV=local` no longer matters: phpunit.xml sets `APP_ENV=testing` with `force="true"`, so the shell value cannot win and POST tests cannot regress to 419. `DB_*` is deliberately left unforced so CI's PostgreSQL job can override it.

## Commands

- `composer run dev` - Start all services (server, queue, scheduler, Vite, Reverb)
- `composer run lint` - Format PHP files (Pint)
- `composer run lint:check` - Check PHP formatting
- `composer run test` - Clear config and run all tests
- `php artisan test` - All tests
- `php artisan test --filter=X` - Filtered tests
- `vendor/bin/pint --dirty` - Format changed PHP files
- `pnpm run lint` - Lint and fix frontend (oxlint, fix-mode)
- `pnpm run lint:check` - Check frontend lint (no fixes)
- `pnpm run fmt` - Format frontend
- `pnpm run fmt:check` - Check frontend formatting
- `pnpm run typecheck` - Type check frontend
- `php artisan queue:work --queue=default,monitors,notifications`
- `php artisan monitors:compute-rollups`

## Development Workflow

1. Create feature branch
2. Write/update tests first
3. Implement feature following existing patterns
4. Run `vendor/bin/pint --dirty`
5. Run relevant tests: `php artisan test --filter=FeatureName`
6. Run full suite before PR: `php artisan test`
