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
- Latest full single-site maintenance regression: run `34244282299` passed on PHP 7.4, PHP 8.2, and disposable WordPress 7.1.
- Latest runtime capability artifact: `10063358784`, SHA-256 `f3f13c5a2d603fcecaca8de458df18538a9216f42c2656e6eaa7e296a44695b7`.
- Latest content/member/navigation regression: run `34244282476` passed.
- Latest workbench integrity: run `34244282480` passed.
- Latest Events Manager regression: run `34244282382` passed against WordPress.org Events Manager 7.4.3.
- Event taxonomy contract verified in the reference runtime: `event-categories` and `event-tags` can be registered on `event`; assignment uses native `edit_events` authority; exact assignment and relationship clearing work; relationship clearing leaves the terms themselves intact.
- Three bounded event-taxonomy abilities are verified in the candidate: list existing event taxonomy terms, read one ordinary single event's exact category/tag relationship set, and replace that exact relationship set.
- Event taxonomy mutation requires exact event state plus exact previous relationship state, validates target terms already exist, rejects stale/no-change writes, verifies readback, rolls relationships back after injected verification failure, and preserves event core state, referenced venue state, and an unrelated control event.
- Fresh live Chattanooga evidence confirms the current `event` type exposes `event-categories` with 11 terms but does not expose `event-tags`; the candidate already fails closed when an allowlisted taxonomy is unavailable.
- The live target vocabulary is sufficient: `Festival` is term 247, `Music Festivals` is term 59, and `Live Music` is term 60. All are top-level categories.
- All five currently published events assigned `Festival` — IDs `6810`, `7800`, `7803`, `7804`, and `7806` — are also assigned `Live Music`. Three of those five are also assigned `Music Festivals`.
- Therefore the current festival-vs-Live-Music issue is a relationship-classification issue, not missing taxonomy vocabulary. No event term create/update/delete capability is justified by current evidence.
- The live connector reports 11 event categories but provides no read-only list-all-categories ability; full catalogue naming is therefore incomplete, while the vocabulary required for the current classification decision was independently verified through category IDs and filtered event reads.
- DreamHost read-only preflight remains partial. Candidate installation and live MCP discovery are still separate production gates.
- Next gate: no additional taxonomy source capability is justified. Candidate installation/live discovery and any live relationship repair require separate production authorization and a fresh pre-mutation read.
- Production state: not merged to `main`; not installed on Chattanooga Music Scene by this workbench increment; no live taxonomy/content writes were made.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`.
- Source version on verified baseline: `0.2.1`.
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before any live mutation, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, and live verification remain separate states. Successful GitHub/reference-runtime tests do not establish production installation or live behavior.
