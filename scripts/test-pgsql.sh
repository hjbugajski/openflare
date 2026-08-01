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

COMPOSE=(docker compose -f docker/testing/compose.yaml)

cleanup() {
  "${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${COMPOSE[@]}" run --build --rm test php artisan test "$@"
