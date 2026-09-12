#!/usr/bin/env bash
set -euo pipefail

SIM_URL="${SIM_URL:-http://127.0.0.1:8091}"
SIM_ADMIN_PASSWORD="${SIM_ADMIN_PASSWORD:-cmsa-simulation-only}"
WP_CLI="${WP_CLI:-/tmp/wp-cli.phar}"
WP_PATH="${WP_PATH:-/tmp/cmsa-simulation}"
PHP_BIN="${PHP_BIN:-php}"

MCP_PATH='/wp-json/chattanooga-cms-admin/v1/mcp'
MCP_ENDPOINT="${SIM_URL}${MCP_PATH}"
REGISTER_ENDPOINT="${SIM_URL}/wp-json/chattanooga-cms-admin/v1/oauth/register"
TOKEN_ENDPOINT="${SIM_URL}/wp-json/chattanooga-cms-admin/v1/oauth/token"
RESOURCE_METADATA="${SIM_URL}/.well-known/oauth-protected-resource${MCP_PATH}"
SERVER_METADATA="${SIM_URL}/.well-known/oauth-authorization-server"
CALLBACK_URI="${SIM_URL}/oauth-callback"
SCOPE='mcp:admin'
STATE='cmsa-native-oauth-host-smoke'

tmp_dir="$(mktemp -d)"
client_id=''
access_token=''
refresh_token=''
rotated_access_token=''
rotated_refresh_token=''

wp_cli() {
  "$PHP_BIN" "$WP_CLI" --path="$WP_PATH" "$@"
}

cleanup() {
  CLIENT_ID_VALUE="$client_id" \
  ACCESS_TOKEN_VALUE="$access_token" \
  REFRESH_TOKEN_VALUE="$refresh_token" \
  ROTATED_ACCESS_TOKEN_VALUE="$rotated_access_token" \
  ROTATED_REFRESH_TOKEN_VALUE="$rotated_refresh_token" \
    "$PHP_BIN" "$WP_CLI" --path="$WP_PATH" eval '
      $pairs = array(
        array("cua_mcp_client_", getenv("CLIENT_ID_VALUE")),
        array("cua_mcp_at_", getenv("ACCESS_TOKEN_VALUE")),
        array("cua_mcp_rt_", getenv("REFRESH_TOKEN_VALUE")),
        array("cua_mcp_at_", getenv("ROTATED_ACCESS_TOKEN_VALUE")),
        array("cua_mcp_rt_", getenv("ROTATED_REFRESH_TOKEN_VALUE")),
      );
      foreach ($pairs as $pair) {
        if (is_string($pair[1]) && "" !== $pair[1]) {
          delete_transient($pair[0] . hash("sha256", $pair[1]));
        }
      }
    ' >/dev/null 2>&1 || true
  rm -rf "$tmp_dir"
}
trap cleanup EXIT

for attempt in $(seq 1 30); do
  if curl -fsS "${SIM_URL}/wp-json/" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    echo "OAuth smoke could not reach the WordPress REST API." >&2
    exit 1
  fi
  sleep 1
done

curl -fsS "$RESOURCE_METADATA" >"$tmp_dir/resource.json"
RESOURCE="$MCP_ENDPOINT" AUTH_SERVER="$SIM_URL" "$PHP_BIN" -r '
  $d=json_decode(file_get_contents($argv[1]),true);
  if (!is_array($d)) exit(1);
  if (($d["resource"]??"")!==getenv("RESOURCE")) exit(1);
  if (!in_array(getenv("AUTH_SERVER"),$d["authorization_servers"]??[],true)) exit(1);
  if (!in_array("mcp:admin",$d["scopes_supported"]??[],true)) exit(1);
  if (!in_array("header",$d["bearer_methods_supported"]??[],true)) exit(1);
' "$tmp_dir/resource.json"

curl -fsS "$SERVER_METADATA" >"$tmp_dir/server.json"
AUTH_SERVER="$SIM_URL" "$PHP_BIN" -r '
  $d=json_decode(file_get_contents($argv[1]),true);
  if (!is_array($d) || ($d["issuer"]??"")!==getenv("AUTH_SERVER")) exit(1);
  foreach (["authorization_endpoint","token_endpoint","registration_endpoint"] as $key) if (empty($d[$key])) exit(1);
  if (!in_array("code",$d["response_types_supported"]??[],true)) exit(1);
  if (!in_array("authorization_code",$d["grant_types_supported"]??[],true)) exit(1);
  if (!in_array("refresh_token",$d["grant_types_supported"]??[],true)) exit(1);
  if (!in_array("S256",$d["code_challenge_methods_supported"]??[],true)) exit(1);
  if (!in_array("none",$d["token_endpoint_auth_methods_supported"]??[],true)) exit(1);
  if (empty($d["client_id_metadata_document_supported"])) exit(1);
' "$tmp_dir/server.json"

