#!/usr/bin/env bash
#
# Sentrix realtime health check.
#
# Reverb + Horizon run under the container's supervisor automatically (see
# docker/supervisord.conf), so you no longer start them by hand. This checks
# they're up.
#
#   ./bin/realtime.sh
set -uo pipefail

cd "$(dirname "$0")/.."
SAIL="./vendor/bin/sail"

echo "→ Supervisor programs:"
if ! $SAIL exec -T laravel.test supervisorctl status 2>/dev/null; then
  echo "  (supervisorctl unavailable — falling back to a process check)"
  $SAIL exec -T laravel.test sh -c \
    'ps ax | grep -E "artisan (reverb:start|horizon)" | grep -v grep' 2>/dev/null \
    || echo "  (no reverb/horizon processes found — is the container up?)"
fi

echo
echo "→ Reverb reachability (expect HTTP 404 = up):"
curl -s -o /dev/null -w "  http://localhost:8080 -> %{http_code}\n" http://localhost:8080 \
  || echo "  (no response on :8080 — Reverb not reachable)"
