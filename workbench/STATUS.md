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
- Latest complete single-site/full regression: run `34165007012` on commit `a42e0be1e4fb52c48aee0617078a0f43d53f7ae3`; artifact `10033890050`, SHA-256 `d47cb1d77e97f8cddda5825f924dfd1f9b50e14698a9de5d87dc69e55c50abf5`.
- Real multisite cache execution: run `34164758622` on commit `a81c962c007163698de13b2d9b4f8bfe7bfcac7c`; WordPress 7.1 multisite installed, candidate network-activated, and cache result was `object-cache,wordpress-blog-cache`.
- Corrupt/incomplete backup rejection: run `34165007012` passed corrupted component checksum rejection, missing component archive rejection, and corrupted database checksum rejection before restore, with targets unchanged.
- Passed: PHP 7.4/8.2, real WordPress 7.1 activation and 24-ability registry, permission/REST isolation, DB backup/restore and numeric PK fidelity, plugin/theme lifecycle and rollback, package privacy, error redaction, theme switch/return and auto-update policy, plugin/theme updates, core update/rollback, single-site cache, real multisite cache, and corrupt/missing rollback fail-closed behavior.
- Active workbench gate: all configured backup storage paths unavailable/read-only must fail before backup creation.
- Remaining after that: partial-write/disk-space behavior; deterministic local update/no-update/malformed-package fixtures; DreamHost read-only preflight; actual Chattanooga MCP discovery; broader typed site-administration abilities.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. A successful GitHub test does not establish production installation or behavior.
