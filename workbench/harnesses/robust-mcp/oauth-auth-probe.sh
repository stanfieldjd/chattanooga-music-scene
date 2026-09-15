#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
idp='http://127.0.0.1:8099'
endpoint='http://127.0.0.1:8100/mcp'
resource='http://127.0.0.1:8100/mcp'
audience='urn:chattanooga:robust-mcp:chatgpt'
required_scope='mcp:connect'
private_key='/tmp/robust-oauth-private.pem'
bad_private_key='/tmp/robust-oauth-bad-private.pem'
jwks='/tmp/robust-oauth-jwks.json'
bad_jwks='/tmp/robust-oauth-bad-jwks.json'
idp_counter='/tmp/robust-oauth-idp-counter.log'
telemetry='/tmp/robust-oauth-telemetry.log'
sessions='/tmp/robust-oauth-sessions'
cache='/tmp/robust-oauth-cache'
idp_pid=''
mcp_pid=''

cleanup() {
  if [[ "${mcp_pid:-}" =~ ^[1-9][0-9]*$ ]]; then
    kill "$mcp_pid" 2>/dev/null || true
  fi
  if [[ "${idp_pid:-}" =~ ^[1-9][0-9]*$ ]]; then
    kill "$idp_pid" 2>/dev/null || true
  fi
}
trap cleanup EXIT

rm -rf "$sessions" "$cache"
rm -f "$private_key" "$bad_private_key" "$jwks" "$bad_jwks" "$idp_counter" "$telemetry" \
  /tmp/robust-oauth-*.json /tmp/robust-oauth-*.headers /tmp/robust-oauth-*.log
mkdir -p "$sessions" "$cache"

php "$base_dir/oauth-fixture-keygen.php" "$private_key" "$jwks"
php "$base_dir/oauth-fixture-keygen.php" "$bad_private_key" "$bad_jwks"

make_token() {
  local key="$1" issuer="$2" aud="$3" scope="$4" ttl="$5"
  php "$base_dir/oauth-fixture-token.php" "$key" "$issuer" "$aud" "$scope" "$ttl"
}

valid_token="$(make_token "$private_key" "$idp" "$audience" "$required_scope" 3600)"
valid_token_2="$(make_token "$private_key" "$idp" "$audience" "$required_scope" 3600)"
wrong_signature="$(make_token "$bad_private_key" "$idp" "$audience" "$required_scope" 3600)"
wrong_issuer="$(make_token "$private_key" "$idp/wrong" "$audience" "$required_scope" 3600)"
wrong_audience="$(make_token "$private_key" "$idp" 'urn:wrong-audience' "$required_scope" 3600)"
missing_scope="$(make_token "$private_key" "$idp" "$audience" 'other:scope' 3600)"
expired_token="$(make_token "$private_key" "$idp" "$audience" "$required_scope" -60)"

for token in "$valid_token" "$valid_token_2" "$wrong_signature" "$wrong_issuer" "$wrong_audience" "$missing_scope" "$expired_token"; do
  test -n "$token"
  test "${#token}" -gt 100
done

OAUTH_FIXTURE_ISSUER="$idp" \
OAUTH_FIXTURE_JWKS_FILE="$jwks" \
OAUTH_FIXTURE_COUNTER_FILE="$idp_counter" \
php -S 127.0.0.1:8099 "$base_dir/oauth-fixture-router.php" >/tmp/robust-oauth-idp.log 2>&1 &
idp_pid=$!

ROBUST_MCP_AUTH_MODE='oauth-jwt' \
ROBUST_MCP_OAUTH_ISSUER="$idp" \
ROBUST_MCP_OAUTH_AUDIENCE="$audience" \
ROBUST_MCP_OAUTH_SCOPES="$required_scope" \
ROBUST_MCP_OAUTH_RESOURCE="$resource" \
ROBUST_MCP_OAUTH_CACHE_DIR="$cache" \
ROBUST_MCP_OAUTH_CACHE_TTL='900' \
ROBUST_MCP_SESSION_DIR="$sessions" \
ROBUST_MCP_LOG_FILE="$telemetry" \
php -S 127.0.0.1:8100 "$base_dir/server.php" >/tmp/robust-oauth-mcp.log 2>&1 &
mcp_pid=$!

