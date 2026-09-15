#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
good_pid=''
bad_auth_pid=''
bad_store_pid=''
cleanup() {
  for pid in "$good_pid" "$bad_auth_pid" "$bad_store_pid"; do
    if [ -n "$pid" ]; then kill "$pid" 2>/dev/null || true; fi
  done
}
trap cleanup EXIT

wait_health() {
  local port="$1" log="$2"
  for attempt in $(seq 1 30); do
    if curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:${port}/healthz" | grep -q '^200$'; then
      return 0
    fi
    if [ "$attempt" -eq 30 ]; then
      cat "$log" >&2 2>/dev/null || true
      return 1
    fi
    sleep 1
  done
}

rm -rf /tmp/robust-ready-good
ROBUST_MCP_SESSION_DIR=/tmp/robust-ready-good \
php -S 127.0.0.1:8098 "$base_dir/server.php" >/tmp/robust-ready-good.log 2>&1 &
good_pid=$!
wait_health 8098 /tmp/robust-ready-good.log

good_ready="$(curl -sS -o /tmp/robust-ready-good.json -w '%{http_code}' http://127.0.0.1:8098/readyz)"
test "$good_ready" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/robust-ready-good.json"),true,512,JSON_THROW_ON_ERROR); if (($d["status"]??"")!=="ok" || ($d["check"]??"")!=="ready") exit(1);'
test -d /tmp/robust-ready-good

# A bad auth configuration must not kill liveness, but it must prevent readiness
# and prevent the MCP endpoint from serving traffic.
ROBUST_MCP_AUTH_MODE=manual-bearer \
php -S 127.0.0.1:8099 "$base_dir/server.php" >/tmp/robust-ready-bad-auth.log 2>&1 &
bad_auth_pid=$!
wait_health 8099 /tmp/robust-ready-bad-auth.log

bad_auth_ready="$(curl -sS -o /tmp/robust-ready-bad-auth.json -w '%{http_code}' http://127.0.0.1:8099/readyz)"
test "$bad_auth_ready" = '503'
php -r '$d=json_decode(file_get_contents("/tmp/robust-ready-bad-auth.json"),true,512,JSON_THROW_ON_ERROR); if (($d["status"]??"")!=="not_ready") exit(1); if (count($d)!==4) exit(2);'

cat > /tmp/robust-ready-discover.json <<'JSON'
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"readiness-probe","version":"1.0.0"}}}
JSON
bad_auth_mcp="$(curl -sS -o /tmp/robust-ready-bad-auth-mcp.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  --data-binary @/tmp/robust-ready-discover.json http://127.0.0.1:8099/mcp)"
test "$bad_auth_mcp" = '500'
grep -Fq 'server_configuration_error' /tmp/robust-ready-bad-auth-mcp.json

# An unusable session path is also a readiness failure. /proc cannot be used as
# an application session directory on the Linux CI runner.
ROBUST_MCP_SESSION_DIR=/proc/chattanooga-robust-mcp-session-store \
php -S 127.0.0.1:8100 "$base_dir/server.php" >/tmp/robust-ready-bad-store.log 2>&1 &
bad_store_pid=$!
wait_health 8100 /tmp/robust-ready-bad-store.log

bad_store_ready="$(curl -sS -o /tmp/robust-ready-bad-store.json -w '%{http_code}' http://127.0.0.1:8100/readyz)"
test "$bad_store_ready" = '503'
bad_store_mcp="$(curl -sS -o /tmp/robust-ready-bad-store-mcp.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  --data-binary @/tmp/robust-ready-discover.json http://127.0.0.1:8100/mcp)"
test "$bad_store_mcp" = '500'

# Readiness responses deliberately disclose no credential or filesystem detail.
if grep -Eiq 'bearer|digest|/proc|session|telemetry|ROBUST_MCP' /tmp/robust-ready-bad-auth.json /tmp/robust-ready-bad-store.json; then
  echo 'readiness endpoint leaked configuration detail' >&2
  exit 1
fi

printf '%s\n' 'robust-mcp-readiness: PASS liveness-independent readiness-probed bad-auth=503 bad-store=503 serving-fail-closed'
