#!/usr/bin/env bash
set -euo pipefail

WP_CLI="${WP_CLI:-/tmp/wp-cli.phar}"
WP_PATH="${WP_PATH:-/tmp/wordpress}"
BASE_URL="${BASE_URL:-http://127.0.0.1:8090}"
ENDPOINT="${BASE_URL}/index.php?rest_route=%2Fchattanooga-cms-admin%2Fv1%2Fmcp"
PROTOCOL='2026-07-28'

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  exit 1
}

app_password="$(php "$WP_CLI" user application-password create admin cmsa-external-functional-redteam --porcelain --path="$WP_PATH")"
test -n "$app_password" || fail 'Could not create an Application Password.'

php -S 127.0.0.1:8090 -t "$WP_PATH" >/tmp/cmsa-external-functional-http.log 2>&1 &
server_pid=$!
cleanup_server() {
  kill "$server_pid" 2>/dev/null || true
}
trap cleanup_server EXIT

for attempt in $(seq 1 30); do
  if curl -fsS "${BASE_URL}/index.php?rest_route=%2F" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/cmsa-external-functional-http.log >&2
    fail 'Disposable WordPress HTTP server did not become ready.'
  fi
  sleep 1
done

mcp_call() {
  local body_file="$1"
  local method="$2"
  local tool_name="${3:-}"
  local output_file="$4"
  local -a headers=(
    -H 'Content-Type: application/json'
    -H "MCP-Protocol-Version: ${PROTOCOL}"
    -H "Mcp-Method: ${method}"
  )
  if [ -n "$tool_name" ]; then
    headers+=( -H "Mcp-Name: ${tool_name}" )
  fi

  local code
  code="$(curl -sS -o "$output_file" -w '%{http_code}' \
    --user "admin:${app_password}" \
    "${headers[@]}" \
    --data-binary "@${body_file}" \
    "$ENDPOINT")"
  test "$code" = '200' || {
    cat "$output_file" >&2 || true
    fail "MCP ${method} returned HTTP ${code}."
  }

  php -r '$d=json_decode(file_get_contents($argv[1]),true); if (!is_array($d) || isset($d["error"]) || !isset($d["result"]) || (($d["result"]["isError"]??false)===true)) { fwrite(STDERR,file_get_contents($argv[1])); exit(1); }' "$output_file" \
    || fail "MCP ${method} returned an application error."
}

make_tool_body() {
  local id="$1"
  local tool="$2"
  local arguments_json="$3"
  local output_file="$4"
  ID="$id" TOOL="$tool" ARGS="$arguments_json" PROTOCOL="$PROTOCOL" php -r '
    $args=json_decode(getenv("ARGS"),true);
    if (!is_array($args)) exit(2);
    echo json_encode([
      "jsonrpc"=>"2.0",
      "id"=>(int)getenv("ID"),
      "method"=>"tools/call",
      "params"=>[
        "name"=>getenv("TOOL"),
        "arguments"=>$args,
        "_meta"=>[
          "io.modelcontextprotocol/protocolVersion"=>getenv("PROTOCOL"),
          "io.modelcontextprotocol/clientInfo"=>["name"=>"cmsa-external-functional-redteam","version"=>"1.0.0"]
        ]
      ]
    ], JSON_UNESCAPED_SLASHES);
  ' > "$output_file"
}

catalog_body=/tmp/cmsa-external-catalog.json
make_tool_body 1001 'cmsa.catalog' '{}' "$catalog_body"
mcp_call "$catalog_body" 'tools/call' 'cmsa.catalog' /tmp/cmsa-external-catalog-response.json

find_ability_bridge() {
  local target="$1"
  TARGET="$target" php -r '
    $d=json_decode(file_get_contents("/tmp/cmsa-external-catalog-response.json"),true);
    $matches=[];
    foreach (($d["result"]["structuredContent"]["items"]??[]) as $item) {
      if (($item["contract"]??"")==="ability" && ($item["target"]??"")===getenv("TARGET") && !empty($item["bridge"])) $matches[]=$item["bridge"];
    }
    if (count($matches)!==1) exit(1);
    echo $matches[0];
  ' || fail "Could not resolve ability bridge for ${target}."
}

