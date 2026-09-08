# Chattanooga CMS Admin Capability Matrix

Latest reference candidate checkpoint: `b105ea0edf3fdb071794357770e1f0f9ed1c12ac`.
Single-site maintenance: `34187848901`; content/member/navigation: `34187757628`; integrity: `34187848894`; Events Manager regression: `34187848906`; artifact `10041154773`; SHA-256 `c9b312bccbd5e5c12356e3048cf33085282c8c7f2f8fccb8e98b8c3b9d1011e8`.

| Capability | State | Evidence / boundary |
| --- | --- | --- |
| Single-site product scope | REFERENCE_VERIFIED | Multisite/network behavior is out of scope and not an acceptance target. |
| Third-party plugin source boundary | REFERENCE_VERIFIED | Installed plugins are immutable dependency surfaces; only Chattanooga CMS Admin and disposable harness code are changed. |
| PHP 7.4 / 8.2 + WordPress 7.1 activation | REFERENCE_VERIFIED | Current single-site candidate passed. |
| Native Abilities registry | REFERENCE_VERIFIED | 74 candidate abilities. |
| Maintenance backup/update/rollback/cache/privacy/error model | REFERENCE_VERIFIED | Full single-site maintenance regression green. |
| Post/page CRUD/revisions/status | REFERENCE_VERIFIED | Dedicated content runtime green. |
| Post/page permanent deletion | REFERENCE_VERIFIED | Trash-only prerequisite, exact modified-state conflict, explicit confirmation, native object permission, absence verification and unrelated-content isolation. |
| Category/post-tag terms/relationships | REFERENCE_VERIFIED | Conflict and rollback fault gates green. |
| Core navigation menu list/get/create | REFERENCE_VERIFIED | Bounded normalized menus/items plus registered locations; edit_theme_options authority. |
| Core navigation item create/update | REFERENCE_VERIFIED | Published page/custom root-relative or HTTP(S) links only; exact menu-state conflict, parent/cycle validation, readback and rollback/cleanup. |
| Core navigation item deletion | REFERENCE_VERIFIED | Explicit destructive confirmation; exact menu state; item absence verified; linked page preserved. |
| Core navigation location assignment | REFERENCE_VERIFIED | Exact assignment-map state; registered locations only; assign/unassign; injected-fault rollback green. |
| Core navigation whole-menu rename/delete | ACTIVE | Small lifecycle symmetry gap; delete must require unassigned menu, exact state and explicit confirmation. |
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
| Recurring-event administration | NOT_IMPLEMENTED | Not a current target; add only for a concrete Chattanooga workflow. |
| Event/location trash/delete | NOT_IMPLEMENTED | Separate destructive gate if an actual Chattanooga administration workflow requires it. |
| Booking/ticket/payment administration | NOT_IMPLEMENTED | Not an automatic target; higher-risk dependency surfaces. |
| Media/featured-image administration | NOT_IMPLEMENTED | Not an automatic priority; add only for a concrete content/event workflow. |
| Multisite/network administration | OUT_OF_SCOPE | Single-site production target. |
| Candidate live MCP discovery | UNKNOWN | Candidate not deployed. |
| Production deployment | NOT_DEPLOYED | Workbench evidence does not imply live installation. |
