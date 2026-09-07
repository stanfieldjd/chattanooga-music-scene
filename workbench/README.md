# Mars Workbench

Persistent GitHub control workspace for ChatGPT work on Chattanooga Music Scene.

## Purpose

This branch is the coordination layer for ongoing engineering and site-administration work. It records the verified repository position, active workstreams, task control records, validation evidence, and the handoff state between source work and production work.

The workbench is not production and does not imply that a source change has been deployed to WordPress.

## Branch

- Workbench branch: `workbench/mars`
- Baseline branch: `main`
- Baseline commit at creation: `d3167ab8d084523c63d22004955d781962e41623`

## Layout

- `STATUS.md` — current human-readable position.
- `TASK_QUEUE.md` — ordered work queue and blocking state.
- `PROTOCOL.md` — repeatable operating procedure for source work.
- `state/current.json` — machine-readable repository/workstream state.
- `tasks/` — task control records with objective, scope, rollback, and acceptance tests.
- `templates/task.md` — canonical task-record template.
- `JOURNAL.md` — append-only workbench change journal.
- `scripts/check-workbench.sh` — structural integrity check used by CI.

## Operating boundary

1. Read the authoritative operating rules before material work.
2. Re-read `STATUS.md`, `TASK_QUEUE.md`, and `state/current.json` before continuing a workstream.
3. Observe the current source branch and commit before changing it.
4. Create or use a task-specific branch for source changes; do not treat the workbench branch as a deployment branch.
5. Record rollback and acceptance tests before material mutation.
6. Run the applicable validation workflow before promotion.
7. Verify production independently after deployment; source success is not live-site success.
8. Update the workbench state after every material result.

## Authoritative operating rules

The workbench references, but does not replace or summarize as authority, `JD-Operating-Rules-Ledger-v1.0.md`. The exact ledger remains the governing source and must be reloaded directly when required.
