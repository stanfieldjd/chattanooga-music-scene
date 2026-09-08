# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment. Product target is single-site WordPress only; multisite/network behavior is out of scope and must not be reintroduced as an acceptance requirement. Third-party plugin source is immutable dependency code for this workstream; only Chattanooga CMS Admin and its disposable harness are editable.

## Gates 0–18 — PASS

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
- Fresh read-only Chattanooga event-taxonomy evidence sufficient to determine that the current festival classification defect is relationship assignment rather than missing vocabulary.

Current reference evidence: candidate source checkpoint `62e46bb64514973a640f1abd13ff5f90248580f8`; maintenance `34244282299`; content/member/navigation `34244282476`; integrity `34244282480`; Events Manager regression `34244282382`; artifact `10063358784`; SHA-256 `f3f13c5a2d603fcecaca8de458df18538a9216f42c2656e6eaa7e296a44695b7`; 82 registered abilities.

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

Evidence retained from the event-lifecycle reference gates and included in the latest Events Manager runtime regression.

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

Evidence retained from the event-lifecycle reference gates and included in the latest Events Manager runtime regression.

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

Evidence: source checkpoint `62e46bb64514973a640f1abd13ff5f90248580f8`; latest Events Manager runtime `34244282382`; maintenance `34244282299`; content/member/navigation `34244282476`; integrity `34244282480`; 82 registered abilities.

1. Runtime contract inspection verifies Events Manager 7.4.3 registers `event-categories` and `event-tags` against `event` in the disposable reference runtime.
2. `event-categories` is hierarchical and `event-tags` is non-hierarchical; both expose native `edit_events` assignment authority in that reference runtime.
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

## Gate 18 — Fresh live event taxonomy inventory — PASS FOR TARGET DECISION / READ-ONLY

1. The connected Chattanooga environment was queried read-only; no candidate installation, MCP transport change, content write, or taxonomy write occurred.
2. The live `event` type currently reports 110 published events, 1 draft, and 11 `event-categories` terms. `event-tags` is not exposed on this live event type.
3. Relevant live vocabulary was independently verified: `Festival` = term `247`, slug `festival`, parent `0`, count `5`; `Music Festivals` = term `59`, slug `music-festivals`, parent `0`, count `3`; `Live Music` = term `60`, slug `live-music`, parent `0`, Events Manager count `68`.
4. The published custom-post-type filter for `Festival` returned exactly event IDs `6810`, `7800`, `7803`, `7804`, and `7806`.
5. Fresh individual event reads established that all five Festival-category records are also assigned `Live Music`.
6. Three of the five Festival records are also assigned `Music Festivals`.
7. Direct reads identified the records as actual festival-form events: `3 Sisters Bluegrass Festival`, `Chattanooga Oktoberfest`, `IBMA World of Bluegrass`, `Chattanooga Bluegrass Festival`, and `Chattanooga Jazz Fest`.
8. The current vocabulary is therefore sufficient for the primary-form distinction. The proven defect is relationship classification; no event term create/update/delete capability is justified by current evidence.
9. The live connector reports 11 event-category terms but exposes no read-only list-all-category ability. A complete name catalogue of all 11 terms is therefore not claimed. This does not block the target decision because the three relevant terms were verified by direct category IDs and taxonomy-filtered event reads.
10. Any later live repair must start with a fresh exact relationship read and must be separately authorized; the evidence collected in this gate is not a future expected-before write token.

## Live Chattanooga

The read-only taxonomy decision gate is complete for the festival-classification workflow. DreamHost production preflight remains partial. Candidate installation/live MCP discovery and any live festival relationship repair are separate production gates. Reference-runtime and live read-only evidence do not imply deployment or mutation authorization.
