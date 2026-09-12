#!/usr/bin/env bash
set -euo pipefail

SIM_PORT="${SIM_PORT:-8091}"
SIM_ADMIN_PASSWORD="${SIM_ADMIN_PASSWORD:-cmsa-simulation-only}"
SIM_URL="http://127.0.0.1:${SIM_PORT}"

cd "$(dirname "$0")"

docker compose up -d db wordpress

for attempt in $(seq 1 60); do
  if docker compose run --rm cli wp db check >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 60 ]; then
    echo "Simulation database did not become ready." >&2
    exit 1
  fi
  sleep 2
done

if ! docker compose run --rm cli wp core is-installed >/dev/null 2>&1; then
  docker compose run --rm cli wp core install \
    --url="$SIM_URL" \
    --title="Chattanooga CMS Admin Simulation" \
    --admin_user=admin \
    --admin_password="$SIM_ADMIN_PASSWORD" \
    --admin_email=simulation@example.invalid \
    --skip-email
fi

docker compose run --rm cli wp option update home "$SIM_URL" >/dev/null
docker compose run --rm cli wp option update siteurl "$SIM_URL" >/dev/null
docker compose run --rm cli wp rewrite structure '/%postname%/' --hard >/dev/null
docker compose run --rm cli wp plugin activate chattanooga-cms-admin >/dev/null

if ! docker compose run --rm cli wp post list --post_type=page --name=simulation-control --field=ID | grep -Eq '^[0-9]+$'; then
  docker compose run --rm cli wp post create \
    --post_type=page \
    --post_status=publish \
    --post_title="Simulation Control" \
    --post_name=simulation-control \
    --post_content="Disposable Chattanooga CMS Admin simulation page." >/dev/null
fi

if ! docker compose run --rm cli wp post list --post_type=post --name=simulation-edit-target --field=ID | grep -Eq '^[0-9]+$'; then
  docker compose run --rm cli wp post create \
    --post_type=post \
    --post_status=draft \
    --post_title="Simulation Edit Target" \
    --post_name=simulation-edit-target \
    --post_content="Disposable content for Chattanooga CMS Admin write-path testing." >/dev/null
fi

bash smoke.sh

echo "Simulation ready: ${SIM_URL}"
echo "Administrator: admin"
echo "Plugin source is live-mounted from site-plugins/chattanooga-cms-admin/."
