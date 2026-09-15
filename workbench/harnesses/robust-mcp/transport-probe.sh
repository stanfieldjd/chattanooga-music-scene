#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
endpoint='http://127.0.0.1:8093/mcp'
counter='/tmp/robust-mcp-handler-count'
rm -f "$counter"

ROBUST_MCP_COUNTER_FILE="$counter" php -S 127.0.0.1:8093 "$base_dir/server.php" >/tmp/robust-mcp-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -sS -o /dev/null -w '%{http_code}' 'http://127.0.0.1:8093/not-mcp' | grep -q '^404$'; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-mcp-http.log >&2
    exit 1
  fi
  sleep 1
done

common_headers=(
  -H 'Content-Type: application/json'
  -H 'Accept: application/json, text/event-stream'
)
meta='"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"robust-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}'

assert_error() {
  local file="$1" expected="$2"
  php -r '$d=json_decode(file_get_contents($argv[1]),true); if (($d["error"]["code"]??null)!==(int)$argv[2]) {fwrite(STDERR,file_get_contents($argv[1])); exit(1);}' "$file" "$expected"
}

cat > /tmp/robust-discover.json <<JSON
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{${meta}}}
JSON

# HTTP edge semantics are explicit and independent of the SDK dispatcher.
get_code="$(curl -sS -D /tmp/robust-get-headers.txt -o /tmp/robust-get.json -w '%{http_code}' "$endpoint")"
test "$get_code" = '405'
grep -Eiq '^Allow: POST' /tmp/robust-get-headers.txt

content_type_code="$(curl -sS -o /tmp/robust-content-type.json -w '%{http_code}' \
  -H 'Content-Type: text/plain' -H 'Accept: application/json, text/event-stream' \
  --data-binary @/tmp/robust-discover.json "$endpoint")"
test "$content_type_code" = '415'
assert_error /tmp/robust-content-type.json -32600

accept_code="$(curl -sS -o /tmp/robust-accept.json -w '%{http_code}' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  --data-binary @/tmp/robust-discover.json "$endpoint")"
test "$accept_code" = '406'
assert_error /tmp/robust-accept.json -32600

accept_q0_code="$(curl -sS -o /tmp/robust-accept-q0.json -w '%{http_code}' \
  -H 'Content-Type: application/json; charset=utf-8' -H 'Accept: application/json, text/event-stream;q=0' \
  --data-binary @/tmp/robust-discover.json "$endpoint")"
test "$accept_q0_code" = '406'

# Parser/framing behavior remains JSON-RPC, not framework-specific errors.
printf '{' > /tmp/robust-invalid-json.txt
invalid_json_code="$(curl -sS -o /tmp/robust-invalid-json-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-invalid-json.txt "$endpoint")"
test "$invalid_json_code" = '400'
assert_error /tmp/robust-invalid-json-response.json -32700

printf '[{"jsonrpc":"2.0","id":2,"method":"server/discover"}]' > /tmp/robust-batch.json
batch_code="$(curl -sS -o /tmp/robust-batch-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-batch.json "$endpoint")"
test "$batch_code" = '400'
assert_error /tmp/robust-batch-response.json -32600

# Transport body limit is deliberately lower than the SDK default.
php -r 'file_put_contents("/tmp/robust-oversize.txt", str_repeat("x", 1048577));'
oversize_code="$(curl -sS -o /tmp/robust-oversize-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-oversize.txt "$endpoint")"
test "$oversize_code" = '413'

# Notifications are acknowledged and never receive a JSON-RPC response body.
printf '{"jsonrpc":"2.0","method":"notifications/progress","params":{"progressToken":"ci"}}' > /tmp/robust-notification.json
notification_code="$(curl -sS -o /tmp/robust-notification-response -w '%{http_code}' \
  "${common_headers[@]}" --data-binary @/tmp/robust-notification.json "$endpoint")"
test "$notification_code" = '202'
test ! -s /tmp/robust-notification-response

# Discovery and advertised schema fidelity.
discover_code="$(curl -sS -D /tmp/robust-discover-headers.txt -o /tmp/robust-discover-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-discover.json "$endpoint")"
test "$discover_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/robust-discover-response.json"),true); $s=$d["result"]["_meta"]["io.modelcontextprotocol/serverInfo"]??[]; if (($s["name"]??"")!=="chattanooga-robust-mcp" || ($s["version"]??"")!=="0.1.0") exit(1);'

cat > /tmp/robust-list.json <<JSON
{"jsonrpc":"2.0","id":3,"method":"tools/list","params":{${meta}}}
JSON
list_code="$(curl -sS -o /tmp/robust-list-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/list' \
  --data-binary @/tmp/robust-list.json "$endpoint")"
test "$list_code" = '200'
php -r '
$d=json_decode(file_get_contents("/tmp/robust-list-response.json"),true); $tools=$d["result"]["tools"]??[];
$names=array_map(static fn($t)=>$t["name"]??"",$tools); sort($names,SORT_STRING);
if ($names!==["robust.bad-output","robust.echo"]) exit(1);
$echo=null; foreach($tools as $tool){if(($tool["name"]??"")==="robust.echo"){$echo=$tool;break;}}
if (!isset($echo["inputSchema"]["$defs"],$echo["inputSchema"]["allOf"])) exit(2);
if (($echo["inputSchema"]["properties"]["text"]["x-mcp-header"]??"")!=="Text") exit(3);
'

