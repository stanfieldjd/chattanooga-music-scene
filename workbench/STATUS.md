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
- Validation: PHP 7.4 syntax workflow passed.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene.
- Next gate: runtime integration and live WordPress ability discovery after an authorized deployment path exists.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`
- Source version on verified baseline: `0.2.1`
- Packaging workflow is present on `main`.
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before plugin updates, theme updates, event cleanup, venue repair, taxonomy changes, or other live mutations, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

Source work, deployment, and live verification are separate states. A successful GitHub commit or CI run does not establish installation, deployment, activation, or correct live behavior.
