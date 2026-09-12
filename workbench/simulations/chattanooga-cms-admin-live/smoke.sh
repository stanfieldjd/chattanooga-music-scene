#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

docker compose run --rm cli wp core is-installed >/dev/null
docker compose run --rm cli wp plugin is-active chattanooga-cms-admin >/dev/null

docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/native-mcp-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/native-mcp-admin-surface-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/native-mcp-direct-boundary-cli.php
docker compose run --rm cli wp eval-file /workspace/workbench/harnesses/chattanooga-cms-admin-v2/probes/media-upload-cli.php

bash mcp-http-write-smoke.sh

echo "cmsa-live-simulation: PASS wordpress=running plugin=active native_mcp=verified admin_surface=verified direct_boundary=verified media_upload=verified http_write=verified"