authorization_endpoint="$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(empty($d["authorization_endpoint"])) exit(1); echo $d["authorization_endpoint"];' "$tmp_dir/server.json")"
test "$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); echo $d["token_endpoint"]??"";' "$tmp_dir/server.json")" = "$TOKEN_ENDPOINT"
test "$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); echo $d["registration_endpoint"]??"";' "$tmp_dir/server.json")" = "$REGISTER_ENDPOINT"

cat >"$tmp_dir/register.json" <<JSON
{"client_name":"CMSA Native OAuth Host Smoke","redirect_uris":["${CALLBACK_URI}"],"grant_types":["authorization_code","refresh_token"],"response_types":["code"],"token_endpoint_auth_method":"none","application_type":"native"}
JSON
register_code="$(curl -sS -o "$tmp_dir/register-response.json" -w '%{http_code}' -H 'Content-Type: application/json' --data-binary @"$tmp_dir/register.json" "$REGISTER_ENDPOINT")"
test "$register_code" = '201'
client_id="$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(empty($d["client_id"])||($d["token_endpoint_auth_method"]??"")!=="none"||($d["application_type"]??"")!=="native") exit(1); echo $d["client_id"];' "$tmp_dir/register-response.json")"
test -n "$client_id"

verifier="$("$PHP_BIN" -r '$b=random_bytes(48); echo rtrim(strtr(base64_encode($b),"+/","-_"),"=");')"
challenge="$(VERIFIER="$verifier" "$PHP_BIN" -r '$v=getenv("VERIFIER"); echo rtrim(strtr(base64_encode(hash("sha256",$v,true)),"+/","-_"),"=");')"
test "${#verifier}" -ge 43
test -n "$challenge"

cat >"$tmp_dir/discover.json" <<'JSON'
{"jsonrpc":"2.0","id":951,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{},"io.modelcontextprotocol/clientInfo":{"name":"cmsa-native-oauth-host-smoke","version":"1.0.0"}}}}
JSON
anonymous_code="$(curl -sS -D "$tmp_dir/anonymous-headers.txt" -o "$tmp_dir/anonymous.json" -w '%{http_code}' -H 'Content-Type: application/json' -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' --data-binary @"$tmp_dir/discover.json" "$MCP_ENDPOINT")"
test "$anonymous_code" = '401'
grep -Eiq '^WWW-Authenticate: Bearer .*resource_metadata=' "$tmp_dir/anonymous-headers.txt"
grep -Fq "$RESOURCE_METADATA" "$tmp_dir/anonymous-headers.txt"

cookie_jar="$tmp_dir/cookies.txt"
curl -sS -c "$cookie_jar" "${SIM_URL}/wp-login.php" >/dev/null
login_code="$(curl -sS -L -b "$cookie_jar" -c "$cookie_jar" -o "$tmp_dir/login-result.html" -w '%{http_code}' --data-urlencode 'log=admin' --data-urlencode "pwd=${SIM_ADMIN_PASSWORD}" --data-urlencode 'wp-submit=Log In' --data-urlencode "redirect_to=${SIM_URL}/wp-admin/" --data-urlencode 'testcookie=1' "${SIM_URL}/wp-login.php")"
test "$login_code" = '200'
grep -q 'wordpress_logged_in' "$cookie_jar"

curl -sS -b "$cookie_jar" -c "$cookie_jar" -o "$tmp_dir/authorize.html" --get --data-urlencode "client_id=${client_id}" --data-urlencode "redirect_uri=${CALLBACK_URI}" --data-urlencode 'response_type=code' --data-urlencode "code_challenge=${challenge}" --data-urlencode 'code_challenge_method=S256' --data-urlencode "resource=${MCP_ENDPOINT}" --data-urlencode "scope=${SCOPE}" --data-urlencode "state=${STATE}" "$authorization_endpoint"
grep -Fq 'Authorize Chattanooga CMS Admin' "$tmp_dir/authorize.html"
nonce="$("$PHP_BIN" -r '$h=file_get_contents($argv[1]); if(!preg_match("/name=\"_cmsa_oauth_nonce\" value=\"([^\"]+)\"/",$h,$m)) exit(1); echo html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,"UTF-8");' "$tmp_dir/authorize.html")"
test -n "$nonce"

