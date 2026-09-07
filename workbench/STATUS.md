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
- Latest complete single-site/full regression: run `34166093601` on commit `04139cb2cc8da95af1e79cf6bb50f08062c2b77a`; artifact `10034214148`, SHA-256 `da9258afb3694f6a10c2854c8d963b207fa1bdbd7e49c2dd22745ed48d5a4aa6`.
- Real multisite cache execution: run `34164758622`; exact cache result `object-cache,wordpress-blog-cache`.
- Backup fail-closed evidence: corrupted/missing rollback material is rejected before restore with target state unchanged, and all configured backup paths being non-writable returns failure before backup creation.
- Deterministic plugin-update edge evidence: no-update is rejected, local v1→v2 succeeds with activation preserved, and malformed local package failure restores exact active v1 state from the verified rollback backup.
- Passed: PHP 7.4/8.2, real WordPress 7.1 activation and 24-ability registry, permission/REST isolation, DB backup/restore and numeric PK fidelity, plugin/theme lifecycle and rollback, package privacy, error redaction, theme switch/return and auto-update policy, plugin/theme updates, deterministic plugin update edges, core update/rollback, single-site cache, real multisite cache, corrupt/missing rollback rejection, and unavailable-storage rejection.
- Active workbench gate: partial/stalled backup-write integrity. The current database dump writer is being tested for incomplete stream writes before that storage-integrity path can be promoted.
- Remaining after that: DreamHost read-only preflight; actual Chattanooga MCP discovery; broader typed site-administration abilities.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. A successful GitHub test does not establish production installation or behavior.
