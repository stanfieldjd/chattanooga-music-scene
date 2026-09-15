#!/usr/bin/env bash
set -uo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "$base_dir/transport-probe.sh"
rc=$?
if [ "$rc" -ne 0 ]; then
  echo '--- robust MCP HTTP server log ---' >&2
  cat /tmp/robust-mcp-http.log >&2 2>/dev/null || true
  echo '--- robust MCP last response files ---' >&2
  for file in /tmp/robust-*.json /tmp/robust-*-headers.txt /tmp/robust-notification-response; do
    if [ -f "$file" ]; then
      echo "### $file" >&2
      cat "$file" >&2 || true
      echo >&2
    fi
  done
fi
exit "$rc"
