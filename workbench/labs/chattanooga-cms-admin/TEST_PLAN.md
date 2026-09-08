# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment. Product target is single-site WordPress only; multisite/network behavior is out of scope and must not be reintroduced as an acceptance requirement. Third-party plugin source is immutable dependency code for this workstream; only Chattanooga CMS Admin and its disposable harness are editable.

## Gates 0–14 — PASS

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

Current reference evidence: candidate checkpoint `1a808764b150965809dda2dd07a5b5fad058ff72`; maintenance `34188720251`; content/member/navigation `34188720289`; integrity `34188720290`; Events Manager regression `34188720252`; artifact `10041444805`; SHA-256 `d852b4f3771583a72961f744c8cfc5fdc1de81eb38d8bb7ad2af316464a87ce2`.

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

Evidence: content runtime `34188720289`; 76 total registered abilities; maintenance/static `34188720251`; integrity `34188720290`.

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

## Gate 15 — Site administration autonomy gap recalculation — ACTIVE

1. Inventory the verified 76 abilities against actual Chattanooga single-site administration workflows.
2. Identify a concrete administration action that remains impossible through the typed surface and is materially necessary or frequently required.
3. Rank candidate gaps by operational necessity, frequency, reversibility, and security/destructive risk.
4. Do not select a capability merely because WordPress or an installed plugin exposes it.
5. Media, recurring events, bookings, tickets, payments, member security/account lifecycle, widgets/templates, and generic option mutation remain non-automatic targets.
6. Any selected mutation must use native authority, bounded schemas/allowlists, exact-state conflict handling where applicable, readback verification, rollback for recoverable writes, and explicit destructive isolation where rollback is impossible.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or content/member/event/navigation mutation is authorized by these laboratory gates. Candidate installation and live MCP discovery remain separate production gates.
