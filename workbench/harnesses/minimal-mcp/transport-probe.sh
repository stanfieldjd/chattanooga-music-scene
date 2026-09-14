#!/usr/bin/env bash
set -euo pipefail

endpoint='http://127.0.0.1:8091/index.php?rest_route=%2Fminimal-mcp%2Fv1%2Fmcp'
app_password="$(php /tmp/wp-cli.phar user application-password create admin minimal-mcp-ci --porcelain --path=/tmp/wordpress)"
common_headers=(
  -H 'Content-Type: application/json'
  -H 'Accept: application/json, text/event-stream'
)

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
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

anonymous_code="$(curl -sS -o /tmp/minimal-anonymous.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$anonymous_code" = '401'

origin_code="$(curl -sS -o /tmp/minimal-origin.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'Origin: https://attacker.example' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$origin_code" = '403'

discover_code="$(curl -sS -D /tmp/minimal-discover-headers.txt -o /tmp/minimal-discover-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$discover_code" = '200'
grep -Eiq '^MCP-Protocol-Version: 2026-07-28' /tmp/minimal-discover-headers.txt
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-discover-response.json"),true); $r=$d["result"]??[];
if (($r["resultType"]??"")!=="complete") exit(1);
if (($r["supportedVersions"]??[])!==["2026-07-28"]) exit(2);
if (($r["_meta"]["io.modelcontextprotocol/serverInfo"]["name"]??"")!=="minimal-mcp-tunnel") exit(3);
if (($r["_meta"]["io.modelcontextprotocol/serverInfo"]["version"]??"")!=="0.0.4") exit(4);
'

cat > /tmp/minimal-list.json <<'JSON'
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

list_code="$(curl -sS -o /tmp/minimal-list-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/list' \
  --data-binary @/tmp/minimal-list.json "$endpoint")"
test "$list_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-list-response.json"),true); $tools=$d["result"]["tools"]??[];
$names=array_map(static fn($tool)=>$tool["name"]??"",$tools); sort($names,SORT_STRING);
if ($names!==["fixture.echo","probe.site"]) exit(1);
$echo=null; foreach($tools as $tool){if(($tool["name"]??"")==="fixture.echo"){$echo=$tool; break;}}
if (($echo["inputSchema"]["properties"]["text"]["x-mcp-header"]??"")!=="Text") exit(2);
'

cat > /tmp/minimal-site-call.json <<'JSON'
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"probe.site","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

site_code="$(curl -sS -o /tmp/minimal-site-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: probe.site' \
  --data-binary @/tmp/minimal-site-call.json "$endpoint")"
test "$site_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-site-response.json"),true); $r=$d["result"]??[]; $s=$r["structuredContent"]??[];
if (($r["isError"]??true)!==false || ($s["ok"]??false)!==true || ($s["siteTitle"]??"")!=="Minimal MCP Tunnel" || empty($s["wordpressVersion"])) exit(1);
'

cat > /tmp/minimal-echo-call.json <<'JSON'
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"fixture.echo","arguments":{"text":"registry-works"},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

# A present annotated argument without its Mcp-Param-* mirror is non-conforming.
missing_param_code="$(curl -sS -o /tmp/minimal-param-missing.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$missing_param_code" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-param-missing.json"),true); if (($d["error"]["code"]??0)!==-32020) exit(1);'

mismatch_param_code="$(curl -sS -o /tmp/minimal-param-mismatch.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  -H 'Mcp-Param-Text: wrong-value' --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$mismatch_param_code" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-param-mismatch.json"),true); if (($d["error"]["code"]??0)!==-32020) exit(1);'

echo_code="$(curl -sS -o /tmp/minimal-echo-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  -H 'Mcp-Param-Text: registry-works' --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$echo_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-echo-response.json"),true); $r=$d["result"]??[]; $s=$r["structuredContent"]??[];
if (($r["isError"]??true)!==false || ($s["echo"]??"")!=="registry-works" || ($s["source"]??"")!=="external-wordpress-plugin") exit(1);
'

