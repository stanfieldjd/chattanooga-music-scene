# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_SINGLE_SITE_MAINTENANCE_CONTENT_PERMANENT_DELETE_TAXONOMY_NAVIGATION_MEMBER_EVENT_LOCATION_ADMIN_VERIFIED — NAVIGATION LIFECYCLE NEXT / LIVE PREFLIGHT PARTIAL / PRODUCTION NOT DEPLOYED

## Current verified candidate

- Candidate checkpoint: `b105ea0edf3fdb071794357770e1f0f9ed1c12ac`.
- Product scope: single-site WordPress only. Multisite/network support is not an acceptance target.
- Full single-site maintenance regression: `34187848901` passed on PHP 7.4, PHP 8.2, and disposable WordPress 7.1.
- Runtime artifact: `10041154773`, SHA-256 `c9b312bccbd5e5c12356e3048cf33085282c8c7f2f8fccb8e98b8c3b9d1011e8`.
- Content/member/navigation run: `34187757628` passed.
- Integrity run: `34187848894` passed.
- Events Manager regression run: `34187848906` passed against WordPress.org Events Manager 7.4.3.
- Real registry: 74 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 taxonomy + 6 navigation + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation.

## Verified content administration

- Bounded post/page list/get/create-draft/update/trash/restore/revision-restore operations remain conflict checked and object-capability gated.
- Publication/status transitions retain exact-state and publish-authority controls.
- Category/post-tag term and relationship administration retains conflict/readback/rollback gates.
- Permanent post/page deletion is a separate destructive contract rather than part of ordinary CRUD.
- Permanent deletion requires the item to already be in WordPress trash, requires exact `expected_modified_gmt`, requires explicit `confirm_permanent_delete=true`, and rechecks native object-level `delete_post` authority at execution time.
- Hard deletion uses native WordPress deletion and verifies the item is absent afterward; stale, unconfirmed, non-trash, wrong-type, or unauthorized requests fail closed.

## Verified core navigation administration

- Six typed abilities cover bounded core WordPress navigation: list menus, get one menu, create a menu, create/update a menu item, delete a menu item, and assign/unassign a registered menu location.
- Navigation authority is isolated behind `edit_theme_options`; anonymous and ordinary editor fixtures are denied while administrator authority passes.
- Menu mutations use exact menu-state tokens; location assignment uses an exact assignment-map state token.
- Item creation/update supports only published core WordPress pages and bounded custom links using root-relative or HTTP/HTTPS URLs.
- Parent validation prevents cross-menu parents and menu-item cycles.
- Readback verification and injected write corruption prove item rollback/cleanup; injected location-assignment corruption proves exact assignment-map rollback.
- Navigation-item deletion requires explicit destructive confirmation, deletes only the menu-item post, and verifies linked content remains intact.
- An unrelated control menu remains unchanged throughout the mutation suite.
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
- Events Manager and all other third-party plugin source remain immutable dependency surfaces for this workstream.

## Active next gate — navigation menu lifecycle close-out

The core navigation surface can create menus and fully manage their bounded items/locations, but it cannot yet rename or delete an obsolete menu. Close that symmetry gap with exact menu-state conflict handling. Menu deletion must be an explicit destructive contract, must not delete linked pages, and must not silently alter other menus. Prefer requiring the menu to be unassigned before hard deletion so assignment changes remain a separate explicit operation. After this small lifecycle close-out, recalculate the next core/site administration gap rather than expanding third-party plugins.

## Production boundary

`main`, `feature/chattanooga-cms-admin`, DreamHost production, existing Chattanooga content/member/event records, and the current MCP transport are not mutation targets for workbench development. Candidate installation and live MCP discovery remain separate production gates; reference CI success does not imply deployment.
