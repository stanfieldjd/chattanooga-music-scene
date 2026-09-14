#!/usr/bin/env bash
set -euo pipefail

endpoint='http://127.0.0.1:8091/index.php?rest_route=%2Fminimal-mcp%2Fv1%2Fmcp'
app_password="$(php /tmp/wp-cli.phar user application-password create admin minimal-mcp-ci --porcelain --path=/tmp/wordpress)"

php -S 127.0.0.1:8091 -t /tmp/wordpress >/tmp/minimal-mcp-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -fsS 'http://127.0.0.1:8091/index.php?rest_route=%2F' >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/minimal-mcp-http.log >&2
    exit 1
  fi
  sleep 1
done

cat > /tmp/minimal-discover.json <<'JSON'
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"}}}}
JSON

anonymous_code="$(curl -sS -o /tmp/minimal-anonymous.json -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json \
  "$endpoint")"
test "$anonymous_code" = '401'

discover_code="$(curl -sS -D /tmp/minimal-discover-headers.txt -o /tmp/minimal-discover-response.json -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json \
  "$endpoint")"
test "$discover_code" = '200'
grep -Eiq '^MCP-Protocol-Version: 2026-07-28' /tmp/minimal-discover-headers.txt
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-discover-response.json"),true);
$r=$d["result"]??[];
if (($r["resultType"]??"")!=="complete") exit(1);
if (($r["supportedVersions"]??[])!==["2026-07-28"]) exit(2);
if (($r["_meta"]["io.modelcontextprotocol/serverInfo"]["name"]??"")!=="minimal-mcp-tunnel") exit(3);
'

cat > /tmp/minimal-list.json <<'JSON'
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"}}}}
JSON

list_code="$(curl -sS -o /tmp/minimal-list-response.json -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/list' \
  --data-binary @/tmp/minimal-list.json \
  "$endpoint")"
test "$list_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-list-response.json"),true);
$tools=$d["result"]["tools"]??[];
if (count($tools)!==1) exit(1);
if (($tools[0]["name"]??"")!=="probe.site") exit(2);
if (($tools[0]["annotations"]["readOnlyHint"]??false)!==true) exit(3);
'

cat > /tmp/minimal-call.json <<'JSON'
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"probe.site","arguments":{},"_meta":{"io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"}}}}
JSON

call_code="$(curl -sS -o /tmp/minimal-call-response.json -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: probe.site' \
  --data-binary @/tmp/minimal-call.json \
  "$endpoint")"
test "$call_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-call-response.json"),true);
$r=$d["result"]??[];
$s=$r["structuredContent"]??[];
if (($r["resultType"]??"")!=="complete") exit(1);
if (($r["isError"]??true)!==false) exit(2);
if (($s["ok"]??false)!==true) exit(3);
if (($s["siteTitle"]??"")!=="Minimal MCP Tunnel") exit(4);
if (empty($s["wordpressVersion"])) exit(5);
'

bad_header_code="$(curl -sS -o /tmp/minimal-bad-header.json -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/list' \
  -H 'Mcp-Name: probe.site' \
  --data-binary @/tmp/minimal-call.json \
  "$endpoint")"
test "$bad_header_code" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-bad-header.json"),true); if (($d["error"]["code"]??0)!==-32020) exit(1);'

cat > /tmp/minimal-initialize.json <<'JSON'
{"jsonrpc":"2.0","id":4,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"legacy-client","version":"1.0.0"}}}
JSON

legacy_code="$(curl -sS -o /tmp/minimal-legacy.json -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2025-11-25' \
  -H 'Mcp-Method: initialize' \
  --data-binary @/tmp/minimal-initialize.json \
  "$endpoint")"
test "$legacy_code" = '400'

printf '%s\n' 'minimal-mcp-transport: PASS discover tools/list tools/call auth header-validation modern-only'
