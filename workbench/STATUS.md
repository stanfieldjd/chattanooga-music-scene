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
- Latest complete reference runtime: CMS Admin Workbench Lab run `34164417137` on commit `f52ee6431fb0111ea5e9499466a2f04fe034aa71`.
- Runtime artifact: `10033701473`, SHA-256 `e74eef622406878219d6cbd89d429befbfae2b2fe93d02771b1de013686a2712`.
- Passed: PHP 7.4/8.2, real WordPress 7.1 activation and 24-ability registry, permission isolation, REST isolation, database backup/restore including numeric primary-key fidelity, plugin/theme lifecycle and rollback, plugin/theme updates, package-network privacy, error-output sensitive-marker redaction, candidate-controlled theme switch/return, plugin/theme auto-update state persistence, core backup/restore, normal core update, forced plugin rollback, forced core rollback, and single-site cache invalidation.
- Active workbench gate: real WordPress 7.1 multisite cache execution.
- Remaining after multisite: corrupt/incomplete backup rejection and storage-failure handling; deterministic local update/no-update/malformed-package fixtures; DreamHost read-only preflight; actual Chattanooga MCP discovery; broader typed site-administration abilities.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Packaging workflow is present on `main`.
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before plugin updates, theme updates, event cleanup, venue repair, taxonomy changes, member administration, or other live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. A successful GitHub test does not establish production installation or behavior.