for attempt in $(seq 1 30); do
  idp_code="$(curl -sS -o /tmp/robust-oauth-idp-ready.json -w '%{http_code}' "$idp/.well-known/oauth-authorization-server" || true)"
  mcp_code="$(curl -sS -o /tmp/robust-oauth-ready.json -w '%{http_code}' 'http://127.0.0.1:8100/readyz' || true)"
  if [ "$idp_code" = '200' ] && [ "$mcp_code" = '200' ]; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-oauth-idp.log >&2 || true
    cat /tmp/robust-oauth-mcp.log >&2 || true
    exit 1
  fi
  sleep 1
done

# RFC 9728 metadata is public in OAuth mode and is available at both the root
# well-known path and the resource-specific path used for /mcp resources.
for metadata_path in '/.well-known/oauth-protected-resource' '/.well-known/oauth-protected-resource/mcp'; do
  code="$(curl -sS -o /tmp/robust-oauth-metadata.json -w '%{http_code}' "http://127.0.0.1:8100${metadata_path}")"
  test "$code" = '200'
  php -r '
  $d=json_decode(file_get_contents("/tmp/robust-oauth-metadata.json"),true,512,JSON_THROW_ON_ERROR);
  if (($d["authorization_servers"]??[])!==[$argv[1]]) exit(1);
  if (($d["resource"]??"")!==$argv[2]) exit(2);
  if (($d["scopes_supported"]??[])!==[$argv[3]]) exit(3);
  if (($d["bearer_methods_supported"]??[])!==["header"]) exit(4);
  ' "$idp" "$resource" "$required_scope"
done

