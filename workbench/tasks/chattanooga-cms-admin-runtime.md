# Task: Chattanooga CMS Admin Runtime Integration

Status: LIVE_PLUGIN_ACTIVE_MCP_EXPOSURE_BLOCKED — MINI ORANGE ROLE/ABILITY GOVERNANCE CONFIRMED / FESTIVAL RELATIONSHIPS FRESHLY REVERIFIED / NO LIVE RELATIONSHIP WRITE AUTHORIZED

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
- The five published Festival-category events are IDs `6810`, `7800`, `7803`, `7804`, and `7806`.
- Existing vocabulary is sufficient for the established primary-form classification rule; no Events Manager term create/update/delete capability is justified.

## Gate 19 — live installation and MCP discovery — ACTIVE PLUGIN / EXPOSURE BLOCKED

Fresh production verification after the user's installation established:

- WordPress reports `Chattanooga CMS Admin` version `0.1.0` as an active plugin.
- Production runtime is WordPress `7.1`, PHP `8.2.30`, single-site production.
- The current MCP connection resolves to WordPress user ID `2`, roles `administrator` and `bbp_keymaster`.
- The live `administrator` role explicitly has `edit_events`, `manage_options`, `activate_plugins`, `install_plugins`, `update_plugins`, and the other native capability families required by Chattanooga CMS Admin. The taxonomy permission callback's required `edit_events` capability is therefore satisfied on this connection.
- `discover_abilities` returns zero abilities for category `chattanooga-cms-admin` and zero matches for the candidate event-taxonomy namespace/relationship searches.
- Candidate source registers the category on `wp_abilities_api_categories_init` and all ability families on `wp_abilities_api_init`.
- Candidate ability metadata sets `public=true`, `mcp.public=true`, and capability-specific `permission_callback` checks. The taxonomy family requires `edit_events`.
- Reference WordPress registration remains green at 82 abilities.

Verified conclusion: installation/activation is confirmed and the WordPress role-capability gate is satisfied, but live MCP integration acceptance is not met because the candidate namespace is not exposed to this connector. The currently available connector does not expose miniOrange ability-policy/NHI configuration controls, so the exact live policy row/toggle cannot be directly inspected from this surface. The evidence localizes the unresolved boundary to live MCP exposure/governance; it does not prove a candidate registration-code defect.

No workaround mutation was performed.

## Gate 20 — miniOrange governance contract — CONFIGURATION GATE CONFIRMED

Fresh continuation checks on 2026-09-08 established:

- Re-running `discover_abilities` for category `chattanooga-cms-admin` still returns zero abilities.
- Searches for MCP/ability/server/access controls on the current WordPress MCP surface expose no miniOrange self-management ability capable of changing NHI/role ability grants.
- ChatGPT plugin permission inspection reports `MCP Server For WordPress` with an app-specific permission mode of `Allow all actions`; ChatGPT-side plugin permission mode is therefore not the cause of the missing namespace.
- miniOrange's official MCP Server release notes state that version 1.2.0 added the NHI Registry and per-ability enable/disable controls for MCP exposure.
- The same official release notes state that version 1.2.2 made the NHI Registry role-based: abilities are granted to WordPress roles, and an MCP request receives the abilities granted to its user's role(s) across enabled NHIs.
- Version 1.4.2 redesigned the role/ability editor as a matrix with abilities/resources as rows and WordPress roles as columns, including resource- and role-level select/clear controls.
- Version 1.4.0 explicitly states that miniOrange's bundled abilities are reachable through the governed MCP endpoint while remaining unavailable through the public REST API. Therefore the candidate's `show_in_rest=false` requirement is not, by itself, evidence of MCP incompatibility and must not be relaxed merely to force discovery.
- miniOrange's current product documentation describes MCP tool discovery as exposure of approved WordPress abilities after identity and role-based permission evaluation.
- Authoritative vendor source consulted: `https://plugins.miniorange.com/mcp-server-ai-policy-enforcement-wordpress-changelog` and `https://plugins.miniorange.com/native-mcp-server-endpoint-wordpress`, accessed 2026-09-08.

