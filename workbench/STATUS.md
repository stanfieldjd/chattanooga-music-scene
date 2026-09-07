# Workbench Status

Last verified: 2026-09-07

## Repository baseline

- Repository: `stanfieldjd/chattanooga-music-scene`
- Production source branch: `main`
- Verified `main` head: `d3167ab8d084523c63d22004955d781962e41623`
- Workbench branch: `workbench/mars`

## Active source workstreams

### Chattanooga CMS Admin

- Branch: `feature/chattanooga-cms-admin`
- Verified source checkpoint: `0b34773ebc8073cb657477770b34cabc280f5892`
- Path: `site-plugins/chattanooga-cms-admin`
- Workbench lab: `workbench/labs/chattanooga-cms-admin`
- Immutable lab baseline: exact Git-blob mirror of the verified source checkpoint.
- Mutable coding candidate: `workbench/labs/chattanooga-cms-admin/candidate`.
- Latest complete single-site/full regression: run `34165355234` on commit `7b6e25b4a1103f260fdf36237482d9e7c7787934`; artifact `10033988433`, SHA-256 `bea034a0a86a3de8ed85208cf61a059a996ae64ff45eaae3cfefd640479d28df`.
- Real multisite cache execution: run `34164758622`; exact cache result `object-cache,wordpress-blog-cache`.
- Backup fail-closed evidence: corrupted/missing rollback material is rejected before restore with target state unchanged, and all configured backup paths being non-writable returns failure before backup creation.
- Exact unavailable-storage result: `storage-failure-cli: PASS all-backup-paths=unwritable backup=not-created`.
- Passed: PHP 7.4/8.2, real WordPress 7.1 activation and 24-ability registry, permission/REST isolation, DB backup/restore and numeric PK fidelity, plugin/theme lifecycle and rollback, package privacy, error redaction, theme switch/return and auto-update policy, plugin/theme updates, core update/rollback, single-site cache, real multisite cache, corrupt/missing rollback rejection, and unavailable-storage rejection.
- Active workbench gate: deterministic local plugin update edge cases — no-update, v1→v2 success, malformed package rollback.
- Remaining after that: partial-write/disk-space backup failure; DreamHost read-only preflight; actual Chattanooga MCP discovery; broader typed site-administration abilities.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. A successful GitHub test does not establish production installation or behavior.