common_headers=(
  -H 'Content-Type: application/json'
  -H 'Accept: application/json, text/event-stream'
  -H 'MCP-Protocol-Version: 2026-07-28'
  -H 'Mcp-Method: server/discover'
)
cat > /tmp/robust-oauth-discover.json <<'JSON'
{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"oauth-redteam","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

# OAuth challenges must point ChatGPT/clients at the protected-resource metadata
# and advertise the required scope.
missing_code="$(curl -sS -D /tmp/robust-oauth-missing.headers -o /tmp/robust-oauth-missing.json -w '%{http_code}' \
  "${common_headers[@]}" --data-binary @/tmp/robust-oauth-discover.json "$endpoint")"
test "$missing_code" = '401'
grep -Eiq '^WWW-Authenticate: Bearer .*resource_metadata="http://127\.0\.0\.1:8100/\.well-known/oauth-protected-resource"' /tmp/robust-oauth-missing.headers
grep -Eiq 'scope="mcp:connect"' /tmp/robust-oauth-missing.headers

malformed_code="$(curl -sS -D /tmp/robust-oauth-malformed.headers -o /tmp/robust-oauth-malformed.json -w '%{http_code}' \
  "${common_headers[@]}" -H 'Authorization: Basic not-oauth' \
  --data-binary @/tmp/robust-oauth-discover.json "$endpoint")"
# The SDK follows the Bearer challenge model for malformed credentials: HTTP
# 401 carries error="invalid_request" rather than using a separate 400 status.
test "$malformed_code" = '401'
grep -Eiq 'error="invalid_request"' /tmp/robust-oauth-malformed.headers

# CORS preflight must never require a bearer token.
preflight_code="$(curl -sS -o /tmp/robust-oauth-preflight.txt -w '%{http_code}' \
  -X OPTIONS -H 'Origin: https://chatgpt.com' -H 'Access-Control-Request-Method: POST' "$endpoint")"
test "$preflight_code" = '204'

# A valid token fills both OIDC-discovery and JWKS caches.
valid_code="$(curl -sS -D /tmp/robust-oauth-valid.headers -o /tmp/robust-oauth-valid.json -w '%{http_code}' \
  "${common_headers[@]}" -H "Authorization: Bearer ${valid_token}" \
  --data-binary @/tmp/robust-oauth-discover.json "$endpoint")"
test "$valid_code" = '200'
php -r '$d=json_decode(file_get_contents("/tmp/robust-oauth-valid.json"),true,512,JSON_THROW_ON_ERROR); if (($d["result"]["supportedVersions"]??[])!==["2026-07-28"]) exit(1);'
grep -Fq '/.well-known/oauth-authorization-server' "$idp_counter"
grep -Fq '/jwks' "$idp_counter"

# Stop the identity provider. Every remaining validation must use the persistent
# verified metadata/JWKS cache, proving request workers do not depend on a live
# identity-provider round trip after warm-up.
kill "$idp_pid" 2>/dev/null || true
wait "$idp_pid" 2>/dev/null || true
idp_pid=''

assert_status() {
  local expected="$1" token="$2" label="$3"
  local code
  code="$(curl -sS -D "/tmp/robust-oauth-${label}.headers" -o "/tmp/robust-oauth-${label}.json" -w '%{http_code}' \
    "${common_headers[@]}" -H "Authorization: Bearer ${token}" \
    --data-binary @/tmp/robust-oauth-discover.json "$endpoint")"
  test "$code" = "$expected"
}

assert_status 401 "$wrong_signature" 'wrong-signature'
assert_status 401 "$wrong_issuer" 'wrong-issuer'
assert_status 401 "$wrong_audience" 'wrong-audience'
assert_status 401 "$expired_token" 'expired'
assert_status 403 "$missing_scope" 'missing-scope'
grep -Eiq 'error="insufficient_scope"' /tmp/robust-oauth-missing-scope.headers
grep -Eiq 'scope="mcp:connect"' /tmp/robust-oauth-missing-scope.headers
assert_status 200 "$valid_token_2" 'cached-valid'

# The same OAuth resource must work over modern, legacy, and automatic protocol
# negotiation after the IdP has gone offline.
MCP_ENDPOINT="$endpoint" MCP_OAUTH_TOKEN="$valid_token_2" MCP_EXPECTED_ERA=modern node "$base_dir/oauth-sdk-probe.mjs"
MCP_ENDPOINT="$endpoint" MCP_OAUTH_TOKEN="$valid_token_2" MCP_EXPECTED_ERA=legacy node "$base_dir/oauth-sdk-probe.mjs"
MCP_ENDPOINT="$endpoint" MCP_OAUTH_TOKEN="$valid_token_2" MCP_EXPECTED_ERA=auto node "$base_dir/oauth-sdk-probe.mjs"

# All MCP endpoint methods remain protected in OAuth mode. Valid credentials
# reach transport semantics; missing credentials do not.
get_missing="$(curl -sS -o /tmp/robust-oauth-get-missing.json -w '%{http_code}' "$endpoint")"
test "$get_missing" = '401'
get_valid="$(curl -sS -D /tmp/robust-oauth-get-valid.headers -o /tmp/robust-oauth-get-valid.json -w '%{http_code}' \
  -H "Authorization: Bearer ${valid_token_2}" "$endpoint")"
test "$get_valid" = '405'
grep -Eiq '^Allow: POST, DELETE, OPTIONS' /tmp/robust-oauth-get-valid.headers

# Credentials and JWTs must never appear in server or structured telemetry logs.
for token in "$valid_token" "$valid_token_2" "$wrong_signature" "$wrong_issuer" "$wrong_audience" "$missing_scope" "$expired_token"; do
  if grep -Fq "$token" /tmp/robust-oauth-mcp.log "$telemetry" 2>/dev/null; then
    echo 'OAuth access token leaked to logs' >&2
    exit 1
  fi
done

# The identity provider stayed offline during all client calls above. If cache
# use failed, those calls would have failed instead of silently reaching it.
if curl -fsS --max-time 1 "$idp/.well-known/oauth-authorization-server" >/dev/null 2>&1; then
  echo 'OAuth fixture identity provider unexpectedly remained reachable' >&2
  exit 1
fi

printf '%s\n' 'robust-mcp-oauth: PASS rfc9728 jwt-signature issuer audience expiration scopes preflight cache-offline modern+legacy+auto no-token-logs'