# Mcp-Name and Mcp-Param-* both support the Base64 sentinel.
encoded_name='=?base64?Zml4dHVyZS5lY2hv?='
encoded_code="$(curl -sS -o /tmp/minimal-encoded-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' \
  -H "Mcp-Name: ${encoded_name}" -H 'Mcp-Param-Text: registry-works' \
  --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$encoded_code" = '200'

cat > /tmp/minimal-unicode-call.json <<'JSON'
{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"fixture.echo","arguments":{"text":" Hello, 世界 "},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
unicode_param="=?base64?$(printf ' Hello, 世界 ' | base64 -w0)?="
unicode_code="$(curl -sS -o /tmp/minimal-unicode-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  -H "Mcp-Param-Text: ${unicode_param}" --data-binary @/tmp/minimal-unicode-call.json "$endpoint")"
test "$unicode_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-unicode-response.json"),true); if (($d["result"]["structuredContent"]["echo"]??"")!==" Hello, 世界 ") exit(1);'

mismatch_code="$(curl -sS -o /tmp/minimal-mismatch.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2026-07-27' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$mismatch_code" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-mismatch.json"),true); if (($d["error"]["code"]??0)!==-32020) exit(1);'

cat > /tmp/minimal-unsupported.json <<'JSON'
{"jsonrpc":"2.0","id":6,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2099-01-01","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
unsupported_code="$(curl -sS -o /tmp/minimal-unsupported-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2099-01-01' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-unsupported.json "$endpoint")"
test "$unsupported_code" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-unsupported-response.json"),true); $e=$d["error"]??[]; if (($e["code"]??0)!==-32022 || ($e["data"]["supported"]??[])!==["2026-07-28"] || ($e["data"]["requested"]??"")!=="2099-01-01") exit(1);'

cat > /tmp/minimal-initialize.json <<'JSON'
{"jsonrpc":"2.0","id":7,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"legacy-client","version":"1.0.0"}}}
JSON
legacy_code="$(curl -sS -o /tmp/minimal-legacy.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" \
  -H 'MCP-Protocol-Version: 2025-11-25' -H 'Mcp-Method: initialize' \
  --data-binary @/tmp/minimal-initialize.json "$endpoint")"
test "$legacy_code" = '400'

MCP_ENDPOINT="$endpoint" MCP_USER='admin' MCP_PASSWORD="$app_password" node workbench/harnesses/minimal-mcp/sdk-probe.mjs

authorization="Basic $(printf 'admin:%s' "$app_password" | base64 -w0)"
MCP_ENDPOINT="$endpoint" MCP_AUTHORIZATION="$authorization" php -r '
file_put_contents("/tmp/minimal-inspector.json", json_encode(["mcpServers"=>["minimal"=>["type"=>"http","url"=>getenv("MCP_ENDPOINT"),"headers"=>["Authorization"=>getenv("MCP_AUTHORIZATION")],"protocolEra"=>"modern"]]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
'

npx --no-install mcp-inspector --cli --config /tmp/minimal-inspector.json --server minimal --method tools/list --format json > /tmp/minimal-inspector-list.json
grep -Fq 'fixture.echo' /tmp/minimal-inspector-list.json
grep -Fq '"x-mcp-header":"Text"' <(tr -d '[:space:]' < /tmp/minimal-inspector-list.json)

# Inspector must derive Mcp-Param-Text itself; the server rejects the body otherwise.
npx --no-install mcp-inspector --cli --config /tmp/minimal-inspector.json --server minimal \
  --method tools/call --tool-name fixture.echo --tool-arg text=inspector-works --format json > /tmp/minimal-inspector-call.json
grep -Fq 'inspector-works' /tmp/minimal-inspector-call.json
grep -Fq 'external-wordpress-plugin' /tmp/minimal-inspector-call.json

printf '%s\n' 'minimal-mcp-transport: PASS curl+official-sdk+inspector dynamic-registry x-mcp-header metadata-validation origin-validation base64 modern-only'
