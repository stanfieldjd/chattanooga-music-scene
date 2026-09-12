#!/usr/bin/env bash
set -euo pipefail

SIM_PORT="${SIM_PORT:-8091}"
SIM_URL="http://127.0.0.1:${SIM_PORT}"
ENDPOINT="${SIM_URL}/index.php?rest_route=%2Fchattanooga-cms-admin%2Fv1%2Fmcp"
APP_ID="cmsa-live-simulation-smoke"
APP_NAME="CMSA Live Simulation Smoke"

cd "$(dirname "$0")"

command -v curl >/dev/null 2>&1 || {
  echo "curl is required for the external MCP smoke test." >&2
  exit 1
}

post_id=""
tmp_dir="$(mktemp -d)"

cleanup() {
  if [ -n "$post_id" ]; then
    docker compose run --rm cli wp post delete "$post_id" --force >/dev/null 2>&1 || true
  fi

  uuid="$(docker compose run --rm cli wp user application-password list admin --app_id="$APP_ID" --field=uuid --quiet 2>/dev/null | head -n 1 || true)"
  if [ -n "$uuid" ]; then
    docker compose run --rm cli wp user application-password delete admin "$uuid" --quiet >/dev/null 2>&1 || true
  fi

  rm -rf "$tmp_dir"
}
trap cleanup EXIT

stale_uuid="$(docker compose run --rm cli wp user application-password list admin --app_id="$APP_ID" --field=uuid --quiet 2>/dev/null | head -n 1 || true)"
if [ -n "$stale_uuid" ]; then
  docker compose run --rm cli wp user application-password delete admin "$stale_uuid" --quiet >/dev/null
fi

app_password="$(docker compose run --rm cli wp user application-password create admin "$APP_NAME" --app-id="$APP_ID" --porcelain --quiet)"
test -n "$app_password"

for attempt in $(seq 1 30); do
  if curl -fsS "${SIM_URL}/index.php?rest_route=%2F" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    echo "Simulation HTTP endpoint did not become ready." >&2
    exit 1
  fi
  sleep 1
done

cat >"$tmp_dir/discover.json" <<'JSON'
{"jsonrpc":"2.0","id":801,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"cmsa-live-simulation-smoke","version":"1.0.0"}}}}
JSON

anonymous_code="$(curl -sS -o "$tmp_dir/anonymous.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @"$tmp_dir/discover.json" \
  "$ENDPOINT")"
test "$anonymous_code" = '401'

authenticated_code="$(curl -sS -o "$tmp_dir/discover-response.json" -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @"$tmp_dir/discover.json" \
  "$ENDPOINT")"
test "$authenticated_code" = '200'
cat "$tmp_dir/discover-response.json" | docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); if (($d["result"]["resultType"]??"")!=="complete" || !in_array("2026-07-28",$d["result"]["supportedVersions"]??[],true)) exit(1);'

cat >"$tmp_dir/catalog.json" <<'JSON'
{"jsonrpc":"2.0","id":802,"method":"tools/call","params":{"name":"cmsa.catalog","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"cmsa-live-simulation-smoke","version":"1.0.0"}}}}
JSON

catalog_code="$(curl -sS -o "$tmp_dir/catalog-response.json" -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: cmsa.catalog' \
  --data-binary @"$tmp_dir/catalog.json" \
  "$ENDPOINT")"
test "$catalog_code" = '200'

bridge="$(cat "$tmp_dir/catalog-response.json" | docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); foreach (($d["result"]["structuredContent"]["items"]??[]) as $item) { if (($item["contract"]??"")==="rest" && ($item["method"]??"")==="POST" && ($item["route"]??"")==="/wp/v2/posts") { echo $item["bridge"]; exit(0); } } exit(1);')"
test -n "$bridge"

write_json="$(docker compose run --rm -T -e BRIDGE="$bridge" cli php -r '$b=getenv("BRIDGE"); echo json_encode(["jsonrpc"=>"2.0","id"=>803,"method"=>"tools/call","params"=>["name"=>"cmsa.write-bridge","arguments"=>["bridge"=>$b,"input"=>["path"=>"/wp/v2/posts","params"=>["title"=>"CMSA Live Simulation HTTP Write","status"=>"draft","content"=>"Disposable native MCP simulation write-authority probe."]]],"_meta"=>["io.modelcontextprotocol/protocolVersion"=>"2026-07-28","io.modelcontextprotocol/clientInfo"=>["name"=>"cmsa-live-simulation-smoke","version"=>"1.0.0"]]]]);')"
printf '%s' "$write_json" >"$tmp_dir/write.json"

write_code="$(curl -sS -o "$tmp_dir/write-response.json" -w '%{http_code}' \
  --user "admin:${app_password}" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: cmsa.write-bridge' \
  --data-binary @"$tmp_dir/write.json" \
  "$ENDPOINT")"
test "$write_code" = '200'

post_id="$(cat "$tmp_dir/write-response.json" | docker compose run --rm -T cli php -r '$d=json_decode(stream_get_contents(STDIN),true); $result=$d["result"]??[]; $rest=$result["structuredContent"]["result"]??[]; if (($result["isError"]??true)!==false || ($rest["status"]??0)!==201 || ($rest["data"]["status"]??"")!=="draft" || empty($rest["data"]["id"])) exit(1); echo (int)$rest["data"]["id"];')"
test -n "$post_id"
test "$(docker compose run --rm cli wp post get "$post_id" --field=post_status --quiet)" = 'draft'

echo "cmsa-live-simulation-http-write: PASS anonymous_boundary=verified admin_auth=verified catalog=verified write=verified persistence=verified cleanup=armed"
