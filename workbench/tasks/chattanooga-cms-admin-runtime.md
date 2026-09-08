# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_SINGLE_SITE_MAINTENANCE_CONTENT_PERMANENT_DELETE_TAXONOMY_NAVIGATION_LIFECYCLE_MEMBER_EVENT_LOCATION_EVENT_LIFECYCLE_EVENT_TAXONOMY_ADMIN_VERIFIED — LIVE FESTIVAL RELATIONSHIP ISSUE VERIFIED / NO TERM LIFECYCLE JUSTIFIED / PRODUCTION GATE PENDING

## Current verified candidate

- Candidate source checkpoint: `62e46bb64514973a640f1abd13ff5f90248580f8`.
- Product scope: single-site WordPress only. Multisite/network support is not an acceptance target.
- Latest full single-site maintenance regression: `34244282299` passed on PHP 7.4, PHP 8.2, and disposable WordPress 7.1.
- Latest runtime artifact: `10063358784`, SHA-256 `f3f13c5a2d603fcecaca8de458df18538a9216f42c2656e6eaa7e296a44695b7`.
- Latest content/member/navigation run: `34244282476` passed.
- Latest integrity run: `34244282480` passed.
- Latest Events Manager regression run: `34244282382` passed against WordPress.org Events Manager 7.4.3.
- Real registry: 82 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 content taxonomy + 8 navigation + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation + 3 event lifecycle + 3 event taxonomy.

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
- Three typed abilities are verified:
  1. `list-event-taxonomy-terms` — bounded inspection of existing category/tag vocabulary.
  2. `get-event-taxonomy` — exact relationship read for one ordinary single event plus event conflict token.
  3. `set-event-taxonomy` — exact relationship replacement for one allowlisted taxonomy.
- `set-event-taxonomy` requires the exact current event state token and exact previous term-ID set. Stale event or relationship state fails closed.
- Requested target terms must already exist. No implicit term creation occurs.
- No-change relationship writes are rejected.
- Relationship clearing is explicit replacement with an empty set and does not delete the underlying term.
- Runtime fault injection proved failed post-write verification restores the exact prior relationship set.
- Event core state, referenced venue state, and an unrelated control event remain unchanged through taxonomy transactions.
- Third-party Events Manager source remains immutable dependency code.

## Gate 18 — fresh live taxonomy inventory — DECISION SUFFICIENT / PASS FOR TARGET WORKFLOW

Fresh read-only Chattanooga evidence on 2026-09-08 established:

- The live `event` post type has 110 published events, 1 draft, and 11 `event-categories` terms. `event-tags` is not exposed on the live `event` type.
- `Festival`: term ID `247`, slug `festival`, parent `0`, current category count `5`.
- `Music Festivals`: term ID `59`, slug `music-festivals`, parent `0`, current category count `3`.
- `Live Music`: term ID `60`, slug `live-music`, parent `0`, current category count `68`; the published custom-post-type filter returned 63 published event records.
- The five currently published Festival-category events are IDs `6810`, `7800`, `7803`, `7804`, and `7806`.
- Every one of those five Festival-category records is also assigned `Live Music`.
- Three of those five are also assigned `Music Festivals`.
- Individual live reads confirmed these are actual festival-form records, including `3 Sisters Bluegrass Festival`, `Chattanooga Oktoberfest`, `IBMA World of Bluegrass`, `Chattanooga Bluegrass Festival`, and `Chattanooga Jazz Fest`.
- The current connector reports that 11 event-category terms exist but does not expose a read-only list-all-category ability. A complete name catalogue of all 11 terms is therefore unavailable through this surface. This limitation does not prevent the current decision because the required `Festival`, `Music Festivals`, and `Live Music` terms were independently verified by ID and taxonomy-filtered event reads.

Decision: the live site already has sufficient vocabulary for the primary-form festival distinction. The defect is current relationship assignment: all five published Festival records also carry `Live Music`. No Events Manager term create/update/delete capability is justified by present evidence.

## Current production boundary

No live content or taxonomy relationship was modified during Gate 18. The candidate is still not installed on Chattanooga Music Scene, and candidate MCP discovery has not been run. Installing the candidate, changing MCP transport, or repairing the five live festival relationships are separate production mutations and require the applicable authorization/verification gate plus a fresh pre-mutation read.

`main`, `feature/chattanooga-cms-admin`, DreamHost production files, existing Chattanooga content/member/event/navigation records, and current MCP transport remain outside automatic workbench mutation.

## Recalculated next position

Do not add taxonomy term lifecycle or other speculative source capability. The next material step for this workstream is the production candidate-install/live-discovery gate; the next event-taxonomy operation is an authorized, freshly re-read relationship repair using exact expected-before state. Until that production authorization exists, the engineering source is at a verified bounded checkpoint rather than an incomplete taxonomy implementation.
