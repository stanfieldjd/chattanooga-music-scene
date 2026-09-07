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
- Latest complete single-site/full regression: run `34166835095` on commit `286a8d15d905b60380b5173cb084fabfd7c3ce43`; artifact `10034438937`, SHA-256 `24ee7663f8ec92d6e0c7bf99b59c5790587f31f1e0e7ab9cfebc81a7a6c9cbc1`.
- Latest real multisite regression: run `34166835071` on the same candidate checkpoint.
- Backup fail-closed evidence now includes checksum corruption, missing rollback material, all backup locations unavailable, progressive/stalled database writes, and component/core ZIP finalization failure.
- Storage repairs: database backup writes must complete every byte or abort/remove incomplete SQL; backup metadata must be written completely; component/core ZIP close failure or zero-byte output is rejected and removed.
- Deterministic plugin-update edge evidence: no-update is rejected, local v1→v2 succeeds with activation preserved, and malformed local package failure restores exact active v1 state from the verified rollback backup.
- Passed: PHP 7.4/8.2, real WordPress 7.1 activation and 24-ability registry, permission/REST isolation, DB backup/restore and numeric PK fidelity, plugin/theme lifecycle and rollback, package privacy, error redaction, theme switch/return and auto-update policy, plugin/theme updates, deterministic plugin update edges, core update/rollback, single-site cache, real multisite cache, corrupt/missing rollback rejection, unavailable-storage rejection, partial-write integrity, and archive-finalization integrity.
- Next workbench/runtime gate: Chattanooga/DreamHost read-only preflight using only facts exposed by the connected environment; unavailable server-level facts remain UNKNOWN.
- Remaining after preflight: separately authorized candidate installation/MCP discovery and broader typed site-administration abilities.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. A successful GitHub test does not establish production installation or behavior.
