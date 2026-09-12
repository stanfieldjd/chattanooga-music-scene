#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

docker compose run --rm cli wp core is-installed >/dev/null
docker compose run --rm cli wp plugin is-active chattanooga-cms-admin >/dev/null

docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/native-mcp-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/native-mcp-version-boundary-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/native-mcp-admin-surface-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/native-mcp-direct-boundary-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/core-content-rest-parity-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/core-admin-rest-parity-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/core-users-rest-parity-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/private-content-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/media-upload-cli.php

bash mcp-http-write-smoke.sh
bash mcp-oauth-smoke.sh

echo "cmsa-live-simulation: PASS wordpress=running plugin=active native_mcp=verified version_boundary=verified admin_surface=verified direct_boundary=verified core_content=crud users=crud_roles private_content=crud media_upload=verified comments=crud_moderation media_metadata=crud http_write=verified oauth=pkce_bearer_refresh"
