#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
idp='http://127.0.0.1:8101'
endpoint='http://127.0.0.1:8102/mcp'
resource="$endpoint"
audience='urn:chattanooga:robust-mcp:chatgpt-refresh'
redirect_uri='https://chatgpt.com/connector/oauth/robust-ci'
private_key='/tmp/robust-refresh-private.pem'
jwks='/tmp/robust-refresh-jwks.json'
state_file='/tmp/robust-refresh-state.json'
counter='/tmp/robust-refresh-counter.log'
sessions='/tmp/robust-refresh-sessions'
cache='/tmp/robust-refresh-cache'
telemetry='/tmp/robust-refresh-telemetry.log'
idp_pid=''
mcp_pid=''

cleanup() {
  if [[ "${mcp_pid:-}" =~ ^[1-9][0-9]*$ ]]; then kill "$mcp_pid" 2>/dev/null || true; fi
  if [[ "${idp_pid:-}" =~ ^[1-9][0-9]*$ ]]; then kill "$idp_pid" 2>/dev/null || true; fi
}
trap cleanup EXIT

rm -rf "$sessions" "$cache"
rm -f "$private_key" "$jwks" "$state_file" "$counter" "$telemetry" /tmp/robust-refresh-*.json /tmp/robust-refresh-*.headers /tmp/robust-refresh-*.txt /tmp/robust-refresh-*.log
mkdir -p "$sessions" "$cache"
php "$base_dir/oauth-fixture-keygen.php" "$private_key" "$jwks"

OAUTH_REFRESH_ISSUER="$idp" \
OAUTH_REFRESH_JWKS_FILE="$jwks" \
OAUTH_REFRESH_PRIVATE_KEY="$private_key" \
OAUTH_REFRESH_STATE_FILE="$state_file" \
OAUTH_REFRESH_AUDIENCE="$audience" \
OAUTH_REFRESH_RESOURCE="$resource" \
OAUTH_REFRESH_REDIRECT_URI="$redirect_uri" \
OAUTH_REFRESH_COUNTER_FILE="$counter" \
php -S 127.0.0.1:8101 "$base_dir/oauth-refresh-fixture-router.php" >/tmp/robust-refresh-idp.log 2>&1 &
idp_pid=$!

ROBUST_MCP_AUTH_MODE='oauth-jwt' \
ROBUST_MCP_OAUTH_ISSUER="$idp" \
ROBUST_MCP_OAUTH_AUDIENCE="$audience" \
ROBUST_MCP_OAUTH_SCOPES='mcp:connect' \
ROBUST_MCP_OAUTH_RESOURCE="$resource" \
ROBUST_MCP_OAUTH_CACHE_DIR="$cache" \
ROBUST_MCP_OAUTH_CACHE_TTL='900' \
ROBUST_MCP_SESSION_DIR="$sessions" \
ROBUST_MCP_LOG_FILE="$telemetry" \
php -S 127.0.0.1:8102 "$base_dir/server.php" >/tmp/robust-refresh-mcp.log 2>&1 &
mcp_pid=$!

for attempt in $(seq 1 30); do
  idp_code="$(curl -sS -o /tmp/robust-refresh-discovery.json -w '%{http_code}' "$idp/.well-known/oauth-authorization-server" || true)"
  mcp_code="$(curl -sS -o /tmp/robust-refresh-ready.json -w '%{http_code}' 'http://127.0.0.1:8102/readyz' || true)"
  if [ "$idp_code" = '200' ] && [ "$mcp_code" = '200' ]; then break; fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/robust-refresh-idp.log >&2 || true
    cat /tmp/robust-refresh-mcp.log >&2 || true
    exit 1
  fi
  sleep 1
done

php -r '
$d=json_decode(file_get_contents("/tmp/robust-refresh-discovery.json"),true,512,JSON_THROW_ON_ERROR);
foreach (["authorization_code","refresh_token"] as $grant) {if (!in_array($grant,$d["grant_types_supported"]??[],true)) exit(1);}
if (!in_array("S256",$d["code_challenge_methods_supported"]??[],true)) exit(2);
foreach (["mcp:connect","offline_access"] as $scope) {if (!in_array($scope,$d["scopes_supported"]??[],true)) exit(3);}
if (($d["token_endpoint_auth_methods_supported"]??[])!==["none"]) exit(4);
'

verifier="$(php -r 'echo rtrim(strtr(base64_encode(random_bytes(48)), "+/", "-_"), "=");')"
challenge="$(php -r 'echo rtrim(strtr(base64_encode(hash("sha256", $argv[1], true)), "+/", "-_"), "=");' "$verifier")"
state="$(php -r 'echo bin2hex(random_bytes(16));')"
test "${#verifier}" -ge 43

curl -sS -D /tmp/robust-refresh-authorize.headers -o /tmp/robust-refresh-authorize.txt \
  -G "$idp/authorize" \
  --data-urlencode 'response_type=code' \
  --data-urlencode 'client_id=chatgpt-ci-client' \
  --data-urlencode "redirect_uri=${redirect_uri}" \
  --data-urlencode 'scope=mcp:connect offline_access' \
  --data-urlencode "resource=${resource}" \
  --data-urlencode "state=${state}" \
  --data-urlencode "code_challenge=${challenge}" \
  --data-urlencode 'code_challenge_method=S256'
grep -Eq '^HTTP/1\.[01] 302 ' /tmp/robust-refresh-authorize.headers
location="$(awk -F': ' 'tolower($1)=="location"{gsub("\r", "", $2); print $2}' /tmp/robust-refresh-authorize.headers | tail -n1)"
test -n "$location"
readarray -t parsed < <(php -r '$q=[];parse_str((string)parse_url($argv[1],PHP_URL_QUERY),$q);echo ($q["code"]??"")."\n".($q["state"]??"")."\n";' "$location")
code="${parsed[0]:-}"
returned_state="${parsed[1]:-}"
test -n "$code"
test "$returned_state" = "$state"

