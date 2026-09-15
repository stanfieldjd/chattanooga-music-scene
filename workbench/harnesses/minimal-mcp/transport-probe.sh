#!/usr/bin/env bash
set -euo pipefail

endpoint='http://127.0.0.1:8091/index.php?rest_route=%2Fminimal-mcp%2Fv1%2Fmcp'
app_password="$(php /tmp/wp-cli.phar user application-password create admin minimal-mcp-ci --porcelain --path=/tmp/wordpress)"
common_headers=(
  -H 'Content-Type: application/json'
  -H 'Accept: application/json, text/event-stream'
)
modern_meta='"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}'

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

assert_error() {
  local file="$1" expected="$2"
  php -r '$d=json_decode(file_get_contents($argv[1]),true); if (($d["error"]["code"]??null)!==(int)$argv[2]) {fwrite(STDERR,file_get_contents($argv[1])); exit(1);}' "$file" "$expected"
}

cat > /tmp/minimal-discover.json <<'JSON'
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

# Modern-only endpoint semantics: GET and DELETE are explicitly 405.
get_code="$(curl -sS -D /tmp/minimal-get-headers.txt -o /tmp/minimal-get.json -w '%{http_code}' "$endpoint")"
test "$get_code" = '405'
grep -Eiq '^Allow: POST' /tmp/minimal-get-headers.txt

delete_code="$(curl -sS -X DELETE -o /tmp/minimal-delete.json -w '%{http_code}' "$endpoint")"
test "$delete_code" = '405'

# Authentication and request-target guards remain at the transport boundary.
anonymous_code="$(curl -sS -o /tmp/minimal-anonymous.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$anonymous_code" = '401'

host_code="$(curl -sS -o /tmp/minimal-host.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'Host: attacker.example' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$host_code" = '403'

origin_code="$(curl -sS -o /tmp/minimal-origin.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'Origin: https://attacker.example' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$origin_code" = '403'

# Media-type negotiation is protocol behavior, not application behavior.
content_type_code="$(curl -sS -o /tmp/minimal-content-type.json -w '%{http_code}' \
  --user "admin:${app_password}" -H 'Content-Type: text/plain' -H 'Accept: application/json, text/event-stream' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$content_type_code" = '415'

accept_code="$(curl -sS -o /tmp/minimal-accept.json -w '%{http_code}' \
  --user "admin:${app_password}" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$accept_code" = '406'

accept_q0_code="$(curl -sS -o /tmp/minimal-accept-q0.json -w '%{http_code}' \
  --user "admin:${app_password}" -H 'Content-Type: application/json; charset=utf-8' -H 'Accept: application/json, text/event-stream;q=0' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$accept_q0_code" = '406'

# JSON-RPC framing and classification.
printf '{' > /tmp/minimal-invalid-json.txt
invalid_json_code="$(curl -sS -o /tmp/minimal-invalid-json-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" --data-binary @/tmp/minimal-invalid-json.txt "$endpoint")"
test "$invalid_json_code" = '400'
assert_error /tmp/minimal-invalid-json-response.json -32700

printf '[{"jsonrpc":"2.0","id":1,"method":"server/discover"}]' > /tmp/minimal-batch.json
batch_code="$(curl -sS -o /tmp/minimal-batch-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" --data-binary @/tmp/minimal-batch.json "$endpoint")"
test "$batch_code" = '400'
assert_error /tmp/minimal-batch-response.json -32600

printf '{"jsonrpc":"2.0","id":9,"result":{}}' > /tmp/minimal-client-response.json
client_response_code="$(curl -sS -o /tmp/minimal-client-response-out.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" --data-binary @/tmp/minimal-client-response.json "$endpoint")"
test "$client_response_code" = '400'
assert_error /tmp/minimal-client-response-out.json -32600

# Notifications are acknowledged with 202 and no response body; modern routing headers are not required for them.
printf '{"jsonrpc":"2.0","method":"notifications/progress","params":{"progressToken":"ci"}}' > /tmp/minimal-notification.json
notification_code="$(curl -sS -o /tmp/minimal-notification-response -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" --data-binary @/tmp/minimal-notification.json "$endpoint")"
test "$notification_code" = '202'
test ! -s /tmp/minimal-notification-response

# A current modern header without the current body envelope is malformed modern MCP.
printf '{"jsonrpc":"2.0","id":10,"method":"server/discover","params":{}}' > /tmp/minimal-missing-meta.json
missing_meta_code="$(curl -sS -o /tmp/minimal-missing-meta-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-missing-meta.json "$endpoint")"
test "$missing_meta_code" = '400'
assert_error /tmp/minimal-missing-meta-response.json -32602

