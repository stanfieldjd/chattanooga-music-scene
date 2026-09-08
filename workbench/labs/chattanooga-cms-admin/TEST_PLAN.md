# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment. Product target is single-site WordPress only; multisite/network behavior is out of scope and must not be reintroduced as an acceptance requirement. Third-party plugin source is immutable dependency code for this workstream; only Chattanooga CMS Admin and its disposable harness are editable.

## Gates 0–17 — PASS

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
- Ordinary single-event restoration from trash to draft with exact state, object authority, booking preservation, readback, and rollback to trash on failed verification.
- Events Manager event category/tag vocabulary inspection and exact relationship replacement with dual conflict guards, relationship-only clearing, verification rollback, and event/location/control isolation.

Current reference evidence: candidate source checkpoint `62e46bb64514973a640f1abd13ff5f90248580f8`; maintenance `34243687257`; content/member/navigation `34243687429`; integrity `34243687283`; Events Manager regression `34243687468`; artifact `10063123311`; SHA-256 `0cf3d8288731e5a5be8a5a5c7ef6528332f12f01387a38a1f65668a9f07ea24b`; 82 registered abilities.

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

Evidence retained from the navigation reference gates and included in the current 82-ability regression.

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

Evidence retained from the event-lifecycle reference gates and included in Events Manager runtime `34243687468`.

1. Event trash and permanent deletion remain ordinary-single-event-only lifecycle operations; location, booking, ticket, and payment deletion are excluded.
2. Lifecycle abilities require `delete_events` at registration and native event object authority at execution; anonymous access and `edit_events` alone are insufficient.
3. Trash requires exact current event state, rejects stale/repeated trash requests, calls native `EM_Event::delete(false)`, and verifies the backing event post enters WordPress trash.
4. Permanent deletion requires the event already be trashed, exact trashed state, and explicit `confirm_permanent_delete=true`.
5. Events Manager aggregate booking count is evaluated across statuses/owners for the specific event. Any booking refuses deletion and leaves event plus booking intact.
6. Zero verified bookings permit native `EM_Event::delete(true)`; both Events Manager identity and backing WordPress event post must be absent afterward.
7. Object-level `delete_others_events` authority is enforced for events owned by another account.
8. Referenced venue state and unrelated control event remain unchanged through trash, refusal, and successful hard deletion.
9. Dependency-model probe independently confirms Events Manager 7.4.3 `delete(false)` means trash and `delete(true)` means permanent removal while preserving location.
10. Static boundary checks retain two-stage native delete, exact state, explicit confirmation, aggregate booking guard, venue isolation, and event-only deletion scope.

## Gate 16 — Events Manager event restore lifecycle — PASS

Evidence retained from the event-lifecycle reference gates and included in Events Manager runtime `34243687468`.

1. `restore-event` restores one ordinary single event only from WordPress trash.
2. Exact `expected_state_token` is required; stale state and non-trash restore attempts fail closed.
3. Registration uses the same `delete_events` lifecycle authority and execution enforces native object authority, including `delete_others_events` for another owner's event.
4. Native WordPress `wp_untrash_post` behavior was measured in Events Manager 7.4.3: the event row/object survive and the backing event post is restored to `draft`.
5. The product deliberately requires draft readback; restore never silently republishes an event. Publication remains a separate explicit mutation.
6. Referenced venue state and an unrelated control event must remain unchanged.
7. An injected post-restore status corruption proves verification failure triggers rollback to native Events Manager trash, with trash-state readback.
8. A real booking fixture proves trash → restore-to-draft → re-trash preserves the booking, and permanent deletion remains refused while that booking exists.
9. A successfully restored event can re-enter the existing trash/permanent-delete lifecycle.
10. Static coverage requires native untrash, draft-only verification, rollback-to-trash, exact state, object authority, booking preservation, venue isolation, and event-only lifecycle scope.

## Gate 17 — Events Manager event taxonomy relationship administration — PASS

Evidence: source checkpoint `62e46bb64514973a640f1abd13ff5f90248580f8`; Events Manager runtime `34243687468`; maintenance `34243687257`; content/member/navigation `34243687429`; integrity `34243687283`; 82 registered abilities.

1. Runtime contract inspection verifies Events Manager 7.4.3 registers `event-categories` and `event-tags` against `event`.
2. `event-categories` is hierarchical and `event-tags` is non-hierarchical; both expose native `edit_events` assignment authority.
3. Candidate scope is allowlisted to those two event taxonomies only. No generic taxonomy surface is introduced.
4. Three abilities register: bounded existing-term list, exact event relationship read, and exact relationship replacement.
5. Only ordinary single events with a verified backing WordPress `event` post are accepted.
6. Relationship mutation requires exact current event-state token plus exact previous term-ID set; stale relationship state is rejected without write.
7. Every requested target term must already exist in the selected taxonomy; implicit term creation is prohibited.
8. Exact replacement uses native `wp_set_object_terms(..., append=false)`. Empty replacement clears only relationships and leaves terms intact.
9. No-change writes are rejected.
10. Post-write readback verifies the exact target relationship set and also verifies the event core state and referenced location state remained unchanged.
11. Injected post-write relationship corruption forces verification failure and exact rollback to the previous relationship set.
12. An unrelated control event's taxonomy remains unchanged.
13. Anonymous ability access is denied and administrator execution is allowed.
14. The full existing Events Manager read/mutation/deletion regression remains green in the same runtime.
15. Events Manager source is not modified; only Chattanooga CMS Admin and disposable fixtures changed.

## Gate 18 — Fresh live event taxonomy inventory — ACTIVE

1. Query the connected Chattanooga environment read-only for the existing Events Manager event category/tag vocabulary and affected event relationships if the current live connector can expose them.
2. Re-read affected live records rather than treating historical classification state as current.
3. Determine whether existing vocabulary is sufficient to preserve the primary-form distinction, including festivals remaining festival-class records rather than ordinary Live Music merely because they contain performances.
4. If existing terms are sufficient, do not add term-lifecycle source capability merely because Events Manager exposes term-management APIs.
5. If a missing/incorrect vocabulary operation is proven necessary, record the exact concrete need before designing a separate term create/update/delete gate.
6. This gate is read-only. Candidate installation, MCP transport changes, and live content/taxonomy mutation remain separate authorization gates.
7. If the connected live surface cannot expose the required evidence, record the limitation as UNKNOWN/BLOCKED rather than guessing.
8. Media, recurring events, bookings, tickets, payments, member security/account lifecycle, widgets/templates, location deletion, generic options, and multisite/network behavior remain non-automatic targets.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or content/member/event/navigation/taxonomy mutation is authorized by these laboratory gates. Candidate installation and live MCP discovery remain separate production gates.
