#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

printf 'CMS Admin workbench lab\n'
printf 'PHP: %s\n' "$(php -r 'echo PHP_VERSION;')"

find "$ROOT/plugin" "$ROOT/candidate" "$ROOT/tests" "$ROOT/probes" -type f -name '*.php' -print0 \
  | xargs -0 -n1 php -l >/dev/null

php "$ROOT/tests/source-manifest-test.php"
php "$ROOT/tests/source-shape-test.php"
php "$ROOT/tests/backup-write-integrity-test.php"
php "$ROOT/tests/security-test.php"
php "$ROOT/tests/privacy-boundary-test.php"
php "$ROOT/tests/registration-test.php"
php "$ROOT/tests/woocommerce-product-boundary-test.php"

printf 'run-lab: PASS\n'
