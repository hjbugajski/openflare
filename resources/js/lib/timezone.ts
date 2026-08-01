/**
 * Resolves the timezone that uptime rollups are rendered in.
 *
 * Daily rollups are bucketed server-side in the user's saved timezone, falling
 * back to `config('app.timezone')` (UTC) when they have not set one. The client
 * must use the same fallback: resolving to the browser timezone instead shifts
 * the rendered 30-day window off the rollup dates by up to a day.
 */
export function resolveRollupTimezone(preferred: string | undefined): string {
  return preferred ?? 'UTC';
}