find_rest_bridge() {
  local method="$1"
  local path="$2"
  METHOD="$method" PATH_VALUE="$path" php -r '
    $d=json_decode(file_get_contents("/tmp/cmsa-external-catalog-response.json"),true);
    $matches=[];
    foreach (($d["result"]["structuredContent"]["items"]??[]) as $item) {
      if (($item["contract"]??"")!=="rest" || ($item["method"]??"")!==getenv("METHOD") || empty($item["route"]) || empty($item["bridge"])) continue;
      if (@preg_match("@^".$item["route"]."$@i", getenv("PATH_VALUE"))===1) $matches[]=$item["bridge"];
    }
    if (count($matches)!==1) exit(1);
    echo $matches[0];
  ' || fail "Could not resolve REST bridge for ${method} ${path}."
}

bridge_call() {
  local id="$1"
  local tool="$2"
  local bridge="$3"
  local input_json="$4"
  local output_file="$5"
  local args_json
  args_json="$(BRIDGE="$bridge" INPUT="$input_json" php -r '$i=json_decode(getenv("INPUT"),true); if (!is_array($i)) exit(2); echo json_encode(["bridge"=>getenv("BRIDGE"),"input"=>$i], JSON_UNESCAPED_SLASHES);')"
  make_tool_body "$id" "$tool" "$args_json" /tmp/cmsa-external-call.json
  mcp_call /tmp/cmsa-external-call.json 'tools/call' "$tool" "$output_file"
}

# 1. Rank Math external read/write/rollback through native MCP.
get_settings_bridge="$(find_ability_bridge 'rank-math/get-settings')"
set_global_bridge="$(find_ability_bridge 'rank-math/set-global-seo-settings')"
bridge_call 1010 'cmsa.read-bridge' "$get_settings_bridge" '{"sections":["titles"]}' /tmp/cmsa-seo-before.json
original_separator="$(php -r '$d=json_decode(file_get_contents("/tmp/cmsa-seo-before.json"),true); $v=$d["result"]["structuredContent"]["result"]["titles"]["title_separator"]??null; if (!is_string($v)) exit(1); echo $v;' || fail 'Could not read Rank Math title separator through external MCP.')"
if [ "$original_separator" = '|' ]; then alternate_separator='-'; else alternate_separator='|'; fi
seo_write_input="$(ALT="$alternate_separator" php -r 'echo json_encode(["title_separator"=>getenv("ALT")]);')"
bridge_call 1011 'cmsa.write-bridge' "$set_global_bridge" "$seo_write_input" /tmp/cmsa-seo-write.json
bridge_call 1012 'cmsa.read-bridge' "$get_settings_bridge" '{"sections":["titles"]}' /tmp/cmsa-seo-changed.json
changed_separator="$(php -r '$d=json_decode(file_get_contents("/tmp/cmsa-seo-changed.json"),true); echo (string)($d["result"]["structuredContent"]["result"]["titles"]["title_separator"]??"");')"
test "$changed_separator" = "$alternate_separator" || fail 'External MCP Rank Math mutation did not persist.'
seo_restore_input="$(ORIGINAL="$original_separator" php -r 'echo json_encode(["title_separator"=>getenv("ORIGINAL")]);')"
bridge_call 1013 'cmsa.write-bridge' "$set_global_bridge" "$seo_restore_input" /tmp/cmsa-seo-restore.json
bridge_call 1014 'cmsa.read-bridge' "$get_settings_bridge" '{"sections":["titles"]}' /tmp/cmsa-seo-restored.json
restored_separator="$(php -r '$d=json_decode(file_get_contents("/tmp/cmsa-seo-restored.json"),true); echo (string)($d["result"]["structuredContent"]["result"]["titles"]["title_separator"]??"");')"
test "$restored_separator" = "$original_separator" || fail 'External MCP Rank Math rollback failed.'