approve_code="$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -D "$tmp_dir/approve-headers.txt" -o "$tmp_dir/approve-body.txt" -w '%{http_code}' --data-urlencode 'action=cmsa_mcp_oauth_authorize' --data-urlencode "_cmsa_oauth_nonce=${nonce}" --data-urlencode 'cmsa_oauth_decision=approve' --data-urlencode "client_id=${client_id}" --data-urlencode "redirect_uri=${CALLBACK_URI}" --data-urlencode 'response_type=code' --data-urlencode "code_challenge=${challenge}" --data-urlencode 'code_challenge_method=S256' --data-urlencode "resource=${MCP_ENDPOINT}" --data-urlencode "scope=${SCOPE}" --data-urlencode "state=${STATE}" "${SIM_URL}/wp-admin/admin-post.php")"
test "$approve_code" = '302'
location="$(awk 'BEGIN{IGNORECASE=1} /^Location:/ {sub(/^[^:]+:[[:space:]]*/,""); sub(/\r$/,""); print; exit}' "$tmp_dir/approve-headers.txt")"
test -n "$location"
code="$(LOCATION="$location" CALLBACK="$CALLBACK_URI" EXPECTED_STATE="$STATE" EXPECTED_ISS="$SIM_URL" "$PHP_BIN" -r '
  $p=parse_url(getenv("LOCATION")); $c=parse_url(getenv("CALLBACK"));
  if (($p["scheme"]??"")!==($c["scheme"]??"") || ($p["host"]??"")!==($c["host"]??"") || ($p["port"]??null)!==($c["port"]??null) || ($p["path"]??"")!==($c["path"]??"")) exit(1);
  parse_str($p["query"]??"",$q);
  if (empty($q["code"]) || ($q["state"]??"")!==getenv("EXPECTED_STATE") || ($q["iss"]??"")!==getenv("EXPECTED_ISS")) exit(1);
  echo $q["code"];
')"
test -n "$code"

exchange_code="$(curl -sS -o "$tmp_dir/token-response.json" -w '%{http_code}' -H 'Content-Type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=authorization_code' --data-urlencode "client_id=${client_id}" --data-urlencode "code=${code}" --data-urlencode "redirect_uri=${CALLBACK_URI}" --data-urlencode "code_verifier=${verifier}" --data-urlencode "resource=${MCP_ENDPOINT}" "$TOKEN_ENDPOINT")"
test "$exchange_code" = '200'
access_token="$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(($d["token_type"]??"")!=="Bearer"||empty($d["access_token"])||empty($d["refresh_token"])||($d["scope"]??"")!=="mcp:admin") exit(1); echo $d["access_token"];' "$tmp_dir/token-response.json")"
refresh_token="$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(empty($d["refresh_token"])) exit(1); echo $d["refresh_token"];' "$tmp_dir/token-response.json")"
test -n "$access_token"
test -n "$refresh_token"

bearer_code="$(curl -sS -o "$tmp_dir/bearer-discover.json" -w '%{http_code}' -H "Authorization: Bearer ${access_token}" -H 'Content-Type: application/json' -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' --data-binary @"$tmp_dir/discover.json" "$MCP_ENDPOINT")"
test "$bearer_code" = '200'
"$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(($d["result"]["resultType"]??"")!=="complete"||($d["result"]["supportedVersions"]??null)!==["2026-07-28"]) exit(1);' "$tmp_dir/bearer-discover.json"

refresh_code="$(curl -sS -o "$tmp_dir/refresh-response.json" -w '%{http_code}' -H 'Content-Type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=refresh_token' --data-urlencode "client_id=${client_id}" --data-urlencode "refresh_token=${refresh_token}" --data-urlencode "resource=${MCP_ENDPOINT}" "$TOKEN_ENDPOINT")"
test "$refresh_code" = '200'
rotated_access_token="$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(($d["token_type"]??"")!=="Bearer"||empty($d["access_token"])||empty($d["refresh_token"])) exit(1); echo $d["access_token"];' "$tmp_dir/refresh-response.json")"
rotated_refresh_token="$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(empty($d["refresh_token"])) exit(1); echo $d["refresh_token"];' "$tmp_dir/refresh-response.json")"
test -n "$rotated_access_token"
test -n "$rotated_refresh_token"
test "$rotated_refresh_token" != "$refresh_token"

replay_code="$(curl -sS -o "$tmp_dir/replay-response.json" -w '%{http_code}' -H 'Content-Type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=refresh_token' --data-urlencode "client_id=${client_id}" --data-urlencode "refresh_token=${refresh_token}" --data-urlencode "resource=${MCP_ENDPOINT}" "$TOKEN_ENDPOINT")"
test "$replay_code" = '400'
"$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true); if(($d["error"]??"")!=="invalid_grant") exit(1);' "$tmp_dir/replay-response.json"

rotated_bearer_code="$(curl -sS -o "$tmp_dir/rotated-bearer.json" -w '%{http_code}' -H "Authorization: Bearer ${rotated_access_token}" -H 'Content-Type: application/json' -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: server/discover' --data-binary @"$tmp_dir/discover.json" "$MCP_ENDPOINT")"
test "$rotated_bearer_code" = '200'

echo 'cmsa-native-mcp-oauth-host: PASS protected_resource=verified metadata_path=resource-derived authorization_server=verified dcr=native admin_consent=verified pkce=verified bearer_mcp=verified refresh_rotation=verified envelope=verified third_party_mcp=absent'
