#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
endpoint='http://127.0.0.1:8101/mcp'
session_dir='/tmp/robust-mcp-multiworker-sessions'
telemetry='/tmp/robust-mcp-multiworker-telemetry.jsonl'
rm -rf "$session_dir" /tmp/robust-mw-codes
rm -f "$telemetry"
mkdir -p /tmp/robust-mw-codes

PHP_CLI_SERVER_WORKERS=4 \
ROBUST_MCP_SESSION_DIR="$session_dir" \
ROBUST_MCP_LOG_FILE="$telemetry" \
php -S 127.0.0.1:8101 "$base_dir/server.php" >/tmp/robust-mcp-multiworker.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true; pkill -P "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:8101/readyz | grep -q '^200$'; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-mcp-multiworker.log >&2
    exit 1
  fi
  sleep 1
done

cat > /tmp/robust-mw-initialize.json <<'JSON'
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"multiworker-probe","version":"1.0.0"}}}
JSON
initialize_code="$(curl -sS -D /tmp/robust-mw-init-headers.txt -o /tmp/robust-mw-init.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H 'X-Request-Id: mw-init' --data-binary @/tmp/robust-mw-initialize.json "$endpoint")"
test "$initialize_code" = '200'
session_id="$(awk 'BEGIN{IGNORECASE=1} /^Mcp-Session-Id:/ {gsub(/\r/,"",$2); print $2}' /tmp/robust-mw-init-headers.txt | tail -1)"
test -n "$session_id"
test -f "$session_dir/$session_id"

printf '{"jsonrpc":"2.0","method":"notifications/initialized"}' > /tmp/robust-mw-initialized.json
initialized_code="$(curl -sS -o /tmp/robust-mw-initialized-response -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H "Mcp-Session-Id: ${session_id}" -H 'MCP-Protocol-Version: 2025-11-25' -H 'X-Request-Id: mw-initialized' \
  --data-binary @/tmp/robust-mw-initialized.json "$endpoint")"
test "$initialized_code" = '202'

printf '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' > /tmp/robust-mw-list.json
export endpoint session_id

# Concurrent connections force the kernel to distribute work across the PHP
# workers. Every request addresses the same persisted MCP session.
seq 1 64 | xargs -P 16 -I{} bash -c '
  code="$(curl -sS -o "/tmp/robust-mw-response-{}.json" -w "%{http_code}" \
    -H "Content-Type: application/json" -H "Accept: application/json, text/event-stream" \
    -H "Mcp-Session-Id: ${session_id}" -H "MCP-Protocol-Version: 2025-11-25" -H "X-Request-Id: mw-{}" \
    --data-binary @/tmp/robust-mw-list.json "${endpoint}")"
  printf "%s\n" "$code" > "/tmp/robust-mw-codes/{}.txt"
'

if grep -L '^200$' /tmp/robust-mw-codes/*.txt | grep -q .; then
  echo 'one or more multi-worker session requests failed' >&2
  grep -L '^200$' /tmp/robust-mw-codes/*.txt >&2 || true
  exit 1
fi
for response in /tmp/robust-mw-response-*.json; do
  grep -Fq 'robust.echo' "$response"
done

# Prove the same session was consumed by at least two distinct workers rather
# than merely starting multiple workers that never handled session traffic.
worker_count="$(php -r '
$seen=[];
foreach(file($argv[1], FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line){
  $e=json_decode($line,true,512,JSON_THROW_ON_ERROR);
  if (str_starts_with((string)($e["request_id"]??""),"mw-") && isset($e["worker_pid"])) $seen[(string)$e["worker_pid"]]=true;
}
echo count($seen);
' "$telemetry")"
if [ "$worker_count" -lt 2 ]; then
  echo "multi-worker proof did not reach at least two workers (saw ${worker_count})" >&2
  cat "$telemetry" >&2
  exit 1
fi

# The persisted session remains valid after cross-worker traffic and can be
# explicitly terminated from whichever worker handles the DELETE.
delete_code="$(curl -sS -o /tmp/robust-mw-delete.json -w '%{http_code}' -X DELETE \
  -H "Mcp-Session-Id: ${session_id}" -H 'MCP-Protocol-Version: 2025-11-25' -H 'X-Request-Id: mw-delete' "$endpoint")"
test "$delete_code" = '200'
test ! -e "$session_dir/$session_id"

printf '%s\n' "robust-mcp-multiworker: PASS workers=${worker_count} shared-session=legacy concurrent-requests=64 persistent-store=file"
