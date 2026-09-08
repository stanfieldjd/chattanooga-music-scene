# Task: Chattanooga CMS Admin Runtime Integration

Status: LIVE_MCP_RUNTIME_VERIFIED — 82 ABILITIES EXPOSED / HEALTH + EXACT FESTIVAL TAXONOMY READS PASS / NO LIVE RELATIONSHIP WRITE AUTHORIZED

## Objective

Operate Chattanooga CMS Admin as the bounded production administration layer for Chattanooga Music Scene, preserving source integrity, exact-state conflict controls, rollback, and live-result verification before high-risk maintenance or content mutations.

## Target set

- Production Chattanooga CMS Admin plugin installation and its WordPress Abilities/MCP exposure.
- Read-only verification of the five published `Festival` event records already identified by the workstream.
- Source-controlled workbench records on `workbench/mars`.

## Exclusion set

- No live event taxonomy relationship mutation until the candidate abilities are exposed and a separate live-write authorization exists.
- No generic `mosmcp__cpt-remove-terms` substitution.
- No WPCode/media-upload/direct live-source workaround.
- No miniOrange transport/policy change without applicable authorization and a supported control path.
- No term create/update/delete capability.
- No changes to `main`, `feature/chattanooga-cms-admin`, unrelated plugins/themes/settings, members, navigation, bookings, tickets, payments, or event/location core state.

## Current verified candidate

- Candidate source checkpoint: `62e46bb64514973a640f1abd13ff5f90248580f8`.
- Product scope: single-site WordPress only. Multisite/network support is not an acceptance target.
- Reference registry: 82 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 content taxonomy + 8 navigation + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation + 3 event lifecycle + 3 event taxonomy.
- Latest post-checkpoint maintenance/workbench rerun: `34245410480` passed WordPress 7.1 runtime plus PHP 7.4 and PHP 8.2 labs.
- Latest post-checkpoint content/member/navigation rerun: `34245410459` passed.
- Latest post-checkpoint Events Manager rerun: `34245410524` passed against Events Manager 7.4.3.
- Latest post-checkpoint integrity rerun: `34245410436` passed.
- Previously recorded installable/runtime artifact remains source-checkpoint evidence; live installed-byte identity has not been independently reduced to an artifact digest and is therefore not claimed from version alone.

## Verified content administration

- Bounded post/page list/get/create-draft/update/trash/restore/revision-restore operations remain conflict checked and object-capability gated.
- Publication/status transitions retain exact-state and publish-authority controls.
- Category/post-tag term and relationship administration retains conflict/readback/rollback gates.
- Permanent post/page deletion remains a separate destructive contract with trash prerequisite, exact conflict state, explicit confirmation, native object authority, and absence verification.

## Verified core navigation administration

- Eight typed abilities cover bounded core WordPress navigation: list/get/create/rename/delete menus, create/update/delete items, and assign/unassign registered locations.
- Navigation mutations use native core APIs, exact state, readback, rollback where recoverable, destructive confirmation where appropriate, and preserve linked page content.
- The navigation layer does not mutate generic options, theme source, or third-party plugin source.

## Verified member administration

- Bounded member list/search/detail and role reads remain isolated from arbitrary usermeta, credentials, activation keys, and session tokens.
- Display-name/URL and role-state mutations require exact current state, native authority, readback, rollback after injected corruption, and zero notification mail attempts.
- Password/reset/session/account-creation/permanent-user-deletion operations remain outside this contract.

## Verified event and venue administration

- Four bounded read abilities cover list/get events and list/get locations.
- Four mutation abilities cover create/update for ordinary single events and physical venues/locations only.
- Expected-before state, readback, rollback/cleanup, publish/object authority, dependency-state preservation, and referenced-venue isolation are verified.
- Three event-lifecycle abilities cover trash, restore-to-draft, and permanent deletion for ordinary single events.
- Permanent deletion remains trash-first, exact-state, explicitly confirmed, object-authorized, booking-protected, absence-verified, and isolated from the referenced venue and unrelated events.
- Location deletion, booking deletion, ticket deletion, payment administration, and recurring-event administration remain outside the event lifecycle contract.

## Verified Events Manager taxonomy administration

- Reference Events Manager 7.4.3 can register `event-categories` and `event-tags` on the `event` post type. `event-categories` is hierarchical; `event-tags` is non-hierarchical.
- Native taxonomy assignment authority is `edit_events`; term lifecycle uses separate native manage/edit/delete capabilities and was not added to the candidate.
- Three typed abilities are verified in source/reference runtime:
  1. `chattanooga-cms-admin/list-event-taxonomy-terms` — bounded inspection of existing category/tag vocabulary.
  2. `chattanooga-cms-admin/get-event-taxonomy` — exact relationship read for one ordinary single event plus event conflict token.
  3. `chattanooga-cms-admin/set-event-taxonomy` — exact relationship replacement for one allowlisted taxonomy.