# Normal discovery succeeds, including JSON Content-Type parameters and ignored legacy session headers.
discover_code="$(curl -sS -D /tmp/minimal-discover-headers.txt -o /tmp/minimal-discover-response.json -w '%{http_code}' \
  --user "admin:${app_password}" -H 'Content-Type: application/json; charset=utf-8' -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' -H 'Mcp-Session-Id: ignored-modern-session' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$discover_code" = '200'
grep -Eiq '^MCP-Protocol-Version: 2026-07-28' /tmp/minimal-discover-headers.txt
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-discover-response.json"),true); $r=$d["result"]??[];
if (($r["resultType"]??"")!=="complete") exit(1);
if (($r["supportedVersions"]??[])!==["2026-07-28"]) exit(2);
if (($r["_meta"]["io.modelcontextprotocol/serverInfo"]["name"]??"")!=="minimal-mcp-tunnel") exit(3);
if (($r["_meta"]["io.modelcontextprotocol/serverInfo"]["version"]??"")!=="0.0.5") exit(4);
'

cat > /tmp/minimal-list.json <<'JSON'
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
list_code="$(curl -sS -o /tmp/minimal-list-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/list' \
  --data-binary @/tmp/minimal-list.json "$endpoint")"
test "$list_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/minimal-list-response.json"),true); $tools=$d["result"]["tools"]??[];
$names=array_map(static fn($tool)=>$tool["name"]??"",$tools); sort($names,SORT_STRING);
if ($names!==["fixture.echo","probe.site"]) exit(1);
$echo=null; foreach($tools as $tool){if(($tool["name"]??"")==="fixture.echo"){$echo=$tool; break;}}
if (($echo["inputSchema"]["properties"]["text"]["x-mcp-header"]??"")!=="Text") exit(2);
if (($echo["inputSchema"]["properties"]["count"]["x-mcp-header"]??"")!=="Count") exit(3);
'

cat > /tmp/minimal-site-call.json <<'JSON'
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"probe.site","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
site_code="$(curl -sS -o /tmp/minimal-site-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: probe.site' \
  --data-binary @/tmp/minimal-site-call.json "$endpoint")"
test "$site_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-site-response.json"),true); $s=$d["result"]["structuredContent"]??[]; if (($s["ok"]??false)!==true || ($s["siteTitle"]??"")!=="Minimal MCP Tunnel") exit(1);'

cat > /tmp/minimal-echo-call.json <<'JSON'
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"fixture.echo","arguments":{"text":"registry-works"},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
missing_param_code="$(curl -sS -o /tmp/minimal-param-missing.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$missing_param_code" = '400'
assert_error /tmp/minimal-param-missing.json -32020

mismatch_param_code="$(curl -sS -o /tmp/minimal-param-mismatch.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  -H 'Mcp-Param-Text: wrong-value' --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$mismatch_param_code" = '400'
assert_error /tmp/minimal-param-mismatch.json -32020

echo_code="$(curl -sS -o /tmp/minimal-echo-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  -H 'Mcp-Param-Text: registry-works' --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$echo_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-echo-response.json"),true); $s=$d["result"]["structuredContent"]??[]; if (($s["echo"]??"")!=="registry-works" || ($s["source"]??"")!=="external-wordpress-plugin") exit(1);'

# Integer mirrors compare numerically, so 42.0 mirrors integer 42.
cat > /tmp/minimal-numeric-call.json <<'JSON'
{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"fixture.echo","arguments":{"text":"numeric","count":42},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
numeric_code="$(curl -sS -o /tmp/minimal-numeric-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  -H 'Mcp-Param-Text: numeric' -H 'Mcp-Param-Count: 42.0' --data-binary @/tmp/minimal-numeric-call.json "$endpoint")"
test "$numeric_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/minimal-numeric-response.json"),true); if (($d["result"]["structuredContent"]["count"]??null)!==42) exit(1);'

encoded_name='=?base64?Zml4dHVyZS5lY2hv?='
encoded_code="$(curl -sS -o /tmp/minimal-encoded-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' \
  -H "Mcp-Name: ${encoded_name}" -H 'Mcp-Param-Text: registry-works' --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$encoded_code" = '200'

bad_encoded_code="$(curl -sS -o /tmp/minimal-bad-encoded.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: =?base64?%%%?=' -H 'Mcp-Param-Text: registry-works' --data-binary @/tmp/minimal-echo-call.json "$endpoint")"
test "$bad_encoded_code" = '400'
assert_error /tmp/minimal-bad-encoded.json -32020

