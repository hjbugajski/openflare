# Testing and Workflow

## Testing

- Pest v4 for all tests
- Feature tests for controllers, jobs, events
- Factories for model creation in tests
- `Http::fake()` and `Http::preventStrayRequests()` for HTTP mocking
- `Bus::fake()`, `Event::fake()` for asserting dispatches
- Datasets for validation rule tests

## Commands

- `composer run dev` - Start all services (server, queue, scheduler, Vite, Reverb)
- `composer run lint` - Format PHP files (Pint)
- `composer run lint:check` - Check PHP formatting
- `composer run test` - Clear config and run all tests
- `php artisan test` - All tests
- `php artisan test --filter=X` - Filtered tests
- `vendor/bin/pint --dirty` - Format changed PHP files
- `pnpm run lint` - Lint frontend
- `pnpm run lint:fix` - Lint and fix frontend
- `pnpm run format` - Format frontend
- `pnpm run format:check` - Check frontend formatting
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
