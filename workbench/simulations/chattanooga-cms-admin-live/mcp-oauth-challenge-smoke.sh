#!/usr/bin/env bash
set -euo pipefail

SIM_PORT="${SIM_PORT:-8091}"
SIM_URL="${SIM_URL:-http://127.0.0.1:${SIM_PORT}}"
MCP_PATH='/wp-json/chattanooga-cms-admin/v1/mcp'
MCP_ENDPOINT="${SIM_URL}${MCP_PATH}"
RESOURCE_METADATA="${SIM_URL}/.well-known/oauth-protected-resource${MCP_PATH}"

tmp_dir="$(mktemp -d)"
cleanup() {
  rm -rf "$tmp_dir"
}
trap cleanup EXIT

cat >"$tmp_dir/discover.json" <<'JSON'
{"jsonrpc":"2.0","id":951,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{},"io.modelcontextprotocol/clientInfo":{"name":"cmsa-oauth-challenge-smoke","version":"1.0.0"}}}}
JSON

anonymous_code="$(curl -sS -D "$tmp_dir/anonymous-headers.txt" -o "$tmp_dir/anonymous.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @"$tmp_dir/discover.json" \
  "$MCP_ENDPOINT")"
test "$anonymous_code" = '401'
grep -Eiq '^WWW-Authenticate: Bearer .*resource_metadata=' "$tmp_dir/anonymous-headers.txt"
grep -Fq "$RESOURCE_METADATA" "$tmp_dir/anonymous-headers.txt"
if grep -Fq 'error="invalid_token"' "$tmp_dir/anonymous-headers.txt"; then
  echo 'Anonymous MCP challenge must not report invalid_token.' >&2
  exit 1
fi

invalid_code="$(curl -sS -D "$tmp_dir/invalid-headers.txt" -o "$tmp_dir/invalid.json" -w '%{http_code}' \
  -H 'Authorization: Bearer cmsa-deliberately-invalid-token' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data-binary @"$tmp_dir/discover.json" \
  "$MCP_ENDPOINT")"
test "$invalid_code" = '401'
grep -Eiq '^WWW-Authenticate: Bearer .*resource_metadata=' "$tmp_dir/invalid-headers.txt"
grep -Fq "$RESOURCE_METADATA" "$tmp_dir/invalid-headers.txt"
grep -Fq 'error="invalid_token"' "$tmp_dir/invalid-headers.txt"

echo 'cmsa-native-mcp-oauth-challenge: PASS anonymous=no_error invalid_bearer=invalid_token status=401 accept=dual'
