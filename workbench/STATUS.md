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
- Real reference registry: 82 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 content taxonomy + 8 navigation + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation + 3 event lifecycle + 3 event taxonomy.
- Latest post-checkpoint maintenance/workbench rerun `34245410480` passed the WordPress 7.1 runtime probe and PHP 7.4/8.2 labs.
- Latest post-checkpoint content/member/navigation rerun `34245410459` passed.
- Latest post-checkpoint Events Manager rerun `34245410524` passed against WordPress.org Events Manager 7.4.3.
- Prior integrity run `34245410436` passed.
- Event taxonomy contract verified in the reference runtime: `event-categories` and `event-tags` can be registered on `event`; assignment uses native `edit_events` authority; exact assignment and relationship clearing work; relationship clearing leaves the terms themselves intact.
- Three bounded event-taxonomy abilities are verified in the candidate: list existing event taxonomy terms, read one ordinary single event's exact category/tag relationship set, and replace that exact relationship set.
- Event taxonomy mutation requires exact event state plus exact previous relationship state, validates target terms already exist, rejects stale/no-change writes, verifies readback, rolls relationships back after injected verification failure, and preserves event core state, referenced venue state, and an unrelated control event.
- Fresh live Chattanooga evidence confirms the current `event` type exposes `event-categories` but does not expose `event-tags`; the candidate already fails closed when an allowlisted taxonomy is unavailable.
- The live target vocabulary is sufficient: `Festival` is term 247, `Music Festivals` is term 59, and `Live Music` is term 60.
- All five currently published events assigned `Festival` — WordPress post IDs `6810`, `7800`, `7803`, `7804`, and `7806` — still also carry `Live Music`.
- Live MCP event-ID mapping is now exact and independently read through Chattanooga CMS Admin: `6810→1119`, `7800→1180`, `7803→1181`, `7804→1182`, `7806→1183`.
- Fresh exact candidate relationship reads returned: event `1119` = `[59,60,247]`; `1180` = `[60,247,252,256]`; `1181` = `[59,60,247]`; `1182` = `[60,247,252]`; `1183` = `[59,60,247,252]`.
- Therefore the current festival-vs-Live-Music issue remains a relationship-classification issue under the existing project rule; no event term create/update/delete capability is justified by current evidence.
- Live installation is execution-verified: WordPress production reports `Chattanooga CMS Admin` version `0.1.0` active on WordPress 7.1 / PHP 8.2.30.
- The current MCP connection is execution-verified as WordPress user ID 2 with roles `administrator` and `bbp_keymaster`.
- The live `administrator` capability list explicitly includes `edit_events`, `manage_options`, `activate_plugins`, `install_plugins`, `update_plugins`, and the other native authorities required by the candidate families.
- ChatGPT plugin permissions for `MCP Server For WordPress` are set to `Allow all actions`.
- miniOrange policy was successfully saved by the user and live discovery now exposes all 82 `chattanooga-cms-admin` abilities through the current MCP connection.
- Live `chattanooga-cms-admin__get-health` execution passed on production: database responding, direct filesystem method, plugin/theme/content directories writable, backup storage available+writable, ZipArchive available, maintenance mode off, and HTTPS enabled.
- The first exact taxonomy call using WordPress post ID `6810` correctly failed `event not found`; bounded candidate event search established that this API's `id` is the Events Manager event ID, not the WordPress event post ID. The exact five mappings above were then verified by both title and `post_id` before taxonomy reads proceeded.
- Each `get-event-taxonomy` state token exactly matched the corresponding state token returned by the fresh candidate event search at the time of inspection.
- Candidate source continues to keep `show_in_rest=false`; no public-REST relaxation or generic taxonomy workaround was needed.
- No generic `mosmcp__cpt-remove-terms`, WPCode, media-upload, direct production-source workaround, or source change was used.
- Next gate: a live five-event relationship repair is a separate target-specific mutation and remains unauthorized. If authorized, refresh each candidate taxonomy snapshot immediately before its write, remove only term `60` while preserving every other current category, and verify readback through the guarded candidate ability.
- Production state: plugin active; 82 candidate MCP abilities exposed; health/read runtime acceptance passed; five exact taxonomy defects execution-verified; no live taxonomy/content writes were made by this workbench increment.

### WordPress / plugin / theme maintenance

- Fresh live `chattanooga-cms-admin__list-updates` execution reports WordPress `7.1` as current/latest; no core update is offered.
- 9 active plugins currently have offered updates: Big File Uploads `2.1.9→2.2.0`, Hide Page And Post Title `1.5.8→1.6.2`, Plugin Check `2.0.0→2.1.0`, PublishPress Capabilities `2.50.0→2.50.1`, Site Kit by Google `1.185.0→1.187.0`, WooCommerce `11.0.1→11.1.0`, WooCommerce Shipping `2.3.13→2.3.16`, WooCommerce Tax `3.6.12→3.6.15`, and WPCode Lite `2.3.8→2.3.9`.
- 4 inactive themes currently have offered updates: BuddyX `5.1.5→5.1.7`, Twenty Twenty-Four `1.5→1.6`, Twenty Twenty-Three `1.6→1.7`, and Twenty Twenty-Two `2.1→2.2`.
- The active theme remains `BuddyX Child - River Rhythms v5`; BuddyX is its inactive parent and therefore still carries active-site compatibility risk if later updated.
- No update, auto-update policy, activation state, install/delete action, live source, or content change was performed by this inventory pass.
- Maintenance execution is not authorized by the current continuation. Any later live update must target one exact component, begin with fresh inventory/health evidence, use the candidate rollback contract, and validate the post-update live state before moving to another component.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`.
- Source version on verified baseline: `0.2.1`.
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before any live mutation, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, live MCP exposure, and live mutation verification remain separate states. Successful GitHub/reference-runtime tests do not establish production behavior.
