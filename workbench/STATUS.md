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
- All five currently published events assigned `Festival` — IDs `6810`, `7800`, `7803`, `7804`, and `7806` — were freshly re-read after installation and still also carry `Live Music`.
- Fresh titles and relationship sets: `6810 3 Sisters Bluegrass Festival` = 247/60/59; `7800 Chattanooga Oktoberfest` = 252/247/256/60; `7803 IBMA World of Bluegrass` = 247/60/59; `7804 Chattanooga Bluegrass` = 252/247/60; `7806 Chattanooga Jazz Fest` = 252/247/60/59.
- Therefore the current festival-vs-Live-Music issue remains a relationship-classification issue under the existing project rule; no event term create/update/delete capability is justified by current evidence.
- Live installation is execution-verified: WordPress production reports `Chattanooga CMS Admin` version `0.1.0` active on WordPress 7.1 / PHP 8.2.30.
- The current MCP connection is execution-verified as WordPress user ID 2 with roles `administrator` and `bbp_keymaster`.
- Live MCP discovery currently exposes zero abilities in category/namespace `chattanooga-cms-admin`; searches for the candidate event-taxonomy abilities likewise return zero. Therefore the production integration gate is not complete even though the plugin is active.
- Candidate source explicitly registers the Chattanooga CMS Admin category and all abilities on `wp_abilities_api_init`, with `meta.public=true`, `meta.mcp.public=true`, and per-ability WordPress capability callbacks. Reference WordPress registration tests remain green.
- The exact miniOrange exposure configuration is not inspectable through the currently exposed MCP abilities. Current evidence localizes the unresolved boundary to live MCP ability exposure/governance rather than proving an application-code registration defect.
- No generic `mosmcp__cpt-remove-terms`, WPCode, media-upload, or direct production-source workaround was used.
- Next gate: expose/grant the `chattanooga-cms-admin/*` abilities through the active MCP server, then re-run live discovery and candidate health/read checks. Only after that may a separately authorized live relationship repair obtain fresh candidate conflict tokens and perform guarded exact-state writes.
- Production state: plugin active; candidate MCP namespace not exposed; no live taxonomy/content writes were made by this workbench increment.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`.
- Source version on verified baseline: `0.2.1`.
- Production state must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before any live mutation, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, live MCP exposure, and live mutation verification remain separate states. Successful GitHub/reference-runtime tests do not establish production behavior.