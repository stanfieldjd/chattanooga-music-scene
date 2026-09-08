# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_SINGLE_SITE_MAINTENANCE_CONTENT_PERMANENT_DELETE_TAXONOMY_NAVIGATION_LIFECYCLE_MEMBER_EVENT_LOCATION_EVENT_DELETION_ADMIN_VERIFIED — AUTONOMY GAP RECALCULATION NEXT / LIVE PREFLIGHT PARTIAL / PRODUCTION NOT DEPLOYED

## Current verified candidate

- Candidate checkpoint: `b181bfc4350590796aabd45b3196be6bffcbf624`.
- Product scope: single-site WordPress only. Multisite/network support is not an acceptance target.
- Full single-site maintenance regression: `34240050604` passed on PHP 7.4, PHP 8.2, and disposable WordPress 7.1.
- Runtime artifact: `10061625032`, SHA-256 `8de5e338f732d0c8c28e603de8d160d45e40de2a5556b37ea7a71a9b327a77d4`.
- Content/member/navigation run: `34240050534` passed.
- Integrity run: `34240050888` passed.
- Events Manager regression run: `34240050569` passed against WordPress.org Events Manager 7.4.3.
- Real registry: 78 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 taxonomy + 8 navigation + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation + 2 event deletion.

## Verified content administration

- Bounded post/page list/get/create-draft/update/trash/restore/revision-restore operations remain conflict checked and object-capability gated.
- Publication/status transitions retain exact-state and publish-authority controls.
- Category/post-tag term and relationship administration retains conflict/readback/rollback gates.
- Permanent post/page deletion is a separate destructive contract rather than part of ordinary CRUD.
- Permanent deletion requires the item to already be in WordPress trash, exact `expected_modified_gmt`, explicit `confirm_permanent_delete=true`, and native object-level `delete_post` authority.
- Hard deletion uses native WordPress deletion and verifies the item is absent afterward; stale, unconfirmed, non-trash, wrong-type, or unauthorized requests fail closed.

## Verified core navigation administration

- Eight typed abilities cover bounded core WordPress navigation: list menus, get one menu, create a menu, rename a menu, permanently delete an obsolete menu, create/update a menu item, delete a menu item, and assign/unassign a registered menu location.
- Navigation authority is isolated behind `edit_theme_options`; anonymous and ordinary editor fixtures are denied while administrator authority passes.
- Menu and item mutations use exact menu-state tokens; location assignment uses an exact assignment-map state token.
- Menu rename rejects stale and no-change writes, performs readback verification, and rolls back to the exact previous managed state when injected post-write corruption is detected.
- Whole-menu deletion is a separate destructive contract requiring exact current menu state and explicit confirmation. Assigned menus are refused; callers must explicitly unassign them first through the location ability.
- Whole-menu deletion verifies the menu is absent, preserves registered-location assignments, preserves linked WordPress pages, and leaves an unrelated control menu unchanged.
- Item creation/update supports only published core WordPress pages and bounded custom links using root-relative or HTTP/HTTPS URLs.
- Parent validation prevents cross-menu parents and menu-item cycles.
- Item writes and location assignments retain readback verification and injected-fault rollback coverage.
- Navigation-item deletion requires explicit destructive confirmation, deletes only the menu-item post, and verifies linked content remains intact.
- The navigation layer uses core WordPress menu/theme-mod APIs only. It does not mutate theme source, generic options, or any third-party plugin source.

## Verified member administration

- Bounded member list/search/detail and role reads.
- Profile mutation is limited to display name and URL with exact expected profile state, readback verification, and rollback on injected verification failure.
- Account email is readable and participates in the profile conflict token but is not mutable in this ability.
- Exact role-state replacement validates editable roles, prevents changing the current account's own role state, verifies readback, and rolls back on injected corruption.
- Runtime mail guard verified zero notification attempts from member mutations.
- Passwords, reset operations, activation keys, session tokens, arbitrary usermeta, account creation, and permanent user deletion remain separate security/destructive contracts.

## Verified event and venue administration

- Four bounded read abilities cover list/get events and list/get locations.
- Four mutation abilities cover create/update for ordinary single events and physical venues/locations only.
- Expected-before state, readback, rollback/cleanup, publish/object authority, dependency-state preservation, and referenced-venue isolation are verified.
- Two event-deletion abilities provide an explicit two-stage lifecycle for ordinary single events: native Events Manager trash first, then optional permanent deletion.
- Event trash uses the exact current event state, verifies the backing WordPress event post is in trash, preserves the referenced venue, and leaves unrelated events unchanged.
- Permanent event deletion requires the event already be trashed, exact `expected_state_token`, explicit `confirm_permanent_delete=true`, and native object-level delete authority.
- Before hard deletion, the service uses Events Manager's aggregate booking count across statuses/owners for the specific event. Any existing booking refuses permanent deletion and preserves both the event and booking; zero bookings permits the native forced delete path.
- Successful permanent deletion verifies both the Events Manager identity and backing WordPress event post are absent while the referenced venue and unrelated event remain unchanged.
- Location deletion, booking deletion, ticket deletion, and payment administration are not part of the event-deletion contract.
- Events Manager and all other third-party plugin source remain immutable dependency surfaces for this workstream.

## Active next gate — site administration autonomy gap recalculation

Recalculate what Chattanooga CMS Admin still cannot do that is materially necessary to administer the actual single-site Chattanooga environment. Select the next gate from concrete site workflows rather than from WordPress or installed-plugin feature inventories. Do not automatically expand into media, recurring events, bookings, tickets, payments, account security operations, widgets, templates, location deletion, or generic option mutation. Any selected mutation must remain typed and bounded, use native authority, add exact-state conflict handling when the state model supports it, verify readback, and provide rollback or explicit destructive isolation appropriate to the operation.

## Production boundary

`main`, `feature/chattanooga-cms-admin`, DreamHost production, existing Chattanooga content/member/event/navigation records, and the current MCP transport are not mutation targets for workbench development. Candidate installation and live MCP discovery remain separate production gates; reference CI success does not imply deployment.
