# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment. Product target is single-site WordPress only; multisite/network behavior is out of scope and must not be reintroduced as an acceptance requirement. Third-party plugin source is immutable dependency code for this workstream; only Chattanooga CMS Admin and its disposable harness are editable.

## Gates 0–12 — PASS

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

Current reference evidence: candidate checkpoint `b105ea0edf3fdb071794357770e1f0f9ed1c12ac`; maintenance `34187848901`; content/member/navigation `34187757628`; integrity `34187848894`; Events Manager regression `34187848906`; artifact `10041154773`; SHA-256 `c9b312bccbd5e5c12356e3048cf33085282c8c7f2f8fccb8e98b8c3b9d1011e8`.

## Gate 13 — Core WordPress navigation administration — PASS

Evidence: content runtime `34187757628`; 74 total registered abilities.

1. Six typed abilities cover menu list/get/create, menu-item create/update/delete, and registered menu-location assignment/unassignment.
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

## Gate 14 — Navigation menu lifecycle close-out — ACTIVE

1. Add exact-state menu rename/update using native core WordPress menu APIs and readback verification.
2. Add explicit destructive whole-menu deletion only for an existing unassigned menu.
3. Whole-menu deletion must require the exact current menu state and explicit confirmation.
4. Prove linked pages survive menu deletion and unrelated menus/location assignments remain unchanged.
5. Do not combine unassignment with deletion; callers must explicitly unassign first through the already verified location ability.
6. Do not extend into theme source, generic options, widgets, block templates, or third-party menu plugins.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or content/member/event/navigation mutation is authorized by these laboratory gates. Candidate installation and live MCP discovery remain separate production gates.
