#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo_root"

required_files=(
  "workbench/README.md"
  "workbench/STATUS.md"
  "workbench/TASK_QUEUE.md"
  "workbench/PROTOCOL.md"
  "workbench/JOURNAL.md"
  "workbench/state/current.json"
  "workbench/templates/task.md"
  "workbench/tasks/chattanooga-cms-admin-runtime.md"
)

for file in "${required_files[@]}"; do
  if [[ ! -s "$file" ]]; then
    echo "Missing or empty required workbench file: $file" >&2
    exit 1
  fi
done

python3 -m json.tool workbench/state/current.json >/dev/null
bash -n workbench/scripts/check-workbench.sh

grep -q '"repository": "stanfieldjd/chattanooga-music-scene"' workbench/state/current.json
grep -q '"workbench_branch": "workbench/mars"' workbench/state/current.json
grep -q 'd3167ab8d084523c63d22004955d781962e41623' workbench/state/current.json

echo "Workbench integrity check passed."
