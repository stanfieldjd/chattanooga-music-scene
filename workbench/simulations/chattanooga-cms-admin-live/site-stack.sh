#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if ! docker compose run --rm cli wp core is-installed >/dev/null 2>&1; then
  echo "Start the base simulation with bootstrap.sh before enabling the site stack." >&2
  exit 1
fi

ensure_plugin_version() {
  local slug="$1"
  local version="$2"
  local current=""

  current="$(docker compose run --rm cli wp plugin get "$slug" --field=version --quiet 2>/dev/null || true)"
  if [ "$current" != "$version" ]; then
    docker compose run --rm cli wp plugin install "$slug" --version="$version" --force --activate >/dev/null
  else
    docker compose run --rm cli wp plugin activate "$slug" >/dev/null
  fi

  test "$(docker compose run --rm cli wp plugin get "$slug" --field=version --quiet)" = "$version"
  docker compose run --rm cli wp plugin is-active "$slug" >/dev/null
}

# The built-in Chattanooga MCP must remain the only MCP transport in this simulation.
if docker compose run --rm cli wp plugin is-installed miniorange-secure-mcp-server >/dev/null 2>&1; then
  echo "Third-party MCP plugin detected in the native-MCP simulation." >&2
  exit 1
fi

# Keep Rank Math's registration UI out of this disposable environment.
docker compose run --rm cli wp config set RANK_MATH_REGISTRATION_SKIP true --raw >/dev/null

ensure_plugin_version events-manager 7.4.3
ensure_plugin_version woocommerce 11.0.1
ensure_plugin_version seo-by-rank-math 1.0.278
ensure_plugin_version another-wordpress-classifieds-plugin 4.4.8

# Chattanooga-owned plugin source is live-mounted by docker-compose.yml.
docker compose run --rm cli wp plugin activate chattanooga-cms-admin chattanooga-music-marketplace chattanooga-music-scene-core >/dev/null

docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/events-manager-bootstrap-cli.php

docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/events-manager-compatibility-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/woocommerce-compatibility-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/rank-math-compatibility-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/awp-native-content-compatibility-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-site-plugins/integration-cli.php

bash smoke.sh

echo "cmsa-live-simulation-site-stack: PASS custom_plugins=3 events_manager=7.4.3 woocommerce=11.0.1 rank_math=1.0.278 awp_classifieds=4.4.8 awp_native_path=verified third_party_mcp=absent"
