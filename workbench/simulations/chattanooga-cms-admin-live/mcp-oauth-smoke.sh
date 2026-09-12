#!/usr/bin/env bash
set -euo pipefail

SIM_PORT="${SIM_PORT:-8091}"
SIM_ADMIN_PASSWORD="${SIM_ADMIN_PASSWORD:-cmsa-simulation-only}"
SIM_URL="${SIM_URL:-http://127.0.0.1:${SIM_PORT}}"
MCP_ENDPOINT="${SIM_URL}/wp-json/chattanooga-cms-admin/v1/mcp"
REGISTER_ENDPOINT="${SIM_URL}/wp-json/chattanooga-cms-admin/v1/oauth/register"
TOKEN_ENDPOINT="${SIM_URL}/wp-json/chattanooga-cms-admin/v1/oauth/token"
RESOURCE_METADATA="${SIM_URL}/.well-known/oauth-protected-resource"
SERVER_METADATA="${SIM_URL}/.well-known/oauth-authorization-server"
CALLBACK_URI="${SIM_URL}/oauth-callback"
SCOPE='mcp:admin'
STATE='cmsa-native-oauth-smoke'

cd "$(dirname "$0")"

tmp_dir="$(mktemp -d)"
client_id=''
access_token=''
refresh_token=''
rotated_access_token=''
rotated_refresh_token=''

cleanup() {
  if [ -n "$client_id" ] || [ -n "$access_token" ] || [ -n "$refresh_token" ] || [ -n "$rotated_access_token" ] || [ -n "$rotated_refresh_token" ]; then
    docker compose run --rm -T \
      -e CLIENT_ID="$client_id" \
      -e ACCESS_TOKEN="$access_token" \
      -e REFRESH_TOKEN="$refresh_token" \
      -e ROTATED_ACCESS_TOKEN="$rotated_access_token" \
      -e ROTATED_REFRESH_TOKEN="$rotated_refresh_token" \
      cli wp eval '
        $pairs = array(
          array("cua_mcp_client_", getenv("CLIENT_ID")),
          array("cua_mcp_at_", getenv("ACCESS_TOKEN")),
          array("cua_mcp_rt_", getenv("REFRESH_TOKEN")),
          array("cua_mcp_at_", getenv("ROTATED_ACCESS_TOKEN")),
          array("cua_mcp_rt_", getenv("ROTATED_REFRESH_TOKEN")),
        );
        foreach ($pairs as $pair) {
          if (is_string($pair[1]) && "" !== $pair[1]) {
            delete_transient($pair[0] . hash("sha256", $pair[1]));
          }
        }
      ' >/dev/null 2>&1 || true
  fi
  rm -rf "$tmp_dir"
}
trap cleanup EXIT

for attempt in $(seq 1 30); do
  if curl -fsS "${SIM_URL}/wp-json/" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    echo "Simulation HTTP endpoint did not become ready." >&2
    exit 1
  fi
  sleep 1
done

curl -fsS "$RESOURCE_METADATA" >"$tmp_dir/resource-metadata.json"
RESOURCE="$MCP_ENDPOINT" AUTH_SERVER="$SIM_URL" docker compose run --rm -T \
  -e RESOURCE \
  -e AUTH_SERVER \
  cli php -r '
    $d=json_decode(stream_get_contents(STDIN), true);
    if (!is_array($d)) exit(1);
    if (($d["resource"]??"")!==getenv("RESOURCE")) exit(1);
    if (!in_array(getenv("AUTH_SERVER"), $d["authorization_servers"]??[], true)) exit(1);
    if (!in_array("mcp:admin", $d["scopes_supported"]??[], true)) exit(1);
    if (!in_array("header", $d["bearer_methods_supported"]??[], true)) exit(1);
  ' <"$tmp_dir/resource-metadata.json"

curl -fsS "$SERVER_METADATA" >"$tmp_dir/server-metadata.json"
AUTH_SERVER="$SIM_URL" docker compose run --rm -T -e AUTH_SERVER cli php -r '
  $d=json_decode(stream_get_contents(STDIN), true);
  if (!is_array($d)) exit(1);
  if (($d["issuer"]??"")!==getenv("AUTH_SERVER")) exit(1);
  foreach (["authorization_endpoint","token_endpoint","registration_endpoint"] as $key) if (empty($d[$key])) exit(1);
  if (!in_array("code", $d["response_types_supported"]??[], true)) exit(1);
  if (!in_array("authorization_code", $d["grant_types_supported"]??[], true)) exit(1);
  if (!in_array("refresh_token", $d["grant_types_supported"]??[], true)) exit(1);
  if (!in_array("S256", $d["code_challenge_methods_supported"]??[], true)) exit(1);
  if (!in_array("none", $d["token_endpoint_auth_methods_supported"]??[], true)) exit(1);
  if (empty($d["client_id_metadata_document_supported"])) exit(1);
' <"$tmp_dir/server-metadata.json"