# Invalid conditional input must be rejected before the business callback executes.
cat > /tmp/robust-invalid-call.json <<JSON
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"robust.echo","arguments":{"text":"missing-count","mode":"counted"},${meta}}}
JSON
invalid_call_code="$(curl -sS -o /tmp/robust-invalid-call-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: robust.echo' -H 'Mcp-Param-Text: missing-count' \
  --data-binary @/tmp/robust-invalid-call.json "$endpoint")"
test "$invalid_call_code" = '200'
assert_error /tmp/robust-invalid-call-response.json -32602
test ! -e "$counter"

cat > /tmp/robust-valid-call.json <<JSON
{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"robust.echo","arguments":{"text":"curl-works","mode":"counted","count":3,"tags":["wire","schema"]},${meta}}}
JSON
valid_call_code="$(curl -sS -o /tmp/robust-valid-call-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: robust.echo' -H 'Mcp-Param-Text: curl-works' \
  --data-binary @/tmp/robust-valid-call.json "$endpoint")"
test "$valid_call_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/robust-valid-call-response.json"),true); $r=$d["result"]??[]; if (($r["isError"]??true)!==false || ($r["structuredContent"]["echo"]??"")!=="curl-works" || ($r["structuredContent"]["count"]??null)!==3) exit(1);'
test "$(wc -l < "$counter")" = '1'

# A callback that violates outputSchema must become a tool error with no invalid structuredContent.
cat > /tmp/robust-bad-output-call.json <<JSON
{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"robust.bad-output","arguments":{},${meta}}}
JSON
bad_output_code="$(curl -sS -o /tmp/robust-bad-output-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: robust.bad-output' \
  --data-binary @/tmp/robust-bad-output-call.json "$endpoint")"
test "$bad_output_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/robust-bad-output-response.json"),true); $r=$d["result"]??[]; if (($r["isError"]??false)!==true || array_key_exists("structuredContent",$r)) exit(1);'

# Unknown tool remains Invalid Params; unknown method remains Method Not Found.
cat > /tmp/robust-unknown-tool.json <<JSON
{"jsonrpc":"2.0","id":7,"method":"tools/call","params":{"name":"missing.tool","arguments":{},${meta}}}
JSON
unknown_tool_code="$(curl -sS -o /tmp/robust-unknown-tool-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: missing.tool' \
  --data-binary @/tmp/robust-unknown-tool.json "$endpoint")"
test "$unknown_tool_code" = '200'
assert_error /tmp/robust-unknown-tool-response.json -32602

cat > /tmp/robust-unknown-method.json <<JSON
{"jsonrpc":"2.0","id":8,"method":"unknown/method","params":{${meta}}}
JSON
unknown_method_code="$(curl -sS -o /tmp/robust-unknown-method-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: unknown/method' \
  --data-binary @/tmp/robust-unknown-method.json "$endpoint")"
test "$unknown_method_code" = '404'
assert_error /tmp/robust-unknown-method-response.json -32601

# Header disagreement and unsupported version remain distinct failure classes.
mismatch_code="$(curl -sS -o /tmp/robust-mismatch.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-27' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-discover.json "$endpoint")"
test "$mismatch_code" = '400'
assert_error /tmp/robust-mismatch.json -32020

cat > /tmp/robust-unsupported.json <<'JSON'
{"jsonrpc":"2.0","id":9,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2099-01-01","io.modelcontextprotocol/clientInfo":{"name":"robust-mcp-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON
unsupported_code="$(curl -sS -o /tmp/robust-unsupported-response.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2099-01-01' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-unsupported.json "$endpoint")"
test "$unsupported_code" = '400'
assert_error /tmp/robust-unsupported-response.json -32022

# Independent official TypeScript client.
MCP_ENDPOINT="$endpoint" node "$base_dir/sdk-probe.mjs"

# Independent MCP Inspector CLI.
MCP_ENDPOINT="$endpoint" php -r '
file_put_contents("/tmp/robust-inspector.json", json_encode(["mcpServers"=>["robust"=>["type"=>"http","url"=>getenv("MCP_ENDPOINT"),"protocolEra"=>"modern"]]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
'

npx --no-install mcp-inspector --cli --config /tmp/robust-inspector.json --server robust --method tools/list --format json > /tmp/robust-inspector-list.json
grep -Fq 'robust.echo' /tmp/robust-inspector-list.json
grep -Fq '"x-mcp-header":"Text"' <(tr -d '[:space:]' < /tmp/robust-inspector-list.json)

npx --no-install mcp-inspector --cli --config /tmp/robust-inspector.json --server robust \
  --method tools/call --tool-name robust.echo --tool-arg text=inspector-works --tool-arg mode=plain --format json > /tmp/robust-inspector-call.json
grep -Fq 'inspector-works' /tmp/robust-inspector-call.json
grep -Fq 'robust-mcp' /tmp/robust-inspector-call.json

printf '%s\n' 'robust-mcp-transport: PASS official-php-sdk strict-schema-guard output-enforcement body-limit http-semantics curl+official-ts-sdk+inspector modern-only'
