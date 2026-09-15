#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
endpoint='http://127.0.0.1:8098/mcp'
sessions='/tmp/robust-mcp-contract-sessions'
rm -rf "$sessions"
mkdir -p "$sessions"

ROBUST_MCP_SESSION_DIR="$sessions" \
php -S 127.0.0.1:8098 "$base_dir/server.php" >/tmp/robust-mcp-contract-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  code="$(curl -sS -o /tmp/robust-contract-ready.json -w '%{http_code}' 'http://127.0.0.1:8098/readyz' || true)"
  if [ "$code" = '200' ]; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-mcp-contract-http.log >&2
    cat /tmp/robust-contract-ready.json >&2 2>/dev/null || true
    exit 1
  fi
  sleep 1
done

MCP_ENDPOINT="$endpoint" node "$base_dir/tool-contract-probe.mjs"

printf '%s\n' 'robust-mcp-chatgpt-contract: PASS frozen-snapshot gate=exact-reviewed-contract'
