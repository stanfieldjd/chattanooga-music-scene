#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
endpoint='http://127.0.0.1:8103/mcp'
session_dir='/tmp/robust-mcp-concurrency-sessions'
telemetry='/tmp/robust-mcp-concurrency-telemetry.jsonl'
rm -rf "$session_dir"
rm -f "$telemetry" /tmp/robust-concurrency-*.json /tmp/robust-concurrency-*.headers /tmp/robust-concurrency-*.marker /tmp/robust-concurrency-*.log
mkdir -p "$session_dir"

PHP_CLI_SERVER_WORKERS=4 \
ROBUST_MCP_SESSION_DIR="$session_dir" \
ROBUST_MCP_SESSION_LOCK_TIMEOUT_MS='250' \
ROBUST_MCP_LOG_FILE="$telemetry" \
php -S 127.0.0.1:8103 "$base_dir/server.php" >/tmp/robust-concurrency-server.log 2>&1 &
server_pid=$!
holder_pid=''
gc_holder_pid=''
cleanup() {
  if [[ "${holder_pid:-}" =~ ^[1-9][0-9]*$ ]]; then kill "$holder_pid" 2>/dev/null || true; fi
  if [[ "${gc_holder_pid:-}" =~ ^[1-9][0-9]*$ ]]; then kill "$gc_holder_pid" 2>/dev/null || true; fi
  kill "$server_pid" 2>/dev/null || true
  pkill -P "$server_pid" 2>/dev/null || true
}
trap cleanup EXIT

for attempt in $(seq 1 30); do
  if curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:8103/readyz | grep -q '^200$'; then break; fi
  if [ "$attempt" -eq 30 ]; then cat /tmp/robust-concurrency-server.log >&2; exit 1; fi
  sleep 1
done

cat > /tmp/robust-concurrency-init.json <<'JSON'
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"concurrency-conflict-probe","version":"1.0.0"}}}
JSON
init_code="$(curl -sS -D /tmp/robust-concurrency-init.headers -o /tmp/robust-concurrency-init-response.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  --data-binary @/tmp/robust-concurrency-init.json "$endpoint")"
test "$init_code" = '200'
session_id="$(awk 'BEGIN{IGNORECASE=1} /^Mcp-Session-Id:/ {gsub(/\r/,"",$2); print $2}' /tmp/robust-concurrency-init.headers | tail -1)"
test -n "$session_id"
test -f "$session_dir/$session_id"

printf '{"jsonrpc":"2.0","method":"notifications/initialized"}' > /tmp/robust-concurrency-initialized.json
initialized_code="$(curl -sS -o /tmp/robust-concurrency-initialized-response.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H "Mcp-Session-Id: ${session_id}" -H 'MCP-Protocol-Version: 2025-11-25' \
  --data-binary @/tmp/robust-concurrency-initialized.json "$endpoint")"
test "$initialized_code" = '202'

printf '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' > /tmp/robust-concurrency-list.json

php "$base_dir/session-lock-probe.php" hold "$session_dir" "$session_id" 1200 /tmp/robust-concurrency-held.marker >/tmp/robust-concurrency-holder.log 2>&1 &
holder_pid=$!
for attempt in $(seq 1 100); do
  test -e /tmp/robust-concurrency-held.marker && break
  if [ "$attempt" -eq 100 ]; then cat /tmp/robust-concurrency-holder.log >&2; exit 1; fi
  sleep 0.02
done

start_ms="$(php -r 'echo (int) floor(microtime(true)*1000);')"
busy_code="$(curl -sS -D /tmp/robust-concurrency-busy.headers -o /tmp/robust-concurrency-busy.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H "Mcp-Session-Id: ${session_id}" -H 'MCP-Protocol-Version: 2025-11-25' \
  --data-binary @/tmp/robust-concurrency-list.json "$endpoint")"
end_ms="$(php -r 'echo (int) floor(microtime(true)*1000);')"
elapsed_ms=$((end_ms-start_ms))
test "$busy_code" = '409'
grep -Fq '"error":"session_busy"' /tmp/robust-concurrency-busy.json
grep -Eiq '^Retry-After: 1' /tmp/robust-concurrency-busy.headers
if [ "$elapsed_ms" -lt 150 ] || [ "$elapsed_ms" -gt 1000 ]; then
  echo "bounded session conflict returned in unexpected ${elapsed_ms}ms" >&2
  exit 1
fi

wait "$holder_pid"
holder_pid=''
recovered_code="$(curl -sS -o /tmp/robust-concurrency-recovered.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H "Mcp-Session-Id: ${session_id}" -H 'MCP-Protocol-Version: 2025-11-25' \
  --data-binary @/tmp/robust-concurrency-list.json "$endpoint")"
test "$recovered_code" = '200'
grep -Fq 'robust.echo' /tmp/robust-concurrency-recovered.json

# GC must skip an expired session while another process owns that session lock,
# then remove it once the lock is released.
fake_session="$(php -r 'require $argv[1]."/vendor/autoload.php"; echo Symfony\Component\Uid\Uuid::v4()->toRfc4122();' "$base_dir")"
printf '{}' > "$session_dir/$fake_session"
touch -d '2 hours ago' "$session_dir/$fake_session"
php "$base_dir/session-lock-probe.php" hold "$session_dir" "$fake_session" 1200 /tmp/robust-concurrency-gc-held.marker >/tmp/robust-concurrency-gc-holder.log 2>&1 &
gc_holder_pid=$!
for attempt in $(seq 1 100); do
  test -e /tmp/robust-concurrency-gc-held.marker && break
  if [ "$attempt" -eq 100 ]; then cat /tmp/robust-concurrency-gc-holder.log >&2; exit 1; fi
  sleep 0.02
done

php "$base_dir/session-lock-probe.php" gc "$session_dir" 60 > /tmp/robust-concurrency-gc-busy.json
test -f "$session_dir/$fake_session"
if grep -Fq "$fake_session" /tmp/robust-concurrency-gc-busy.json; then
  echo 'GC deleted a locked session' >&2
  exit 1
fi

wait "$gc_holder_pid"
gc_holder_pid=''
php "$base_dir/session-lock-probe.php" gc "$session_dir" 60 > /tmp/robust-concurrency-gc-free.json
test ! -e "$session_dir/$fake_session"
grep -Fq "$fake_session" /tmp/robust-concurrency-gc-free.json

delete_code="$(curl -sS -o /tmp/robust-concurrency-delete.json -w '%{http_code}' -X DELETE \
  -H "Mcp-Session-Id: ${session_id}" -H 'MCP-Protocol-Version: 2025-11-25' "$endpoint")"
test "$delete_code" = '200'
test ! -e "$session_dir/$session_id"

printf '%s\n' "robust-mcp-session-concurrency: PASS busy=409 bounded_ms=${elapsed_ms} recovery=200 gc-lock-aware delete=serialized"
