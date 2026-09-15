#!/usr/bin/env bash
set -euo pipefail

endpoint='http://127.0.0.1:8093/index.php?rest_route=%2Fminimal-mcp%2Fv1%2Fmcp'
token="${MCP_BEARER_TOKEN:?MCP_BEARER_TOKEN is required}"
app_password="$(php /tmp/wp-cli.phar user application-password create admin robust-mcp-basic-negative --porcelain --path=/tmp/wordpress)"
common_headers=(
  -H 'Content-Type: application/json'
  -H 'Accept: application/json, text/event-stream'
)

php -S 127.0.0.1:8093 -t /tmp/wordpress >/tmp/robust-mcp-bearer-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -fsS 'http://127.0.0.1:8093/index.php?rest_route=%2F' >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-mcp-bearer-http.log >&2
    exit 1
  fi
  sleep 1
done

cat > /tmp/robust-bearer-discover.json <<'JSON'
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"robust-mcp-bearer-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

missing_code="$(curl -sS -o /tmp/robust-bearer-missing.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$missing_code" = '401'
grep -Fq 'robust_mcp_bearer_required' /tmp/robust-bearer-missing.json

wrong_code="$(curl -sS -o /tmp/robust-bearer-wrong.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'Authorization: Bearer definitely-wrong-token-value-000000000000' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$wrong_code" = '401'
grep -Fq 'robust_mcp_bearer_required' /tmp/robust-bearer-wrong.json

# Even a valid WordPress Application Password is not a fallback when manual bearer mode is configured.
basic_code="$(curl -sS -o /tmp/robust-bearer-basic.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$basic_code" = '401'
grep -Fq 'robust_mcp_bearer_required' /tmp/robust-bearer-basic.json

valid_code="$(curl -sS -o /tmp/robust-bearer-valid.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$valid_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/robust-bearer-valid.json"),true);
$r=$d["result"]??[];
if (($r["supportedVersions"]??[])!==["2026-07-28"]) exit(1);
if (($r["_meta"]["io.modelcontextprotocol/serverInfo"]["name"]??"")!=="robust-mcp-server") exit(2);
if (($r["_meta"]["io.modelcontextprotocol/serverInfo"]["version"]??"")!=="0.1.0") exit(3);
'

# Scheme matching is case-insensitive.
lowercase_code="$(curl -sS -o /tmp/robust-bearer-lowercase.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: bearer ${token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$lowercase_code" = '200'

# The pre-parse JSON-RPC path must still enforce the bearer before returning -32700.
printf '{' > /tmp/robust-bearer-invalid-json.txt
malformed_missing_code="$(curl -sS -o /tmp/robust-bearer-malformed-missing.json -w '%{http_code}' \
  "${common_headers[@]}" --data-binary @/tmp/robust-bearer-invalid-json.txt "$endpoint")"
test "$malformed_missing_code" = '401'
grep -Fq 'robust_mcp_bearer_required' /tmp/robust-bearer-malformed-missing.json

malformed_valid_code="$(curl -sS -o /tmp/robust-bearer-malformed-valid.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${token}" \
  --data-binary @/tmp/robust-bearer-invalid-json.txt "$endpoint")"
test "$malformed_valid_code" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/robust-bearer-malformed-valid.json"),true); if (($d["error"]["code"]??null)!==-32700) exit(1);'

MCP_ENDPOINT="$endpoint" MCP_BEARER_TOKEN="$token" node workbench/harnesses/minimal-mcp/bearer-sdk-probe.mjs

authorization="Bearer ${token}"
MCP_ENDPOINT="$endpoint" MCP_AUTHORIZATION="$authorization" php -r '
file_put_contents("/tmp/robust-bearer-inspector.json", json_encode(["mcpServers"=>["robust"=>["type"=>"http","url"=>getenv("MCP_ENDPOINT"),"headers"=>["Authorization"=>getenv("MCP_AUTHORIZATION")],"protocolEra"=>"modern"]]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
'

npx --no-install mcp-inspector --cli --config /tmp/robust-bearer-inspector.json --server robust --method tools/list --format json > /tmp/robust-bearer-inspector-list.json
grep -Fq 'probe.site' /tmp/robust-bearer-inspector-list.json

npx --no-install mcp-inspector --cli --config /tmp/robust-bearer-inspector.json --server robust \
  --method tools/call --tool-name probe.site --format json > /tmp/robust-bearer-inspector-call.json
grep -Fq 'Robust MCP Server' /tmp/robust-bearer-inspector-call.json

printf '%s\n' 'robust-mcp-manual-bearer: PASS exclusive-bearer curl+official-sdk+inspector malformed-json-authenticated'