cat > /tmp/minimal-unicode-call.json <<'JSON'
{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"fixture.echo","arguments":{"text":" Hello, 世界 "},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
unicode_param="=?base64?$(printf ' Hello, 世界 ' | base64 -w0)?="
unicode_code="$(curl -sS -o /tmp/minimal-unicode-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.echo' \
  -H "Mcp-Param-Text: ${unicode_param}" --data-binary @/tmp/minimal-unicode-call.json "$endpoint")"
test "$unicode_code" = '200'

# Unknown tool and malformed tool call params are protocol errors, not tool execution errors.
cat > /tmp/minimal-unknown-tool.json <<'JSON'
{"jsonrpc":"2.0","id":7,"method":"tools/call","params":{"name":"missing.tool","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
unknown_tool_code="$(curl -sS -o /tmp/minimal-unknown-tool-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: missing.tool' \
  --data-binary @/tmp/minimal-unknown-tool.json "$endpoint")"
test "$unknown_tool_code" = '200'
assert_error /tmp/minimal-unknown-tool-response.json -32602

cat > /tmp/minimal-bad-args.json <<'JSON'
{"jsonrpc":"2.0","id":8,"method":"tools/call","params":{"name":"probe.site","arguments":["bad"],"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
bad_args_code="$(curl -sS -o /tmp/minimal-bad-args-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: probe.site' \
  --data-binary @/tmp/minimal-bad-args.json "$endpoint")"
test "$bad_args_code" = '200'
assert_error /tmp/minimal-bad-args-response.json -32602

# Header/body protocol disagreement differs from a genuinely unsupported revision.
mismatch_code="$(curl -sS -o /tmp/minimal-mismatch.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-27' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-discover.json "$endpoint")"
test "$mismatch_code" = '400'
assert_error /tmp/minimal-mismatch.json -32020

cat > /tmp/minimal-unsupported.json <<'JSON'
{"jsonrpc":"2.0","id":11,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2099-01-01","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
unsupported_code="$(curl -sS -o /tmp/minimal-unsupported-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2099-01-01' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/minimal-unsupported.json "$endpoint")"
test "$unsupported_code" = '400'
assert_error /tmp/minimal-unsupported-response.json -32022

cat > /tmp/minimal-initialize.json <<'JSON'
{"jsonrpc":"2.0","id":12,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"legacy-client","version":"1.0.0"}}}
JSON
legacy_code="$(curl -sS -o /tmp/minimal-legacy.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2025-11-25' -H 'Mcp-Method: initialize' \
  --data-binary @/tmp/minimal-initialize.json "$endpoint")"
test "$legacy_code" = '400'
assert_error /tmp/minimal-legacy.json -32022

cat > /tmp/minimal-unknown-method.json <<'JSON'
{"jsonrpc":"2.0","id":13,"method":"unknown/method","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"minimal-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
unknown_method_code="$(curl -sS -o /tmp/minimal-unknown-method-response.json -w '%{http_code}' \
  --user "admin:${app_password}" "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: unknown/method' \
  --data-binary @/tmp/minimal-unknown-method.json "$endpoint")"
test "$unknown_method_code" = '404'
assert_error /tmp/minimal-unknown-method-response.json -32601

# Independent modern clients must still connect and call tools after hardening.
MCP_ENDPOINT="$endpoint" MCP_USER='admin' MCP_PASSWORD="$app_password" node workbench/harnesses/minimal-mcp/sdk-probe.mjs

authorization="Basic $(printf 'admin:%s' "$app_password" | base64 -w0)"
MCP_ENDPOINT="$endpoint" MCP_AUTHORIZATION="$authorization" php -r '
file_put_contents("/tmp/minimal-inspector.json", json_encode(["mcpServers"=>["minimal"=>["type"=>"http","url"=>getenv("MCP_ENDPOINT"),"headers"=>["Authorization"=>getenv("MCP_AUTHORIZATION")],"protocolEra"=>"modern"]]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
'

npx --no-install mcp-inspector --cli --config /tmp/minimal-inspector.json --server minimal --method tools/list --format json > /tmp/minimal-inspector-list.json
grep -Fq 'fixture.echo' /tmp/minimal-inspector-list.json
grep -Fq '"x-mcp-header":"Text"' <(tr -d '[:space:]' < /tmp/minimal-inspector-list.json)

npx --no-install mcp-inspector --cli --config /tmp/minimal-inspector.json --server minimal \
  --method tools/call --tool-name fixture.echo --tool-arg text=inspector-works --format json > /tmp/minimal-inspector-call.json
grep -Fq 'inspector-works' /tmp/minimal-inspector-call.json
grep -Fq 'external-wordpress-plugin' /tmp/minimal-inspector-call.json

printf '%s\n' 'minimal-mcp-transport: PASS http-conformance json-rpc classification auth host origin media-types notifications curl+official-sdk+inspector x-mcp-header modern-only'