- `set-event-taxonomy` requires the exact current event state token and exact previous term-ID set. Stale event or relationship state fails closed.
- Requested target terms must already exist. No implicit term creation occurs.
- No-change relationship writes are rejected.
- Relationship clearing is explicit replacement with an empty set and does not delete the underlying term.
- Runtime fault injection proved failed post-write verification restores the exact prior relationship set.
- Event core state, referenced venue state, and an unrelated control event remain unchanged through taxonomy transactions.
- Third-party Events Manager source remains immutable dependency code.

## Gate 18 — live taxonomy inventory — DECISION SUFFICIENT

Read-only Chattanooga evidence on 2026-09-08 established:

- The live `event` type exposes `event-categories` and does not expose `event-tags` through the generic CPT surface.
- `Festival`: term ID `247`.
- `Music Festivals`: term ID `59`.
- `Live Music`: term ID `60`.
- The five published Festival-category events are WordPress post IDs `6810`, `7800`, `7803`, `7804`, and `7806`.
- Existing vocabulary is sufficient for the established primary-form classification rule; no Events Manager term create/update/delete capability is justified.

## Gate 19 — live installation and MCP discovery — HISTORICAL EXPOSURE BLOCKER

Fresh production verification after the user's installation established:

- WordPress reported `Chattanooga CMS Admin` version `0.1.0` as an active plugin.
- Production runtime was WordPress `7.1`, PHP `8.2.30`, single-site production.
- The current MCP connection resolved to WordPress user ID `2`, roles `administrator` and `bbp_keymaster`.
- The live `administrator` role explicitly had `edit_events`, `manage_options`, `activate_plugins`, `install_plugins`, `update_plugins`, and the other native capability families required by Chattanooga CMS Admin.
- Before the miniOrange policy grant was saved, `discover_abilities` returned zero abilities for category `chattanooga-cms-admin`.
- Candidate source registered the category on `wp_abilities_api_categories_init` and all ability families on `wp_abilities_api_init`; the reference WordPress registry remained green at 82 abilities.

This blocker is superseded by Gate 21, where the live namespace is execution-verified.

## Gate 20 — miniOrange governance contract — HISTORICAL CONFIGURATION DIAGNOSIS

Continuation checks on 2026-09-08 established:

- ChatGPT plugin permission inspection reported `MCP Server For WordPress` with app-specific permission mode `Allow all actions`.
- miniOrange's official MCP Server release notes documented NHI per-ability exposure controls, role-based grants, and the resource-by-role matrix.
- The same source documented governed MCP exposure without requiring public REST exposure, so candidate `show_in_rest=false` was preserved.
- The direct repair was the Administrator/NHI ability grant in miniOrange rather than a candidate source-code or public-REST change.

This diagnosis is superseded by Gate 21's successful live exposure verification.

## Gate 21 — live MCP exposure, health and exact Festival taxonomy reads — PASSED

Fresh production execution on 2026-09-08 established:

- After the user successfully saved the miniOrange policy, `discover_abilities(category="chattanooga-cms-admin")` returned all `82` candidate abilities.
- `chattanooga-cms-admin__get-health` executed successfully on production and reported WordPress `7.1`, PHP `8.2.30`, production environment, HTTPS enabled, database responding, direct filesystem method, plugin/theme/content directories writable, backup storage available and writable, ZipArchive available, cache enabled, cron enabled, maintenance mode off, and 512M memory limits.
- The first `get-event-taxonomy` call deliberately used the already-known WordPress post ID `6810` and returned `event not found`. This established that candidate event abilities do not accept the WordPress post ID as the event identifier.
- Bounded candidate `list-events` searches then resolved each exact title and independently matched its WordPress `post_id`, establishing the Events Manager event-ID domain without guessing:
  - WordPress post `6810` → Events Manager event `1119` — `3 Sisters Bluegrass Festival`.
  - WordPress post `7800` → Events Manager event `1180` — `Chattanooga Oktoberfest`.
  - WordPress post `7803` → Events Manager event `1181` — `IBMA World of Bluegrass`.
  - WordPress post `7804` → Events Manager event `1182` — `Chattanooga Bluegrass`.
  - WordPress post `7806` → Events Manager event `1183` — `Chattanooga Jazz Fest`.
- Fresh candidate `get-event-taxonomy` reads for `event-categories` returned these exact relationship sets and event-state tokens:
  - event `1119`: terms `[59,60,247]`; token `274cdedf2874946fbd7c8ea61a281891f1e8727b829bda9755473db3af26b711`.
  - event `1180`: terms `[60,247,252,256]`; token `8f03f8a014f54fa71f3a0612bd73b6177d7c3227125c06aa692d8584e0d6910d`.
  - event `1181`: terms `[59,60,247]`; token `a3877ad63e6befc067f3ec7f35cfe7b49d177ef6ff3583a4ad44dff311699740`.
  - event `1182`: terms `[60,247,252]`; token `52ab49e34c38fad7a1168d9805482e0a83c0d72f3955ad5a55a320e33874b10e`.
  - event `1183`: terms `[59,60,247,252]`; token `3673df9b98bcb974d2036bdfde497c3c7a8f5e59634347e00aef65fd3b6b00e1`.
