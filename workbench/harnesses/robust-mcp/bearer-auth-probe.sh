#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
endpoint='http://127.0.0.1:8094/mcp'
counter='/tmp/robust-mcp-bearer-handler-count'
rm -f "$counter"

token="$(php -r 'echo bin2hex(random_bytes(32));')"
digest="$(printf '%s' "$token" | sha256sum | awk '{print $1}')"

test "${#token}" -eq 64
printf '%s' "$token" | grep -Eq '^[a-f0-9]{64}$'
printf '%s' "$digest" | grep -Eq '^[a-f0-9]{64}$'

# Configuration accepts only the digest shape used by the previously proven
# manual-bearer design. The plaintext token never enters the server process.
php -r '
require $argv[1]."/vendor/autoload.php";
$f=new Nyholm\Psr7\Factory\Psr17Factory();
try {
  new Chattanooga\RobustMcp\ManualBearerAuthMiddleware("not-a-digest", $f, $f);
} catch (InvalidArgumentException) {
  exit(0);
}
exit(1);
' "$base_dir"

ROBUST_MCP_AUTH_MODE='manual-bearer' \
ROBUST_MCP_BEARER_SHA256="$digest" \
ROBUST_MCP_COUNTER_FILE="$counter" \
php -S 127.0.0.1:8094 "$base_dir/server.php" >/tmp/robust-mcp-bearer-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -sS -o /dev/null -w '%{http_code}' 'http://127.0.0.1:8094/not-mcp' | grep -q '^404$'; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-mcp-bearer-http.log >&2
    exit 1
  fi
  sleep 1
done

common_headers=(
  -H 'Content-Type: application/json'
  -H 'Accept: application/json, text/event-stream'
)
meta='"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"robust-mcp-bearer-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}'

cat > /tmp/robust-bearer-discover.json <<JSON
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{${meta}}}
JSON

missing_code="$(curl -sS -D /tmp/robust-bearer-missing-headers.txt -o /tmp/robust-bearer-missing.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$missing_code" = '401'
grep -Fq '"error":"invalid_token"' /tmp/robust-bearer-missing.json
grep -Eiq '^WWW-Authenticate: Bearer realm="chattanooga-robust-mcp"' /tmp/robust-bearer-missing-headers.txt

weak_code="$(curl -sS -D /tmp/robust-bearer-weak-headers.txt -o /tmp/robust-bearer-weak.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'Authorization: Bearer correct-horse-battery-staple-this-is-not-a-machine-secret' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$weak_code" = '401'
grep -Fq 'error="invalid_token"' /tmp/robust-bearer-weak-headers.txt

wrong_token='0000000000000000000000000000000000000000000000000000000000000000'
wrong_code="$(curl -sS -o /tmp/robust-bearer-wrong.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${wrong_token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$wrong_code" = '401'

# Other HTTP auth schemes are never fallback credentials in manual-bearer mode.
basic_code="$(curl -sS -o /tmp/robust-bearer-basic.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'Authorization: Basic YWRtaW46YXBwbGljYXRpb24tcGFzc3dvcmQ=' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$basic_code" = '401'

valid_code="$(curl -sS -o /tmp/robust-bearer-valid.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$valid_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/robust-bearer-valid.json"),true); if (($d["result"]["supportedVersions"]??[])!==["2026-07-28"]) exit(1);'

# Authentication scheme names are case-insensitive; token bytes are not.
lowercase_code="$(curl -sS -o /tmp/robust-bearer-lowercase.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: bearer ${token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$lowercase_code" = '200'

uppercase_token="$(printf '%s' "$token" | tr '[:lower:]' '[:upper:]')"
if [ "$uppercase_token" != "$token" ]; then
  uppercase_code="$(curl -sS -o /tmp/robust-bearer-uppercase.json -w '%{http_code}' \
    "${common_headers[@]}" -H "Authorization: Bearer ${uppercase_token}" \
    -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
    --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
  test "$uppercase_code" = '401'
fi

# Authentication is evaluated before media parsing and before JSON parsing.
printf '{' > /tmp/robust-bearer-invalid-json.txt
malformed_missing_code="$(curl -sS -o /tmp/robust-bearer-malformed-missing.json -w '%{http_code}' \
  "${common_headers[@]}" --data-binary @/tmp/robust-bearer-invalid-json.txt "$endpoint")"