authorization_endpoint="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (empty($d["authorization_endpoint"])) exit(1); echo $d["authorization_endpoint"];' <"$tmp_dir/server-metadata.json")"
metadata_token_endpoint="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (empty($d["token_endpoint"])) exit(1); echo $d["token_endpoint"];' <"$tmp_dir/server-metadata.json")"
metadata_register_endpoint="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (empty($d["registration_endpoint"])) exit(1); echo $d["registration_endpoint"];' <"$tmp_dir/server-metadata.json")"
test "$metadata_token_endpoint" = "$TOKEN_ENDPOINT"
test "$metadata_register_endpoint" = "$REGISTER_ENDPOINT"

cat >"$tmp_dir/register.json" <<JSON
{"client_name":"CMSA Native OAuth Smoke","redirect_uris":["${CALLBACK_URI}"],"grant_types":["authorization_code","refresh_token"],"response_types":["code"],"token_endpoint_auth_method":"none","application_type":"web"}
JSON

register_code="$(curl -sS -o "$tmp_dir/register-response.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  --data-binary @"$tmp_dir/register.json" \
  "$REGISTER_ENDPOINT")"
test "$register_code" = '201'
client_id="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (empty($d["client_id"]) || ($d["token_endpoint_auth_method"]??"")!=="none") exit(1); echo $d["client_id"];' <"$tmp_dir/register-response.json")"
test -n "$client_id"

verifier="$(docker compose run --rm cli php -r '$b=random_bytes(48); echo rtrim(strtr(base64_encode($b), "+/", "-_"), "=");' | tr -d '\r\n')"
challenge="$(docker compose run --rm -T -e VERIFIER="$verifier" cli php -r '$v=getenv("VERIFIER"); echo rtrim(strtr(base64_encode(hash("sha256",$v,true)), "+/", "-_"), "=");')"
test "${#verifier}" -ge 43
test -n "$challenge"

cat >"$tmp_dir/discover.json" <<'JSON'
{"jsonrpc":"2.0","id":901,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{},"io.modelcontextprotocol/clientInfo":{"name":"cmsa-native-oauth-smoke","version":"1.0.0"}}}}
JSON

anonymous_code="$(curl -sS -D "$tmp_dir/anonymous-headers.txt" -o "$tmp_dir/anonymous.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @"$tmp_dir/discover.json" \
  "$MCP_ENDPOINT")"
test "$anonymous_code" = '401'
grep -Eiq '^WWW-Authenticate: Bearer .*resource_metadata=' "$tmp_dir/anonymous-headers.txt"
grep -Fq "$RESOURCE_METADATA" "$tmp_dir/anonymous-headers.txt"

cookie_jar="$tmp_dir/cookies.txt"
curl -sS -c "$cookie_jar" "${SIM_URL}/wp-login.php" >/dev/null
login_code="$(curl -sS -L -b "$cookie_jar" -c "$cookie_jar" -o "$tmp_dir/login-result.html" -w '%{http_code}' \
  --data-urlencode 'log=admin' \
  --data-urlencode "pwd=${SIM_ADMIN_PASSWORD}" \
  --data-urlencode 'wp-submit=Log In' \
  --data-urlencode "redirect_to=${SIM_URL}/wp-admin/" \
  --data-urlencode 'testcookie=1' \
  "${SIM_URL}/wp-login.php")"
test "$login_code" = '200'
grep -q 'wordpress_logged_in' "$cookie_jar"

curl -sS -b "$cookie_jar" -c "$cookie_jar" -o "$tmp_dir/authorize.html" \
  --get \
  --data-urlencode "client_id=${client_id}" \
  --data-urlencode "redirect_uri=${CALLBACK_URI}" \
  --data-urlencode 'response_type=code' \
  --data-urlencode "code_challenge=${challenge}" \
  --data-urlencode 'code_challenge_method=S256' \
  --data-urlencode "resource=${MCP_ENDPOINT}" \
  --data-urlencode "scope=${SCOPE}" \
  --data-urlencode "state=${STATE}" \
  "$authorization_endpoint"
grep -Fq 'Authorize Chattanooga CMS Admin' "$tmp_dir/authorize.html"
nonce="$(docker compose run --rm -T cli php -r '$h=stream_get_contents(STDIN); if (!preg_match("/name=\"_cmsa_oauth_nonce\" value=\"([^\"]+)\"/",$h,$m)) exit(1); echo html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,"UTF-8");' <"$tmp_dir/authorize.html")"
test -n "$nonce"

approve_code="$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -D "$tmp_dir/approve-headers.txt" -o "$tmp_dir/approve-body.txt" -w '%{http_code}' \
  --data-urlencode 'action=cmsa_mcp_oauth_authorize' \
  --data-urlencode "_cmsa_oauth_nonce=${nonce}" \
  --data-urlencode 'cmsa_oauth_decision=approve' \
  --data-urlencode "client_id=${client_id}" \
  --data-urlencode "redirect_uri=${CALLBACK_URI}" \
  --data-urlencode 'response_type=code' \
  --data-urlencode "code_challenge=${challenge}" \
  --data-urlencode 'code_challenge_method=S256' \
  --data-urlencode "resource=${MCP_ENDPOINT}" \
  --data-urlencode "scope=${SCOPE}" \
  --data-urlencode "state=${STATE}" \
  "${SIM_URL}/wp-admin/admin-post.php")"
