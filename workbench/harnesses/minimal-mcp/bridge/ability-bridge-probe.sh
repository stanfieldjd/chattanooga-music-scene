#!/usr/bin/env bash
set -euo pipefail

endpoint='http://127.0.0.1:8092/index.php?rest_route=%2Fminimal-mcp%2Fv1%2Fmcp'
app_password="$(php /tmp/wp-cli.phar user application-password create admin mcp-ability-bridge-ci --porcelain --path=/tmp/wordpress)"

php -S 127.0.0.1:8092 -t /tmp/wordpress >/tmp/mcp-ability-bridge-http.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 30); do
  if curl -fsS 'http://127.0.0.1:8092/index.php?rest_route=%2F' >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    cat /tmp/mcp-ability-bridge-http.log >&2
    exit 1
  fi
  sleep 1
done

MCP_ENDPOINT="$endpoint" MCP_USER='admin' MCP_PASSWORD="$app_password" \
  node workbench/harnesses/minimal-mcp/bridge/ability-bridge-sdk-probe.mjs

test "$(php /tmp/wp-cli.phar option get minimal_mcp_bridge_ci --path=/tmp/wordpress)" = 'bridge-write-verified'

# The layered gateway must not bypass the target ability's permission callback.
php /tmp/wp-cli.phar user create bridge-subscriber bridge-subscriber@example.invalid --role=subscriber --user_pass='bridge-ci-password' --path=/tmp/wordpress >/dev/null
subscriber_password="$(php /tmp/wp-cli.phar user application-password create bridge-subscriber mcp-ability-bridge-subscriber --porcelain --path=/tmp/wordpress)"
cat > /tmp/bridge-subscriber-call.json <<'JSON'
{"jsonrpc":"2.0","id":91,"method":"tools/call","params":{"name":"wordpress.execute-ability","arguments":{"ability_name":"mcp-fixture/write-option","input":{"value":"must-not-write"}},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientInfo":{"name":"permission-probe","version":"1.0.0"},"io.modelcontextprotocol/clientCapabilities":{}}}}
JSON

# Transport itself requires manage_options, so a subscriber must be rejected before tool execution.
subscriber_code="$(curl -sS -o /tmp/bridge-subscriber-response.json -w '%{http_code}' \
  --user "bridge-subscriber:${subscriber_password}" \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: wordpress.execute-ability' -H 'Mcp-Param-Ability-Name: mcp-fixture/write-option' \
  --data-binary @/tmp/bridge-subscriber-call.json "$endpoint")"
test "$subscriber_code" = '403'
test "$(php /tmp/wp-cli.phar option get minimal_mcp_bridge_ci --path=/tmp/wordpress)" = 'bridge-write-verified'

printf '%s\n' 'mcp-wordpress-ability-bridge: PASS layered-discovery schema-inspection read-execution write-execution private-exclusion admin-boundary'
