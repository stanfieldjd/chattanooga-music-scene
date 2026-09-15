#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
set +e
bash "$base_dir/oauth-auth-probe.sh"
status=$?
set -e

if [ "$status" -eq 0 ]; then
  exit 0
fi

printf '%s\n' '--- OAuth red-team failure diagnostics ---' >&2
for file in /tmp/robust-oauth-idp.log /tmp/robust-oauth-mcp.log; do
  if [ -f "$file" ]; then
    printf '\n### %s ###\n' "$file" >&2
    tail -n 200 "$file" >&2 || true
  fi
done

for file in /tmp/robust-oauth-*.headers /tmp/robust-oauth-*.json; do
  if [ -f "$file" ]; then
    printf '\n### %s ###\n' "$file" >&2
    head -c 8192 "$file" >&2 || true
    printf '\n' >&2
  fi
done

exit "$status"
