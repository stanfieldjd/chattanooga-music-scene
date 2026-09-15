#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
endpoint='http://127.0.0.1:8096/mcp'
telemetry='/tmp/robust-mcp-bearer-dual-telemetry.jsonl'
session_dir='/tmp/robust-mcp-bearer-dual-sessions'
rm -f "$telemetry"
rm -rf "$session_dir"

token="$(php -r 'echo bin2hex(random_bytes(32));')"
digest="$(printf '%s' "$token" | sha256sum | awk '{print $1}')"
wrong='0000000000000000000000000000000000000000000000000000000000000000'

test "${#token}" -eq 64
printf '%s' "$token" | grep -Eq '^[a-f0-9]{64}$'
printf '%s' "$digest" | grep -Eq '^[a-f0-9]{64}$'

ROBUST_MCP_BEARER_SHA256="$digest" \
ROBUST_MCP_LOG_FILE="$telemetry" \
ROBUST_MCP_SESSION_DIR="$session_dir" \
php -S 127.0.0.1:8096 "$base_dir/server.php" >/tmp/robust-mcp-bearer-dual-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -sS -o /dev/null -w '%{http_code}' 'http://127.0.0.1:8096/healthz' | grep -q '^200$'; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-mcp-bearer-dual-http.log >&2
    exit 1
  fi
  sleep 1
done

common=( -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' )
meta='"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"bearer-dual-ci","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}'
cat > /tmp/bearer-dual-discover.json <<JSON
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{${meta}}}
JSON

# No MCP transport method becomes an auth bypass. OPTIONS remains preflight-only.
post_missing="$(curl -sS -o /tmp/bearer-dual-post-missing.json -w '%{http_code}' "${common[@]}" --data-binary @/tmp/bearer-dual-discover.json "$endpoint")"
get_missing="$(curl -sS -o /tmp/bearer-dual-get-missing.json -w '%{http_code}' "$endpoint")"
delete_missing="$(curl -sS -o /tmp/bearer-dual-delete-missing.json -w '%{http_code}' -X DELETE "$endpoint")"
test "$post_missing" = '401'
test "$get_missing" = '401'
test "$delete_missing" = '401'

options_code="$(curl -sS -o /tmp/bearer-dual-options -w '%{http_code}' -X OPTIONS "$endpoint")"
test "$options_code" = '204'

# Invalid token, weak token, and Basic never fall back to another auth path.
wrong_code="$(curl -sS -o /tmp/bearer-dual-wrong.json -w '%{http_code}' "${common[@]}" -H "Authorization: Bearer ${wrong}" --data-binary @/tmp/bearer-dual-discover.json "$endpoint")"
weak_code="$(curl -sS -o /tmp/bearer-dual-weak.json -w '%{http_code}' "${common[@]}" -H 'Authorization: Bearer password' --data-binary @/tmp/bearer-dual-discover.json "$endpoint")"
basic_code="$(curl -sS -o /tmp/bearer-dual-basic.json -w '%{http_code}' "${common[@]}" -H 'Authorization: Basic YWRtaW46cGFzc3dvcmQ=' --data-binary @/tmp/bearer-dual-discover.json "$endpoint")"
test "$wrong_code" = '401'
test "$weak_code" = '401'
test "$basic_code" = '401'

# Authentication occurs before JSON parsing.
printf '{' > /tmp/bearer-dual-malformed.txt
malformed_missing="$(curl -sS -o /tmp/bearer-dual-malformed-missing.json -w '%{http_code}' "${common[@]}" --data-binary @/tmp/bearer-dual-malformed.txt "$endpoint")"
malformed_valid="$(curl -sS -o /tmp/bearer-dual-malformed-valid.json -w '%{http_code}' "${common[@]}" -H "Authorization: Bearer ${token}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' --data-binary @/tmp/bearer-dual-malformed.txt "$endpoint")"
test "$malformed_missing" = '401'
test "$malformed_valid" = '400'
php -r '$d=json_decode(file_get_contents("/tmp/bearer-dual-malformed-valid.json"),true); if (($d["error"]["code"]??null)!==-32700) exit(1);'

# A valid credential reaches transport semantics rather than bypassing them.
valid_get="$(curl -sS -D /tmp/bearer-dual-valid-get-headers.txt -o /tmp/bearer-dual-valid-get.json -w '%{http_code}' -H "Authorization: Bearer ${token}" "$endpoint")"
test "$valid_get" = '405'
grep -Eiq '^Allow: POST, DELETE, OPTIONS' /tmp/bearer-dual-valid-get-headers.txt

valid_discover="$(curl -sS -o /tmp/bearer-dual-valid-discover.json -w '%{http_code}' "${common[@]}" -H "Authorization: Bearer ${token}" -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' --data-binary @/tmp/bearer-dual-discover.json "$endpoint")"
test "$valid_discover" = '200'

# Official client must work in both protocol eras through the same bearer boundary.
MCP_ENDPOINT="$endpoint" MCP_BEARER_TOKEN="$token" node "$base_dir/bearer-sdk-probe.mjs"
MCP_ENDPOINT="$endpoint" MCP_BEARER_TOKEN="$token" node "$base_dir/bearer-legacy-sdk-probe.mjs"

# Inspector modern + legacy with the same explicit Authorization header.
authorization="Bearer ${token}"
MCP_ENDPOINT="$endpoint" MCP_AUTHORIZATION="$authorization" php -r '
foreach (["modern","legacy"] as $era) {
  file_put_contents("/tmp/bearer-dual-inspector-{$era}.json", json_encode(["mcpServers"=>["robust"=>["type"=>"http","url"=>getenv("MCP_ENDPOINT"),"headers"=>["Authorization"=>getenv("MCP_AUTHORIZATION")],"protocolEra"=>$era]]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
'
npx --no-install mcp-inspector --cli --config /tmp/bearer-dual-inspector-modern.json --server robust --method tools/list --format json > /tmp/bearer-dual-inspector-modern-list.json
npx --no-install mcp-inspector --cli --config /tmp/bearer-dual-inspector-legacy.json --server robust --method tools/list --format json > /tmp/bearer-dual-inspector-legacy-list.json
grep -Fq 'robust.echo' /tmp/bearer-dual-inspector-modern-list.json
grep -Fq 'robust.echo' /tmp/bearer-dual-inspector-legacy-list.json

# No plaintext credential may reach either structured telemetry or server logs.
for file in "$telemetry" /tmp/robust-mcp-bearer-dual-http.log; do
  if grep -Fq "$token" "$file"; then
    echo "plaintext bearer token leaked to ${file}" >&2
    exit 1
  fi
done
if grep -Eiq 'authorization|cookie|"arguments"' "$telemetry"; then
  echo 'sensitive metadata leaked into telemetry' >&2
  exit 1
fi

printf '%s\n' 'robust-mcp-bearer-dual: PASS exclusive-auth all-mcp-methods auth-before-parse modern+legacy official-sdk inspector no-secret-logs'
