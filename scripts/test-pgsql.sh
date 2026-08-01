#!/usr/bin/env bash
#
# Run the Pest suite against a real PostgreSQL server, fully inside Docker.
# Catches driver-specific regressions (raw SQL expressions, cursor
# pagination, index behavior) that the default in-memory SQLite lane
# cannot. The database lives on a tmpfs and is destroyed on exit.
#
# Usage: scripts/test-pgsql.sh [artisan test args...]
#   e.g. scripts/test-pgsql.sh --filter=DashboardTest

set -euo pipefail

cd "$(dirname "$0")/.."

# The container bind-mounts this checkout and installs nothing, so anything the
# suite needs must already be built on the host. Fail here with a fix instead of
# mid-suite with a stack trace that looks like a PostgreSQL regression.
if [[ ! -f vendor/autoload.php ]]; then
  echo "error: vendor/autoload.php is missing. Run 'composer install' first." >&2
  exit 1
fi

if [[ ! -f public/build/manifest.json ]]; then
  echo "error: public/build/manifest.json is missing. Run 'pnpm run build' first." >&2
  echo "       Tests that render Inertia pages without withoutVite() need the Vite manifest." >&2
  exit 1
fi

# One project per checkout: a fixed name makes a second run attach to the first
# run's database and lets either EXIT trap tear the suite down mid-flight.
# cksum is POSIX and stable, so reruns from the same path still reuse — and so
# still clean up — the same project.
project="openflare-test-pgsql-$(printf '%s' "$PWD" | cksum | cut -d' ' -f1)"

COMPOSE=(docker compose -p "$project" -f docker/testing/compose.yaml)

cleanup() {
  "${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${COMPOSE[@]}" run --build --rm test php artisan test "$@"
