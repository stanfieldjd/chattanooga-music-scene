#!/usr/bin/env bash
set -euo pipefail

WP_CLI="${WP_CLI:-/tmp/wp-cli.phar}"
WP_PATH="${WP_PATH:-/tmp/wordpress}"
endpoint='http://127.0.0.1:8091/index.php?rest_route=%2Fchattanooga-cms-admin%2Fv1%2Fmcp'
app_password="$(php "$WP_CLI" user application-password create admin cmsa-external-catalog-diagnostic --porcelain --path="$WP_PATH")"

php -S 127.0.0.1:8091 -t "$WP_PATH" >/tmp/cmsa-external-catalog-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -fsS 'http://127.0.0.1:8091/index.php?rest_route=%2F' >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/cmsa-external-catalog-http.log >&2
    exit 1
  fi
  sleep 1
done

cat > /tmp/cmsa-external-catalog-diagnostic.json <<'JSON'
{"jsonrpc":"2.0","id":1201,"method":"tools/call","params":{"name":"cmsa.catalog","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"cmsa-external-catalog-diagnostic","version":"1.0.0"}}}}
JSON

code="$(curl -sS -o /tmp/cmsa-external-catalog-diagnostic-response.json -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: cmsa.catalog' \
  --data-binary @/tmp/cmsa-external-catalog-diagnostic.json \
  "$endpoint")"

test "$code" = '200' || { cat /tmp/cmsa-external-catalog-diagnostic-response.json >&2; exit 1; }

php -r '
$d=json_decode(file_get_contents("/tmp/cmsa-external-catalog-diagnostic-response.json"),true);
$count=0;
foreach (($d["result"]["structuredContent"]["items"]??[]) as $item) {
    if (($item["contract"]??"")!=="rest") continue;
    $route=(string)($item["route"]??"");
    if (strpos($route,"cms_weekend_feature")===false) continue;
    ++$count;
    echo "external-weekend-route method=".($item["method"]??"")." route=".$route." bridge=".($item["bridge"]??"")."\n";
}
echo "external-weekend-route-count={$count}\n";
'