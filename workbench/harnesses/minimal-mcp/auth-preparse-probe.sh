#!/usr/bin/env bash
set -euo pipefail

endpoint='http://127.0.0.1:8092/index.php?rest_route=%2Fminimal-mcp%2Fv1%2Fmcp'

php -S 127.0.0.1:8092 -t /tmp/wordpress >/tmp/minimal-mcp-auth-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -fsS 'http://127.0.0.1:8092/index.php?rest_route=%2F' >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/minimal-mcp-auth-http.log >&2
    exit 1
  fi
  sleep 1
done

printf '{' > /tmp/minimal-anonymous-invalid-json.txt
code="$(curl -sS -o /tmp/minimal-anonymous-invalid-json-response.json -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  --data-binary @/tmp/minimal-anonymous-invalid-json.txt \
  "$endpoint")"

test "$code" = '401'
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-anonymous-invalid-json-response.json"),true);
if (($d["code"]??"")!=="minimal_mcp_authentication_required") {
  fwrite(STDERR, file_get_contents("/tmp/minimal-anonymous-invalid-json-response.json"));
  exit(1);
}
'

printf '%s\n' 'minimal-mcp-preparse-auth: PASS malformed-json-cannot-bypass-auth'