test "$malformed_missing_code" = '401'

malformed_valid_code="$(curl -sS -o /tmp/robust-bearer-malformed-valid.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' \
  --data-binary @/tmp/robust-bearer-invalid-json.txt "$endpoint")"
test "$malformed_valid_code" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/robust-bearer-malformed-valid.json"),true); if (($d["error"]["code"]??null)!==-32700) exit(1);'

unauthorized_media_code="$(curl -sS -o /tmp/robust-bearer-media-unauthorized.json -w '%{http_code}' \
  -H 'Content-Type: text/plain' -H 'Accept: application/json, text/event-stream' \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$unauthorized_media_code" = '401'

authorized_media_code="$(curl -sS -o /tmp/robust-bearer-media-authorized.json -w '%{http_code}' \
  -H 'Content-Type: text/plain' -H 'Accept: application/json, text/event-stream' -H "Authorization: Bearer ${token}" \
  --data-binary @/tmp/robust-bearer-discover.json "$endpoint")"
test "$authorized_media_code" = '415'

# GET remains a transport 405; manual bearer protects the administrative POST.
get_code="$(curl -sS -D /tmp/robust-bearer-get-headers.txt -o /tmp/robust-bearer-get.json -w '%{http_code}' "$endpoint")"
test "$get_code" = '405'
grep -Eiq '^Allow: POST' /tmp/robust-bearer-get-headers.txt

# An unauthorized tools/call cannot reach the business callback.
cat > /tmp/robust-bearer-call.json <<JSON
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"robust.echo","arguments":{"text":"bearer-curl-works","mode":"plain","tags":["auth"]},${meta}}}
JSON
unauthorized_call_code="$(curl -sS -o /tmp/robust-bearer-call-unauthorized.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${wrong_token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: robust.echo' -H 'Mcp-Param-Text: bearer-curl-works' \
  --data-binary @/tmp/robust-bearer-call.json "$endpoint")"
test "$unauthorized_call_code" = '401'
test ! -e "$counter"

authorized_call_code="$(curl -sS -o /tmp/robust-bearer-call-valid.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${token}" \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' -H 'Mcp-Name: robust.echo' -H 'Mcp-Param-Text: bearer-curl-works' \
  --data-binary @/tmp/robust-bearer-call.json "$endpoint")"
test "$authorized_call_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/robust-bearer-call-valid.json"),true); if (($d["result"]["structuredContent"]["echo"]??"")!=="bearer-curl-works") exit(1);'
test "$(wc -l < "$counter")" = '1'

MCP_ENDPOINT="$endpoint" MCP_BEARER_TOKEN="$token" node "$base_dir/bearer-sdk-probe.mjs"

authorization="Bearer ${token}"
MCP_ENDPOINT="$endpoint" MCP_AUTHORIZATION="$authorization" php -r '
file_put_contents("/tmp/robust-bearer-inspector.json", json_encode(["mcpServers"=>["robust"=>["type"=>"http","url"=>getenv("MCP_ENDPOINT"),"headers"=>["Authorization"=>getenv("MCP_AUTHORIZATION")],"protocolEra"=>"modern"]]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
'

npx --no-install mcp-inspector --cli --config /tmp/robust-bearer-inspector.json --server robust --method tools/list --format json > /tmp/robust-bearer-inspector-list.json
grep -Fq 'robust.echo' /tmp/robust-bearer-inspector-list.json

npx --no-install mcp-inspector --cli --config /tmp/robust-bearer-inspector.json --server robust \
  --method tools/call --tool-name robust.echo --tool-arg text=inspector-bearer-works --tool-arg mode=plain --format json > /tmp/robust-bearer-inspector-call.json
grep -Fq 'inspector-bearer-works' /tmp/robust-bearer-inspector-call.json

grep -Fq "$token" /tmp/robust-mcp-bearer-http.log && {
  echo 'plaintext bearer token leaked to server log' >&2
  exit 1
}

printf '%s\n' 'robust-mcp-manual-bearer: PASS 256-bit-random digest-at-rest exclusive-bearer auth-before-parse case-sensitive-token callback-blocked curl+official-sdk+inspector'
