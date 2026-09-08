# Workbench Status

Last verified: 2026-09-08

## Repository baseline

- Repository: `stanfieldjd/chattanooga-music-scene`
- Production source branch: `main`
- Verified `main` head: `d3167ab8d084523c63d22004955d781962e41623`
- Workbench branch: `workbench/mars`
- Production/source branches were not mutated by the latest workbench increment.

## Active source workstreams

### Chattanooga CMS Admin

- Branch: `feature/chattanooga-cms-admin` remains at verified source checkpoint `0b34773ebc8073cb657477770b34cabc280f5892` and was not modified.
- Source path: `site-plugins/chattanooga-cms-admin`.
- Workbench lab: `workbench/labs/chattanooga-cms-admin`.
- Immutable lab baseline remains an exact Git-blob mirror of the verified source checkpoint.
- Mutable coding candidate: `workbench/labs/chattanooga-cms-admin/candidate`.
- Current verified candidate source checkpoint: `62e46bb64514973a640f1abd13ff5f90248580f8`.
- Real registry: 82 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 content taxonomy + 8 navigation + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation + 3 event lifecycle + 3 event taxonomy.
- Full single-site maintenance regression: run `34243687257` passed on PHP 7.4, PHP 8.2, and disposable WordPress 7.1.
- Runtime capability artifact: `10063123311`, SHA-256 `0cf3d8288731e5a5be8a5a5c7ef6528332f12f01387a38a1f65668a9f07ea24b`.
- Content/member/navigation regression: run `34243687429` passed.
- Workbench integrity: run `34243687283` passed.
- Events Manager regression: run `34243687468` passed against WordPress.org Events Manager 7.4.3.
- Event taxonomy contract verified: `event-categories` and `event-tags` are registered on `event`; assignment uses native `edit_events` authority; exact assignment and relationship clearing work; relationship clearing leaves the terms themselves intact.
- Three bounded event-taxonomy abilities are verified: list existing event taxonomy terms, read one ordinary single event's exact category/tag relationship set, and replace that exact relationship set.
- Event taxonomy mutation requires exact event state plus exact previous relationship state, validates target terms already exist, rejects stale/no-change writes, verifies readback, rolls relationships back after injected verification failure, and preserves event core state, referenced venue state, and an unrelated control event.
- Term creation/update/deletion was deliberately not added. Recurrence, media, bookings, tickets, payments, account-security operations, widgets/templates, location deletion, generic option mutation, and multisite/network behavior remain outside automatic expansion.
- DreamHost read-only preflight remains partial. Candidate installation and live MCP discovery are still separate production gates.
- Next gate: obtain fresh read-only live event-taxonomy vocabulary/relationship evidence before deciding whether any term-lifecycle ability or live classification repair is actually necessary.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene by this workbench increment.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`.
- Source version on verified baseline: `0.2.1`.
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before any live mutation, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. Successful GitHub/reference-runtime tests do not establish production installation or live behavior.