Verified conclusion: the missing Chattanooga CMS Admin namespace is consistent with the miniOrange governance model requiring an explicit role/NHI ability grant. Current evidence does not justify changing candidate registration code, enabling public REST exposure, or bypassing miniOrange with a generic mutation route. The direct repair is to grant the `chattanooga-cms-admin` resource/abilities to the current Administrator role in miniOrange's role/ability editor for the enabled NHI used by this connection, then rerun MCP discovery.

No miniOrange policy mutation was performed because the connected MCP surface exposes no supported control for that setting.

## Fresh live Festival relationship checkpoint after installation

All five target records were re-read from production after the plugin became active. Current exact generic-CPT relationship observations are:

- `6810` — `3 Sisters Bluegrass Festival` — published — modified `2026-08-03 16:14:42` — terms `[247 Festival, 60 Live Music, 59 Music Festivals]`.
- `7800` — `Chattanooga Oktoberfest` — published — modified `2026-08-18 11:45:31` — terms `[252 Family Friendly, 247 Festival, 256 Food, 60 Live Music]`.
- `7803` — `IBMA World of Bluegrass` — published — modified `2026-08-18 13:03:44` — terms `[247 Festival, 60 Live Music, 59 Music Festivals]`.
- `7804` — live title `Chattanooga Bluegrass` — published — modified `2026-09-07 05:28:26` — terms `[252 Family Friendly, 247 Festival, 60 Live Music]`.
- `7806` — `Chattanooga Jazz Fest` — published — modified `2026-08-18 11:50:28` — terms `[252 Family Friendly, 247 Festival, 60 Live Music, 59 Music Festivals]`.

The overlap remains 5/5. These generic reads are evidence of the current relationship sets but are not substitutes for the candidate's required `expected_event_state_token`; those tokens must be obtained from fresh `get-event-taxonomy` calls after MCP exposure is fixed.

## Pre-execution control record for the next production mutation

- `OBJECTIVE`: expose the already-installed Chattanooga CMS Admin abilities through the active MCP server and verify candidate read/health behavior before any live taxonomy write.
- `TARGET_SET`: Chattanooga CMS Admin MCP exposure for the current authorized administrative connection only.
- `EXCLUSION_SET`: live event relationships, unrelated MCP abilities, transport replacement, plugin/theme source, unrelated site configuration.
- `EVIDENCE`: plugin active; administrator connection; required WordPress role capabilities present; candidate source/reference registry valid; zero candidate abilities exposed live; miniOrange official governance contract requires explicit role/NHI ability grants.
- `MUTATION_SET`: no mutation is executable from the current connector because no supported miniOrange governance control is exposed.
- `RISK_SET`: enabling the wrong namespace/role/NHI could broaden access beyond the intended administration layer; bypassing with generic mutation would discard exact-state/rollback protections; changing `show_in_rest` would weaken the verified REST-isolation boundary without evidence that it solves this governed MCP exposure state.
- `ROLLBACK_POINT`: no MCP governance mutation has been performed in this task position.
- `ACCEPTANCE_TESTS`: candidate namespace discoverable; expected candidate abilities visible; `get-health` and bounded read abilities execute successfully; event taxonomy reads return exact state tokens; unrelated existing abilities remain available.

## Current production boundary

- Chattanooga CMS Admin is active on production.
- Candidate MCP discovery is blocked by missing live exposure of the candidate namespace, not by the current Administrator role lacking `edit_events` and not by ChatGPT plugin permission mode.
- miniOrange's role/NHI governance model is now authoritative external evidence for the exposure gate.
- No live content or taxonomy relationship was modified during Gates 19–20.
- No MCP transport/policy setting was changed.
- `main`, `feature/chattanooga-cms-admin`, DreamHost production source files, unrelated content/member/event/navigation records, and third-party plugin source remain untouched by the workbench.
- The five Festival relationship writes remain a separate live mutation requiring both candidate MCP exposure and explicit target-specific live-write authorization.

## Recalculated next position

Do not add speculative source capability, do not enable public REST exposure, and do not use the generic taxonomy-removal ability as a substitute. Grant the `chattanooga-cms-admin` resource/abilities to the Administrator role in miniOrange's role/ability editor for the enabled NHI used by the current connection. Then rerun discovery and candidate health/read validation. After that, if the five Festival relationship repair is explicitly authorized, obtain fresh candidate `get-event-taxonomy` tokens and perform only the exact guarded relationship replacements with post-write verification and rollback semantics.