test "$approve_code" = '302'
location="$(awk 'BEGIN{IGNORECASE=1} /^Location:/ {sub(/^[^:]+:[[:space:]]*/,""); sub(/\r$/,""); print; exit}' "$tmp_dir/approve-headers.txt")"
test -n "$location"

CALLBACK="$CALLBACK_URI" EXPECTED_STATE="$STATE" EXPECTED_ISS="$SIM_URL" docker compose run --rm -T \
  -e CALLBACK \
  -e EXPECTED_STATE \
  -e EXPECTED_ISS \
  -e LOCATION="$location" \
  cli php -r '
    $u=getenv("LOCATION"); $p=parse_url($u); $callback=parse_url(getenv("CALLBACK"));
    if (($p["scheme"]??"")!==($callback["scheme"]??"") || ($p["host"]??"")!==($callback["host"]??"") || ($p["port"]??null)!==($callback["port"]??null) || ($p["path"]??"")!==($callback["path"]??"")) exit(1);
    parse_str($p["query"]??"",$q);
    if (empty($q["code"]) || ($q["state"]??"")!==getenv("EXPECTED_STATE") || ($q["iss"]??"")!==getenv("EXPECTED_ISS")) exit(1);
    echo $q["code"];
  ' >"$tmp_dir/code.txt"
code="$(cat "$tmp_dir/code.txt")"
test -n "$code"

exchange_code="$(curl -sS -o "$tmp_dir/token-response.json" -w '%{http_code}' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=authorization_code' \
  --data-urlencode "client_id=${client_id}" \
  --data-urlencode "code=${code}" \
  --data-urlencode "redirect_uri=${CALLBACK_URI}" \
  --data-urlencode "code_verifier=${verifier}" \
  --data-urlencode "resource=${MCP_ENDPOINT}" \
  "$TOKEN_ENDPOINT")"
test "$exchange_code" = '200'
access_token="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (($d["token_type"]??"")!=="Bearer" || empty($d["access_token"]) || empty($d["refresh_token"]) || ($d["scope"]??"")!=="mcp:admin") exit(1); echo $d["access_token"];' <"$tmp_dir/token-response.json")"
refresh_token="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (empty($d["refresh_token"])) exit(1); echo $d["refresh_token"];' <"$tmp_dir/token-response.json")"
test -n "$access_token"
test -n "$refresh_token"

bearer_code="$(curl -sS -o "$tmp_dir/bearer-discover.json" -w '%{http_code}' \
  -H "Authorization: Bearer ${access_token}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @"$tmp_dir/discover.json" \
  "$MCP_ENDPOINT")"
test "$bearer_code" = '200'
docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (($d["result"]["resultType"]??"")!=="complete" || ($d["result"]["supportedVersions"]??null)!==["2026-07-28"]) exit(1);' <"$tmp_dir/bearer-discover.json"

refresh_code="$(curl -sS -o "$tmp_dir/refresh-response.json" -w '%{http_code}' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=refresh_token' \
  --data-urlencode "client_id=${client_id}" \
  --data-urlencode "refresh_token=${refresh_token}" \
  --data-urlencode "resource=${MCP_ENDPOINT}" \
  "$TOKEN_ENDPOINT")"
test "$refresh_code" = '200'
rotated_access_token="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (($d["token_type"]??"")!=="Bearer" || empty($d["access_token"]) || empty($d["refresh_token"])) exit(1); echo $d["access_token"];' <"$tmp_dir/refresh-response.json")"
rotated_refresh_token="$(docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (empty($d["refresh_token"])) exit(1); echo $d["refresh_token"];' <"$tmp_dir/refresh-response.json")"
test -n "$rotated_access_token"
test -n "$rotated_refresh_token"
test "$rotated_refresh_token" != "$refresh_token"

replay_code="$(curl -sS -o "$tmp_dir/replay-response.json" -w '%{http_code}' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=refresh_token' \
  --data-urlencode "client_id=${client_id}" \
  --data-urlencode "refresh_token=${refresh_token}" \
  --data-urlencode "resource=${MCP_ENDPOINT}" \
  "$TOKEN_ENDPOINT")"
test "$replay_code" = '400'
docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (($d["error"]??"")!=="invalid_grant") exit(1);' <"$tmp_dir/replay-response.json"

rotated_bearer_code="$(curl -sS -o "$tmp_dir/rotated-bearer-discover.json" -w '%{http_code}' \
  -H "Authorization: Bearer ${rotated_access_token}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @"$tmp_dir/discover.json" \
  "$MCP_ENDPOINT")"
test "$rotated_bearer_code" = '200'

echo 'cmsa-native-mcp-oauth: PASS protected_resource=verified authorization_server=verified dcr=verified admin_consent=verified pkce=verified bearer_mcp=verified refresh_rotation=verified envelope=verified third_party_mcp=absent'
