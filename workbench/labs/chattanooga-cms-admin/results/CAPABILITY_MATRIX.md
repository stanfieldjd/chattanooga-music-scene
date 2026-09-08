# Chattanooga CMS Admin Capability Matrix

Latest reference candidate checkpoint: `6684aa3d508776b4e006455af0a493f102dddc02`.
Single-site maintenance: `34241513120`; content/member/navigation: `34241513095`; integrity: `34241513166`; Events Manager regression: `34241513102`; artifact `10062232870`; SHA-256 `9a05a3a5974119efbcaca4803776d464e00d768f83537a4a1399068d7a81e11c`.

| Capability | State | Evidence / boundary |
| --- | --- | --- |
| Single-site product scope | REFERENCE_VERIFIED | Multisite/network behavior is out of scope and not an acceptance target. |
| Third-party plugin source boundary | REFERENCE_VERIFIED | Installed plugins are immutable dependency surfaces; only Chattanooga CMS Admin and disposable harness code are changed. |
| PHP 7.4 / 8.2 + WordPress 7.1 activation | REFERENCE_VERIFIED | Current single-site candidate passed. |
| Native Abilities registry | REFERENCE_VERIFIED | 79 candidate abilities. |
| Maintenance backup/update/rollback/cache/privacy/error model | REFERENCE_VERIFIED | Full single-site maintenance regression green. |
| Post/page CRUD/revisions/status | REFERENCE_VERIFIED | Dedicated content runtime green. |
| Post/page permanent deletion | REFERENCE_VERIFIED | Trash-only prerequisite, exact modified-state conflict, explicit confirmation, native object permission, absence verification and unrelated-content isolation. |
| Category/post-tag terms/relationships | REFERENCE_VERIFIED | Conflict and rollback fault gates green. |
| Core navigation menu list/get/create | REFERENCE_VERIFIED | Bounded normalized menus/items plus registered locations; `edit_theme_options` authority. |
| Core navigation whole-menu rename | REFERENCE_VERIFIED | Exact menu-state conflict, no-change guard, native rename API, readback and injected-fault rollback. |
| Core navigation whole-menu deletion | REFERENCE_VERIFIED | Explicit destructive confirmation; exact menu state; assigned menus refused until separately unassigned; menu/item absence verified; linked page and location assignments preserved. |
| Core navigation item create/update | REFERENCE_VERIFIED | Published page/custom root-relative or HTTP(S) links only; exact menu-state conflict, parent/cycle validation, readback and rollback/cleanup. |
| Core navigation item deletion | REFERENCE_VERIFIED | Explicit destructive confirmation; exact menu state; item absence verified; linked page preserved. |
| Core navigation location assignment | REFERENCE_VERIFIED | Exact assignment-map state; registered locations only; assign/unassign; injected-fault rollback green. |
| Navigation source boundary | REFERENCE_VERIFIED | Core WordPress menu/theme-mod APIs only; no generic option mutation, theme source mutation, or third-party plugin code. |
| Member list/search/detail/role reads | REFERENCE_VERIFIED | Bounded allowlists; credentials/private internals absent. |
| Member profile display-name/URL update | REFERENCE_VERIFIED | Expected-state, readback and injected-fault rollback green. |
| Member account email mutation | NOT_IMPLEMENTED | Explicitly excluded after WordPress notification side effect was observed; separate notification/security gate required. |
| Member role-state replacement | REFERENCE_VERIFIED | Expected-state, editable-role validation, self guard and rollback green. |
| Member mutation notifications | REFERENCE_VERIFIED | Zero mail attempts in corrected runtime gate. |
| Password/reset/session operations | NOT_IMPLEMENTED | Separate security-sensitive contract. |
| Member permanent deletion/account creation | NOT_IMPLEMENTED | Separate destructive/lifecycle contracts. |
| Events Manager live contract discovery | LIVE_READ_ONLY_VERIFIED | Existing site transport discovered event/location surfaces; no live candidate mutation. |
| Events Manager 7.4.3 disposable model | REFERENCE_VERIFIED | Native installer/model used only as dependency fixture. |
| Event/location bounded reads | REFERENCE_VERIFIED | Four typed abilities; native permissions, bounded search/list/get allowlists and fail-closed missing IDs. |
| Ordinary event/location create/update | REFERENCE_VERIFIED | Four typed abilities; exact-state update conflicts, readback, rollback/cleanup, publish/object authority, isolation green. |
| Event dependency-state preservation | REFERENCE_VERIFIED | Metadata updates preserve active/booking/private state outside CMS Admin contract. |
| Referenced venue isolation | REFERENCE_VERIFIED | Event transaction preserves existing venue state despite dependency side effects; fail-closed if preservation cannot be verified. |
| Event trash | REFERENCE_VERIFIED | Ordinary single events only; exact state; native `delete(false)`; repeated trash refused; backing post trash readback; venue/unrelated-event isolation. |
| Event restore | REFERENCE_VERIFIED | Trash prerequisite; exact state; native `wp_untrash_post`; draft-only readback; object authority; injected-fault rollback to trash; booking and venue preserved. |
| Event permanent deletion | REFERENCE_VERIFIED | Trash prerequisite; exact state; explicit confirmation; object authority; native `delete(true)`; event identity/post absence verification. |
| Event permanent-delete booking guard | REFERENCE_VERIFIED | Events Manager aggregate count across statuses/owners; any booking refuses and preserves event + booking; verified zero permits hard delete. |
| Location deletion | NOT_IMPLEMENTED | Not part of the event lifecycle gate; add only for a concrete venue administration workflow. |
| Recurring-event administration | NOT_IMPLEMENTED | Not a current target; add only for a concrete Chattanooga workflow. |
| Booking/ticket/payment administration | NOT_IMPLEMENTED | Not an automatic target; higher-risk dependency surfaces. |
| Media/featured-image administration | NOT_IMPLEMENTED | Not an automatic priority; add only for a concrete content/event workflow. |
| Site administration autonomy gap recalculation | ACTIVE | Select the next gate from concrete Chattanooga workflows, not WordPress/plugin feature inventories. |
| Multisite/network administration | OUT_OF_SCOPE | Single-site production target. |
| Candidate live MCP discovery | UNKNOWN | Candidate not deployed. |
| Production deployment | NOT_DEPLOYED | Workbench evidence does not imply live installation. |
