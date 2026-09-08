# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_SINGLE_SITE_MAINTENANCE_CONTENT_PERMANENT_DELETE_TAXONOMY_MEMBER_EVENT_LOCATION_ADMIN_VERIFIED — AUTONOMY GAP RECALCULATION NEXT / LIVE PREFLIGHT PARTIAL / PRODUCTION NOT DEPLOYED

## Current verified candidate

- Candidate checkpoint: `8c2c422d6b8139fcfe571564a0d543b5d1607be2`.
- Product scope: single-site WordPress only. Multisite/network support is not an acceptance target.
- Full single-site maintenance regression: `34187219749` passed on PHP 7.4, PHP 8.2, and disposable WordPress 7.1.
- Runtime artifact: `10040947233`, SHA-256 `27c11afa7652f062da3a33b2ca23b04b37ca1eabd79d4ad6cdc7898287dc6947`.
- Content/member/deletion run: `34187219774` passed.
- Integrity run: `34187219809` passed.
- Events Manager regression run: `34187219757` passed against WordPress.org Events Manager 7.4.3.
- Real registry: 68 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 taxonomy + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation.

## Verified content administration

- Bounded post/page list/get/create-draft/update/trash/restore/revision-restore operations remain conflict checked and object-capability gated.
- Publication/status transitions retain exact-state and publish-authority controls.
- Category/post-tag term and relationship administration retains conflict/readback/rollback gates.
- Permanent post/page deletion is now a separate destructive contract rather than part of ordinary CRUD.
- Permanent deletion requires the item to already be in WordPress trash, requires exact `expected_modified_gmt`, requires explicit `confirm_permanent_delete=true`, and rechecks native object-level `delete_post` authority at execution time.
- Hard deletion uses native WordPress deletion and verifies the item is absent afterward; stale, unconfirmed, non-trash, wrong-type, or unauthorized requests fail closed.
- The permanent-deletion runtime proves unrelated content remains unchanged.

## Verified member administration

- Bounded member list/search/detail and role reads.
- Profile mutation is limited to display name and URL with exact expected profile state, readback verification, and rollback on injected verification failure.
- Account email is readable and participates in the profile conflict token but is not mutable in this ability; attempted email-only mutation is rejected.
- Exact role-state replacement validates editable roles, prevents changing the current account's own role state, verifies readback, and rolls back on injected corruption.
- Mutation permission split: `edit_users` for profile; `promote_users` for roles, with execution-level object authority.
- Runtime mail guard verified zero notification attempts from these mutations.
- Passwords, reset operations, activation keys, session tokens, arbitrary usermeta, account creation, and permanent user deletion remain separate security/destructive contracts.

## Verified event and venue administration

- Four bounded read abilities cover list/get events and list/get locations with explicit allowlists, bounded pagination/search, native permissions, and fail-closed missing identifiers.
- Four mutation abilities cover create/update for ordinary single events and physical venues/locations only.
- Updates require exact expected-before state tokens and reject stale writes.
- Create/update transactions perform readback verification and rollback or cleanup on injected verification failure.
- Publish authority and object-level edit authority are enforced through native capabilities.
- Event metadata updates preserve dependency-owned active/booking/private state outside the CMS Admin contract.
- Referencing an existing venue cannot silently change that venue's publication or managed state; the admin transaction snapshots, restores, verifies, and fails closed if venue isolation cannot be preserved.
- Unrelated event and venue records remain unchanged.
- Events Manager and all other third-party plugin source are dependency surfaces only. Chattanooga CMS Admin and its disposable test harness are the only editable code in this workstream.
- Recurring events, event deletion, tickets, bookings, payments, media, and other upstream surfaces are not automatic expansion targets.

## Active next gate — site administration autonomy gap review

Recalculate what Chattanooga CMS Admin still needs in order to administer the actual single-site Chattanooga environment. Select gaps from concrete site workflows, not from the feature inventory of installed third-party plugins. Prefer core/site administration gaps before adding new third-party surfaces when both could satisfy the operational need. Keep security-sensitive, notification-producing, media, recurring-event, booking, ticket, payment, and other destructive domains separated until a concrete site workflow justifies them.

## Production boundary

`main`, `feature/chattanooga-cms-admin`, DreamHost production, existing Chattanooga content/member/event records, and the current MCP transport are not mutation targets for workbench development. Candidate installation and live MCP discovery remain separate production gates; reference CI success does not imply deployment.
