# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_SINGLE_SITE_MAINTENANCE_CONTENT_TAXONOMY_MEMBER_EVENTS_READ_VERIFIED — EVENT/LOCATION MUTATION NEXT / LIVE PREFLIGHT PARTIAL / PRODUCTION NOT DEPLOYED

## Current verified candidate

- Candidate source checkpoint: `dc1ac1322c14f073e82e2ae20ad315cfd79ee6c7`.
- Product scope: single-site WordPress only. Multisite/network support was explicitly removed and is not an acceptance target.
- Full single-site maintenance regression: `34174322773` — PHP 7.4, PHP 8.2, disposable WordPress 7.1 passed.
- Runtime artifact: `10036756929`, SHA-256 `ff5c958c2bcdeefaaa1a87ff7d53eb45f6448c6f03679ed6f9cf0f42271d7bba`.
- Content/member run: `34174322772` passed.
- Integrity run: `34174322771` passed.
- Events Manager read run: `34174699401` passed against WordPress.org Events Manager 7.4.3.
- Real registry: 62 abilities = 24 maintenance + 14 content CRUD/revision + 2 status + 12 taxonomy + 4 member-read + 2 member-mutation + 4 Events Manager read.

## Verified member administration

- Bounded member list/search/detail and role reads.
- Profile mutation is limited to display name and URL with exact expected profile state, readback verification, and rollback on injected verification failure.
- Account email is readable and participates in the profile conflict token but is not mutable in this ability; attempted email-only mutation is rejected.
- Exact role-state replacement validates editable roles, prevents changing the current account's own role state, verifies readback, and rolls back on injected corruption.
- Mutation permission split: `edit_users` for profile; `promote_users` for roles, with execution-level object authority.
- Runtime mail guard verified zero notification attempts from these mutations.
- Passwords, reset operations, activation keys, session tokens, arbitrary usermeta, account creation, and permanent user deletion remain separate security/destructive contracts.

## Verified Events Manager reads

- Dedicated disposable WordPress 7.1 + MySQL 8.0 lab installs and initializes real Events Manager 7.4.3.
- Required event/location classes, helper APIs, post types and event taxonomies are present.
- Four bounded candidate abilities register: list/get events and list/get locations.
- Anonymous access is denied and administrator access uses Events Manager's native event/location capabilities.
- Event/location list/search/get responses use explicit allowlists; missing identifiers fail closed.
- The initial collection-query failure was a test-fixture defect: a manually built event omitted Events Manager 7.4.3 canonical fields. The corrected disposable fixture persists `event_archetype=event`, `event_type=single`, and `event_active_status=1`, after which native collection and candidate read gates pass.

## Active next gate — bounded event/location mutation

Implement only the Chattanooga operations that are presently justified: create and conflict-checked update for ordinary single events and venues/locations in the disposable Events Manager runtime. Require exact before-state tokens, native permission checks, readback verification, rollback on injected verification failure, and unrelated-record isolation. Do not add recurring-event, ticket, booking, payment, or other Events Manager surfaces merely because the upstream plugin exposes them.

## Production boundary

`main`, `feature/chattanooga-cms-admin`, DreamHost production, existing Chattanooga content/member/event records, and the current MCP transport are not mutation targets for workbench development. Candidate installation and live MCP discovery remain separately authorized production gates.
