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
- Latest complete single-site/full regression: run `34164758599` on commit `a81c962c007163698de13b2d9b4f8bfe7bfcac7c`; artifact `10033803387`, SHA-256 `c3ca4bec92448b3b46f19e225dd81b9b2e0343fb07025a82d6ce0beb1fca6787`.
- Real multisite cache execution: run `34164758622` on the same commit; WordPress 7.1 multisite installed, candidate network-activated, and cache result was `object-cache,wordpress-blog-cache`.
- Passed: PHP 7.4/8.2, real WordPress 7.1 activation and 24-ability registry, permission/REST isolation, DB backup/restore and numeric PK fidelity, plugin/theme lifecycle and rollback, package privacy, error redaction, theme switch/return and auto-update policy, plugin/theme updates, core update/rollback, single-site cache, and real multisite cache.
- Active workbench gate: corrupted/missing rollback material must be rejected before restore without mutating the target.
- Remaining after that: storage/partial-write failure handling; deterministic local update/no-update/malformed-package fixtures; DreamHost read-only preflight; actual Chattanooga MCP discovery; broader typed site-administration abilities.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. A successful GitHub test does not establish production installation or behavior.
