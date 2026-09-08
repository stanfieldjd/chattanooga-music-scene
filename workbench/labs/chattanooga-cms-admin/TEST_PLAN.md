# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment. Product target is single-site WordPress only; multisite/network behavior is out of scope and must not be reintroduced as an acceptance requirement. Third-party plugin source is immutable dependency code for this workstream; only Chattanooga CMS Admin and its disposable harness are editable.

## Gates 0–15 — PASS

- Source architecture, PHP 7.4/8.2, privacy/static boundaries.
- Real WordPress 7.1 Abilities API registration and REST isolation.
- Backup/restore, corrupt/missing rejection, unavailable storage, partial-write and ZIP-finalization fail-closed behavior.
- Plugin/theme/core lifecycle/update and forced rollback, package privacy, bounded error output, single-site cache.
- Post/page CRUD/revisions/trash/restore and object permissions.
- Publication/status transitions with conflicts and publish authority.
- Category/post-tag term and relationship operations with conflicts and rollback.
- Bounded member reads with credential/usermeta/session boundaries.
- Member display-name/URL and role-state mutation with conflict/readback/rollback and zero mail attempts.
- Ordinary single event and physical venue/location read/create/update integration without third-party source modification.
- Permanent post/page deletion with trash prerequisite, exact conflict state, explicit confirmation, object permission and absence verification.
- Core WordPress navigation administration including menu lifecycle, item lifecycle, and registered location assignment.
- Ordinary single-event trash and permanent deletion with exact state, explicit confirmation, object authority, booking protection, absence verification, and venue isolation.

Current reference evidence: candidate checkpoint `b181bfc4350590796aabd45b3196be6bffcbf624`; maintenance `34240050604`; content/member/navigation `34240050534`; integrity `34240050888`; Events Manager regression `34240050569`; artifact `10061625032`; SHA-256 `8de5e338f732d0c8c28e603de8d160d45e40de2a5556b37ea7a71a9b327a77d4`.

## Gate 13 — Core WordPress navigation administration — PASS

1. Typed abilities cover menu list/get/create, menu-item create/update/delete, and registered menu-location assignment/unassignment.
2. All navigation abilities require `edit_theme_options`; anonymous and ordinary editor fixtures are denied.
3. Menu reads expose bounded normalized core-menu/item state plus exact SHA-256 state tokens.
4. Item mutations require the exact current menu state; stale writes fail closed.
5. Supported item targets are published core WordPress pages and bounded custom root-relative/HTTP/HTTPS links only.
6. Cross-menu parent references and parent cycles are rejected.
7. Item writes perform readback verification; injected one-shot post corruption proves exact rollback for updates and cleanup for failed creates.
8. Location assignment requires the exact current registered-location assignment state; injected theme-mod corruption proves rollback to the exact prior map.
9. Navigation-item deletion requires explicit confirmation, verifies the item is absent, and leaves the linked page intact.
10. An unrelated control menu remains unchanged throughout the transaction suite.
11. Static boundary checks require core WordPress navigation APIs and reject generic option mutation or third-party/plugin-specific code from the navigation service.

## Gate 14 — Navigation menu lifecycle close-out — PASS

Evidence: content runtime `34240050534`; 78 total registered abilities; maintenance/static `34240050604`; integrity `34240050888`.

1. `update-navigation-menu` renames an existing core WordPress menu using the exact current menu-state token.
2. Stale rename and no-change rename fail closed.
3. Rename uses native `wp_update_nav_menu_object`, performs readback verification, and injected post-write corruption proves exact rollback to the previous managed menu state.
4. `delete-navigation-menu` is explicitly destructive and requires exact current menu state plus `confirm_delete=true`.
5. Whole-menu deletion is refused while the menu is assigned to any registered theme location.
6. Unassignment remains a separate explicit operation through `set-navigation-menu-location`; deletion never silently changes assignment state.
7. Stale and unconfirmed whole-menu deletion fail closed without removing the menu.
8. Successful whole-menu deletion uses native `wp_delete_nav_menu`, verifies the menu and its menu-item posts are absent, preserves the linked WordPress page, and verifies location assignments remain unchanged.
9. The unrelated control menu remains unchanged.
10. Static boundary checks require the native menu update/delete APIs, exact-state and assignment guards, lifecycle registration, and prohibit generic option mutation or third-party-specific code in the navigation service.

## Gate 15 — Events Manager event deletion — PASS

Evidence: Events Manager runtime `34240050569`; 78 total registered abilities; maintenance/static `34240050604`; content/member/navigation `34240050534`; integrity `34240050888`.

1. Two typed abilities cover ordinary single-event trash and permanent deletion only; location, booking, ticket, and payment deletion are excluded.
2. Both abilities require `delete_events` at registration and native event object authority at execution; anonymous access and `edit_events` alone are insufficient.
3. Trash requires the exact current event state token, rejects stale or repeated trash requests, calls native `EM_Event::delete(false)`, and verifies the backing event post enters WordPress trash.
4. Permanent deletion requires the event already be trashed, the exact trashed event state token, and explicit `confirm_permanent_delete=true`.
5. Before permanent deletion, Events Manager aggregate booking count is evaluated for the specific event across booking statuses and owners. Any existing booking refuses deletion and leaves both event and booking intact.
6. Zero verified bookings permit native `EM_Event::delete(true)`; both the Events Manager event identity and backing WordPress event post must be absent afterward.
7. Object-level `delete_others_events` authority is enforced for events owned by another account.
8. The referenced venue state and an unrelated control event remain unchanged through trash, refusal, and successful hard deletion.
9. The dependency-model probe independently confirms Events Manager 7.4.3 native `delete(false)` means trash and `delete(true)` means permanent deletion while preserving the referenced location.
10. Static boundary checks require the two-stage native delete calls, exact state, explicit confirmation, aggregate booking guard, and venue isolation while rejecting location/booking/ticket deletion methods from this service.

## Gate 16 — Site administration autonomy gap recalculation — ACTIVE

1. Inventory the verified 78 abilities against actual Chattanooga single-site administration workflows.
2. Identify a concrete administration action that remains impossible through the typed surface and is materially necessary or frequently required.
3. Rank candidate gaps by operational necessity, frequency, reversibility, and security/destructive risk.
4. Do not select a capability merely because WordPress or an installed plugin exposes it.
5. Media, recurring events, bookings, tickets, payments, member security/account lifecycle, widgets/templates, location deletion, and generic option mutation remain non-automatic targets.
6. Any selected mutation must use native authority, bounded schemas/allowlists, exact-state conflict handling where applicable, readback verification, rollback for recoverable writes, and explicit destructive isolation where rollback is impossible.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or content/member/event/navigation mutation is authorized by these laboratory gates. Candidate installation and live MCP discovery remain separate production gates.
