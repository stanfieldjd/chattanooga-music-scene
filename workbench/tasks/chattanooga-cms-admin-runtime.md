# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_SINGLE_SITE_MAINTENANCE_CONTENT_PERMANENT_DELETE_TAXONOMY_NAVIGATION_LIFECYCLE_MEMBER_EVENT_LOCATION_EVENT_LIFECYCLE_EVENT_TAXONOMY_ADMIN_VERIFIED — FRESH LIVE TAXONOMY INVENTORY NEXT / LIVE PREFLIGHT PARTIAL / PRODUCTION NOT DEPLOYED

## Current verified candidate

- Candidate source checkpoint: `62e46bb64514973a640f1abd13ff5f90248580f8`.
- Product scope: single-site WordPress only. Multisite/network support is not an acceptance target.
- Full single-site maintenance regression: `34243687257` passed on PHP 7.4, PHP 8.2, and disposable WordPress 7.1.
- Runtime artifact: `10063123311`, SHA-256 `0cf3d8288731e5a5be8a5a5c7ef6528332f12f01387a38a1f65668a9f07ea24b`.
- Content/member/navigation run: `34243687429` passed.
- Integrity run: `34243687283` passed.
- Events Manager regression run: `34243687468` passed against WordPress.org Events Manager 7.4.3.
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

- Events Manager 7.4.3 registers `event-categories` and `event-tags` on the `event` post type. `event-categories` is hierarchical; `event-tags` is non-hierarchical.
- Native taxonomy assignment authority for both is `edit_events`; term lifecycle uses separate native manage/edit/delete capabilities and was not added to the candidate.
- Three typed abilities are now verified:
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

## Active next gate — fresh live taxonomy inventory

Obtain fresh read-only Chattanooga evidence for the existing Events Manager event category/tag vocabulary and the current classification relationships on affected live events. The purpose is to determine whether the verified relationship abilities are sufficient for the real festival-vs-Live-Music integrity workflow or whether a separate, concretely justified term-lifecycle capability is actually required.

This gate is evidence-only unless separately authorized. Do not install the candidate, mutate live records, create/rename/delete terms, or expand into media, recurrence, bookings, tickets, payments, account security, widgets/templates, location deletion, generic options, or multisite behavior merely because an upstream API supports them.

If the connected live surface cannot expose the required taxonomy evidence, record that limitation explicitly rather than guessing or adding source capability without evidence.

## Production boundary

`main`, `feature/chattanooga-cms-admin`, DreamHost production, existing Chattanooga content/member/event/navigation records, and the current MCP transport are not mutation targets for workbench development. Candidate installation and live MCP discovery remain separate production gates; reference CI success does not imply deployment.