# 2. Create a published Events Manager event through external native MCP.
event_date="$(php "$WP_CLI" eval '$tz=wp_timezone(); $now=new DateTimeImmutable("now",$tz); $friday=$now->modify("friday this week")->setTime(0,0,0); $sunday=$friday->modify("+2 days")->setTime(23,59,59); if($now>$sunday){$friday=$friday->modify("+1 week");} echo $friday->format("Y-m-d");' --path="$WP_PATH")"
timezone="$(php "$WP_CLI" eval 'echo wp_timezone_string() ?: "UTC";' --path="$WP_PATH")"
event_name="CMSA External MCP $(php -r 'echo substr(hash("sha256",random_bytes(16)),0,10);')"
create_bridge="$(find_rest_bridge 'POST' '/events-manager/v1/events')"
event_input="$(EVENT_NAME="$event_name" EVENT_DATE="$event_date" TIMEZONE="$timezone" php -r 'echo json_encode(["path"=>"/events-manager/v1/events","params"=>["event_name"=>getenv("EVENT_NAME"),"content"=>"Disposable external MCP functional event.","event_type"=>"single","post_status"=>"publish","event_start_date"=>getenv("EVENT_DATE"),"event_end_date"=>getenv("EVENT_DATE"),"event_start_time"=>"19:30:00","event_end_time"=>"21:30:00","event_timezone"=>getenv("TIMEZONE")]], JSON_UNESCAPED_SLASHES);')"
bridge_call 1020 'cmsa.write-bridge' "$create_bridge" "$event_input" /tmp/cmsa-event-create.json
event_id="$(php -r '
  function xid($v){if(is_array($v)){foreach(["id","event_id"] as $k){if(isset($v[$k])&&is_numeric($v[$k])&&(int)$v[$k]>0)return(int)$v[$k];}foreach($v as $c){$id=xid($c);if($id>0)return$id;}}return 0;}
  $d=json_decode(file_get_contents("/tmp/cmsa-event-create.json"),true); $id=xid($d["result"]["structuredContent"]["result"]["data"]??[]); if($id<1)exit(1); echo $id;
' || fail 'External MCP event creation returned no event ID.')"

# 3. Invoke the declared Weekend Feature ability through external native MCP.
weekend_bridge="$(find_ability_bridge 'chattanooga-music-scene/generate-weekend-feature')"
bridge_call 1030 'cmsa.write-bridge' "$weekend_bridge" '{"status":"draft"}' /tmp/cmsa-weekend-generate.json
feature_id="$(php -r '$d=json_decode(file_get_contents("/tmp/cmsa-weekend-generate.json"),true); $r=$d["result"]["structuredContent"]["result"]??[]; if (($r["event_count"]??0)<1 || empty($r["post_id"])) exit(1); echo (int)$r["post_id"];' || fail 'External MCP Weekend Feature generation returned no usable result.')"
php "$WP_CLI" post get "$feature_id" --field=post_content --path="$WP_PATH" | grep -Fq "$event_name" || fail 'Externally generated Weekend Feature does not contain the external MCP event.'

# 4. Roll back disposable content through external native MCP.
event_path="/events-manager/v1/events/${event_id}"
delete_event_bridge="$(find_rest_bridge 'DELETE' "$event_path")"
event_delete_input="$(PATH_VALUE="$event_path" php -r 'echo json_encode(["path"=>getenv("PATH_VALUE"),"params"=>["context"=>"edit"]], JSON_UNESCAPED_SLASHES);')"
bridge_call 1040 'cmsa.write-bridge' "$delete_event_bridge" "$event_delete_input" /tmp/cmsa-event-delete.json

feature_path="/wp/v2/cms_weekend_feature/${feature_id}"
delete_feature_bridge="$(find_rest_bridge 'DELETE' "$feature_path")"
feature_delete_input="$(PATH_VALUE="$feature_path" php -r 'echo json_encode(["path"=>getenv("PATH_VALUE"),"params"=>["force"=>true]], JSON_UNESCAPED_SLASHES);')"
bridge_call 1041 'cmsa.write-bridge' "$delete_feature_bridge" "$feature_delete_input" /tmp/cmsa-feature-delete.json

feature_status="$(php "$WP_CLI" post get "$feature_id" --field=post_status --path="$WP_PATH" 2>/dev/null || true)"
test -z "$feature_status" || fail 'External MCP Weekend Feature cleanup did not remove the disposable post.'

printf '%s\n' 'cmsa-native-mcp-external-functional: PASS app_password=verified seo_read=verified seo_write=verified seo_rollback=verified event_create=verified weekend_generate=verified event_cleanup=verified feature_cleanup=verified'
