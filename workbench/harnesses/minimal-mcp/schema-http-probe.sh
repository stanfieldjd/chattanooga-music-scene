#!/usr/bin/env bash
set -euo pipefail

endpoint='http://127.0.0.1:8091/index.php?rest_route=%2Fminimal-mcp%2Fv1%2Fmcp'
app_password="$(php /tmp/wp-cli.phar user application-password create admin robust-mcp-schema-ci --porcelain --path=/tmp/wordpress)"
headers=(
  --user "admin:${app_password}"
  -H 'Content-Type: application/json'
  -H 'Accept: application/json, text/event-stream'
  -H 'MCP-Protocol-Version: 2026-07-28'
)
meta='"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"robust-mcp-schema-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}'

php -S 127.0.0.1:8091 -t /tmp/wordpress >/tmp/robust-mcp-schema-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -fsS 'http://127.0.0.1:8091/index.php?rest_route=%2F' >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-mcp-schema-http.log >&2
    exit 1
  fi
  sleep 1
done

assert_error() {
  local file="$1" expected="$2"
  php -r '$d=json_decode(file_get_contents($argv[1]),true); if (($d["error"]["code"]??null)!==(int)$argv[2]) {fwrite(STDERR,file_get_contents($argv[1])); exit(1);}' "$file" "$expected"
}

cat > /tmp/robust-schema-list.json <<JSON
{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{${meta}}}
JSON
curl -fsS "${headers[@]}" -H 'Mcp-Method: tools/list' --data-binary @/tmp/robust-schema-list.json "$endpoint" > /tmp/robust-schema-list-response.json
php -r '
$d=json_decode(file_get_contents("/tmp/robust-schema-list-response.json"),true); $tools=$d["result"]["tools"]??[];
$names=array_map(static fn($tool)=>$tool["name"]??"",$tools); sort($names,SORT_STRING);
if ($names!==["fixture.bad-output","fixture.echo","fixture.schema","probe.site"]) {fwrite(STDERR,json_encode($names)); exit(1);}
$schema=null; foreach($tools as $tool){if(($tool["name"]??"")==="fixture.schema"){$schema=$tool;break;}}
if (($schema["inputSchema"]["$defs"]["payload"]["allOf"][0]["if"]["properties"]["mode"]["const"]??"")!=="text") exit(2);
'

cat > /tmp/robust-schema-valid-text.json <<JSON
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"fixture.schema","arguments":{"payload":{"mode":"text","value":"hello"}},${meta}}}
JSON
curl -fsS "${headers[@]}" -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.schema' --data-binary @/tmp/robust-schema-valid-text.json "$endpoint" > /tmp/robust-schema-valid-text-response.json
php -r '$d=json_decode(file_get_contents("/tmp/robust-schema-valid-text-response.json"),true);$s=$d["result"]["structuredContent"]??null;if(($d["result"]["isError"]??true)!==false||($s["mode"]??"")!=="text"||($s["value"]??"")!=="hello") exit(1);'

cat > /tmp/robust-schema-valid-count.json <<JSON
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"fixture.schema","arguments":{"payload":{"mode":"count","value":7}},${meta}}}
JSON
curl -fsS "${headers[@]}" -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.schema' --data-binary @/tmp/robust-schema-valid-count.json "$endpoint" > /tmp/robust-schema-valid-count-response.json
php -r '$d=json_decode(file_get_contents("/tmp/robust-schema-valid-count-response.json"),true);$s=$d["result"]["structuredContent"]??null;if(($d["result"]["isError"]??true)!==false||($s["mode"]??"")!=="count"||($s["value"]??null)!==7) exit(1);'

# Conditional validation: text requires a string of length >=2.
cat > /tmp/robust-schema-invalid-text.json <<JSON
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"fixture.schema","arguments":{"payload":{"mode":"text","value":"x"}},${meta}}}
JSON
invalid_text_code="$(curl -sS -o /tmp/robust-schema-invalid-text-response.json -w '%{http_code}' "${headers[@]}" -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.schema' --data-binary @/tmp/robust-schema-invalid-text.json "$endpoint")"
test "$invalid_text_code" = '200'
assert_error /tmp/robust-schema-invalid-text-response.json -32602

# Conditional validation: count requires a positive integer.
cat > /tmp/robust-schema-invalid-count.json <<JSON
{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"fixture.schema","arguments":{"payload":{"mode":"count","value":0}},${meta}}}
JSON
invalid_count_code="$(curl -sS -o /tmp/robust-schema-invalid-count-response.json -w '%{http_code}' "${headers[@]}" -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.schema' --data-binary @/tmp/robust-schema-invalid-count.json "$endpoint")"
test "$invalid_count_code" = '200'
assert_error /tmp/robust-schema-invalid-count-response.json -32602

# Exact JSON shape matters: payload must be an object; [] cannot collapse into {} during PHP decoding.
cat > /tmp/robust-schema-array-payload.json <<JSON
{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"fixture.schema","arguments":{"payload":[]},${meta}}}
JSON
array_payload_code="$(curl -sS -o /tmp/robust-schema-array-payload-response.json -w '%{http_code}' "${headers[@]}" -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.schema' --data-binary @/tmp/robust-schema-array-payload.json "$endpoint")"
test "$array_payload_code" = '200'
assert_error /tmp/robust-schema-array-payload-response.json -32602

# Invalid callback output must be contained and converted to a tool-level error.
cat > /tmp/robust-schema-bad-output.json <<JSON
{"jsonrpc":"2.0","id":7,"method":"tools/call","params":{"name":"fixture.bad-output","arguments":{},${meta}}}
JSON
curl -fsS "${headers[@]}" -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.bad-output' --data-binary @/tmp/robust-schema-bad-output.json "$endpoint" > /tmp/robust-schema-bad-output-response.json
php -r '
$d=json_decode(file_get_contents("/tmp/robust-schema-bad-output-response.json"),true);$r=$d["result"]??[];
if (($r["isError"]??false)!==true) exit(1);
if (array_key_exists("structuredContent",$r)) exit(2);
$text=$r["content"][0]["text"]??""; if (strpos($text,"output failed schema validation")===false) exit(3);
'

# Request size is bounded at the transport before JSON processing.
python3 - <<'PY'
import json
meta={
 "io.modelcontextprotocol/protocolVersion":"2026-07-28",
 "io.modelcontextprotocol/clientInfo":{"name":"robust-mcp-schema-ci","version":"1.0.0"},
 "io.modelcontextprotocol/clientCapabilities":{}
}
body={"jsonrpc":"2.0","id":8,"method":"tools/call","params":{"name":"fixture.schema","arguments":{"payload":{"mode":"text","value":"x"*(1024*1024)}},"_meta":meta}}
with open('/tmp/robust-schema-oversize.json','w') as f: json.dump(body,f,separators=(',',':'))
PY
oversize_code="$(curl -sS -o /tmp/robust-schema-oversize-response.json -w '%{http_code}' "${headers[@]}" -H 'Mcp-Method: tools/call' -H 'Mcp-Name: fixture.schema' --data-binary @/tmp/robust-schema-oversize.json "$endpoint")"
test "$oversize_code" = '413'

printf '%s\n' 'robust-mcp-schema-http: PASS local-ref oneOf conditional exact-json-shape output-containment request-limit'