# The provider stores only a hash of the one-time authorization code.
if grep -Fq "$code" "$state_file"; then
  echo 'plaintext authorization code persisted in fixture state' >&2
  exit 1
fi

curl -sS -o /tmp/robust-refresh-token1.json -w '%{http_code}' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=authorization_code' \
  --data-urlencode "code=${code}" \
  --data-urlencode 'client_id=chatgpt-ci-client' \
  --data-urlencode "redirect_uri=${redirect_uri}" \
  --data-urlencode "code_verifier=${verifier}" \
  "$idp/token" > /tmp/robust-refresh-token1.code
test "$(cat /tmp/robust-refresh-token1.code)" = '200'
readarray -t token1 < <(php -r '$d=json_decode(file_get_contents("/tmp/robust-refresh-token1.json"),true,512,JSON_THROW_ON_ERROR); echo ($d["access_token"]??"")."\n".($d["refresh_token"]??"")."\n".($d["scope"]??"")."\n";' )
access1="${token1[0]:-}"
refresh1="${token1[1]:-}"
scope1="${token1[2]:-}"
test -n "$access1"
test -n "$refresh1"
[[ " $scope1 " == *' mcp:connect '* ]]
[[ " $scope1 " == *' offline_access '* ]]

# Authorization codes are one-time. Replaying the same code must fail.
replay_code="$(curl -sS -o /tmp/robust-refresh-code-replay.json -w '%{http_code}' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=authorization_code' \
  --data-urlencode "code=${code}" \
  --data-urlencode 'client_id=chatgpt-ci-client' \
  --data-urlencode "redirect_uri=${redirect_uri}" \
  --data-urlencode "code_verifier=${verifier}" \
  "$idp/token")"
test "$replay_code" = '400'
grep -Fq '"error":"invalid_grant"' /tmp/robust-refresh-code-replay.json

# Refresh credentials are hashed at rest as well.
if grep -Fq "$refresh1" "$state_file"; then
  echo 'plaintext refresh token persisted in fixture state' >&2
  exit 1
fi

MCP_ENDPOINT="$endpoint" MCP_OAUTH_TOKEN="$access1" MCP_EXPECTED_ERA=auto node "$base_dir/oauth-sdk-probe.mjs"

curl -sS -o /tmp/robust-refresh-token2.json -w '%{http_code}' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=refresh_token' \
  --data-urlencode "refresh_token=${refresh1}" \
  --data-urlencode 'client_id=chatgpt-ci-client' \
  --data-urlencode "resource=${resource}" \
  "$idp/token" > /tmp/robust-refresh-token2.code
test "$(cat /tmp/robust-refresh-token2.code)" = '200'
readarray -t token2 < <(php -r '$d=json_decode(file_get_contents("/tmp/robust-refresh-token2.json"),true,512,JSON_THROW_ON_ERROR); echo ($d["access_token"]??"")."\n".($d["refresh_token"]??"")."\n";' )
access2="${token2[0]:-}"
refresh2="${token2[1]:-}"
test -n "$access2"
test -n "$refresh2"
test "$access2" != "$access1"
test "$refresh2" != "$refresh1"

# Refresh tokens rotate; the previous token is invalid immediately after use.
old_refresh_code="$(curl -sS -o /tmp/robust-refresh-old-replay.json -w '%{http_code}' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=refresh_token' \
  --data-urlencode "refresh_token=${refresh1}" \
  --data-urlencode 'client_id=chatgpt-ci-client' \
  --data-urlencode "resource=${resource}" \
  "$idp/token")"
test "$old_refresh_code" = '400'
MCP_ENDPOINT="$endpoint" MCP_OAUTH_TOKEN="$access2" MCP_EXPECTED_ERA=modern node "$base_dir/oauth-sdk-probe.mjs"

# Prove a second refresh, not merely a single rotation, to model sustained ChatGPT connectivity.
curl -sS -o /tmp/robust-refresh-token3.json \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=refresh_token' \
  --data-urlencode "refresh_token=${refresh2}" \
  --data-urlencode 'client_id=chatgpt-ci-client' \
  --data-urlencode "resource=${resource}" \
  "$idp/token"
access3="$(php -r '$d=json_decode(file_get_contents("/tmp/robust-refresh-token3.json"),true,512,JSON_THROW_ON_ERROR);echo $d["access_token"]??"";')"
refresh3="$(php -r '$d=json_decode(file_get_contents("/tmp/robust-refresh-token3.json"),true,512,JSON_THROW_ON_ERROR);echo $d["refresh_token"]??"";')"
test -n "$access3"
test -n "$refresh3"
test "$access3" != "$access2"
test "$refresh3" != "$refresh2"
MCP_ENDPOINT="$endpoint" MCP_OAUTH_TOKEN="$access3" MCP_EXPECTED_ERA=legacy node "$base_dir/oauth-sdk-probe.mjs"

for secret in "$access1" "$access2" "$access3" "$refresh1" "$refresh2" "$refresh3"; do
  if grep -Fq "$secret" /tmp/robust-refresh-mcp.log "$telemetry" 2>/dev/null; then
    echo 'OAuth lifecycle credential leaked to MCP logs' >&2
    exit 1
  fi
done

printf '%s\n' 'robust-mcp-oauth-refresh: PASS authorization-code pkce=S256 offline_access refresh-issued refresh-rotated replay-blocked sustained-modern+legacy+auto no-mcp-secret-logs'