- Each taxonomy-read token exactly matched the state token from the corresponding fresh candidate event search at the time of inspection.
- All five exact relationship sets still include `Live Music` term `60`, so the previously identified 5/5 classification defect is now execution-verified through the candidate's own exact-state contract.
- No `set-event-taxonomy` call was made. No live content, taxonomy, event core, location, plugin/theme, or production source mutation occurred during this validation sequence.

Gate 21 closes the production MCP exposure and read-runtime acceptance gate. The remaining Festival relationship repair is a separate live mutation requiring explicit target-specific authorization.

## Fresh live Festival relationship checkpoint

Current exact candidate relationship observations are:

- `6810` / event `1119` — `3 Sisters Bluegrass Festival` — terms `[59 Music Festivals, 60 Live Music, 247 Festival]`.
- `7800` / event `1180` — `Chattanooga Oktoberfest` — terms `[60 Live Music, 247 Festival, 252 Family Friendly, 256 Food]`.
- `7803` / event `1181` — `IBMA World of Bluegrass` — terms `[59 Music Festivals, 60 Live Music, 247 Festival]`.
- `7804` / event `1182` — `Chattanooga Bluegrass` — terms `[60 Live Music, 247 Festival, 252 Family Friendly]`.
- `7806` / event `1183` — `Chattanooga Jazz Fest` — terms `[59 Music Festivals, 60 Live Music, 247 Festival, 252 Family Friendly]`.

The overlap remains 5/5. The tokens recorded in Gate 21 are evidence snapshots only and must be refreshed immediately before any separately authorized live write.

## Pre-execution control record for any future Festival relationship mutation

- `OBJECTIVE`: correct only the established Festival-vs-Live-Music primary-form relationship defect on the five exact live target events.
- `TARGET_SET`: Events Manager event IDs `1119`, `1180`, `1181`, `1182`, and `1183`, corresponding exactly to WordPress post IDs `6810`, `7800`, `7803`, `7804`, and `7806`.
- `EXCLUSION_SET`: every non-target event, every term object, event core/location state, bookings/tickets/payments, plugin/theme/source state, unrelated taxonomy relationships, and all other site state.
- `EVIDENCE`: 82 candidate abilities exposed; health/read runtime passed; exact title/post-ID-to-event-ID mappings verified; candidate exact relationship reads show term `60` on all five target events; existing target vocabulary is sufficient.
- `MUTATION_SET`: not authorized in the current task position. If separately authorized, exact replacement would remove term `60` only and preserve every other freshly observed category.
- `RISK_SET`: stale tokens/relationships could reject a write; wrong event-ID domain could target the wrong record; broad/generic taxonomy mutation would bypass the candidate's exact-state and rollback protections.
- `ROLLBACK_POINT`: candidate `set-event-taxonomy` preserves the prior exact relationship set and automatically restores it on failed verification; a fresh pre-write read is still mandatory.
- `ACCEPTANCE_TESTS`: each authorized target begins from a fresh exact token/set; only term `60` is removed; every other category remains; post-write exact readback passes; event core/location state remains unchanged; non-target records remain untouched.

Execution remains blocked at this mutation gate until explicit target-specific live-write authorization exists.

## Current production boundary

- Chattanooga CMS Admin is active on production.
- All 82 Chattanooga CMS Admin abilities are exposed through the current MCP connection.
- Live candidate health and bounded exact event-taxonomy reads execute successfully.
- The five Festival relationship defects are exact-state verified through the candidate's own event-taxonomy contract.
- No live content or taxonomy relationship was modified during Gate 21.
- No candidate/source change, public REST relaxation, generic taxonomy workaround, or transport replacement was used.
- `main`, `feature/chattanooga-cms-admin`, DreamHost production source files, unrelated content/member/event/navigation records, and third-party plugin source remain untouched by the workbench.
- The five Festival relationship writes remain a separate live mutation requiring explicit target-specific live-write authorization.

## Recalculated next position

The production MCP exposure/read-runtime objective is satisfied. Do not add speculative source capability and do not use generic taxonomy mutation. The next unresolved workstream is the five-event Festival relationship repair. Before any authorized write, re-resolve each live event identity if necessary and obtain a fresh `get-event-taxonomy` state token and relationship set immediately before `set-event-taxonomy`; then preserve all terms except `Live Music` term `60`, verify exact readback, and rely on the candidate's rollback path if verification fails.