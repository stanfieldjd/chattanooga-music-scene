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
- Source validation: PHP 7.4 syntax workflow passed.
- Workbench lab: `workbench/labs/chattanooga-cms-admin`
- Immutable lab baseline: exact Git-blob mirror of the verified source checkpoint.
- Mutable coding candidate: `workbench/labs/chattanooga-cms-admin/candidate`.
- Reference runtime: disposable WordPress 7.1 + PHP 8.2 + MySQL 8.0 in GitHub Actions.
- Latest execution evidence: CMS Admin Workbench Lab run `34118396822` on commit `76f7f8a05f07014b13712b011cfec9fb69d0b666`.
- Passed: PHP 7.4/8.2 lab, source integrity, security/architecture scan, 24-ability stub registration, candidate activation on real WordPress 7.1, 24-ability real registry verification, database backup/checksum, controlled plugin component backup/mutation/restore.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.
- Remaining runtime gates: DreamHost filesystem/policy probe, actual Chattanooga MCP discovery, permission matrix, REST exposure verification, database/core restore tests, update transactions, package-network privacy capture, and broader content/member/event/commerce ability implementation.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Packaging workflow is present on `main`.
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before plugin updates, theme updates, event cleanup, venue repair, taxonomy changes, member administration, or other live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. A successful GitHub test does not establish production installation or behavior.
