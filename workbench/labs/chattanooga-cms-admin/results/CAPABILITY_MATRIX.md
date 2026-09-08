# Chattanooga CMS Admin Capability Matrix

Latest reference candidate source: `dc1ac1322c14f073e82e2ae20ad315cfd79ee6c7`.
Single-site maintenance: `34174322773`; content/member: `34174322772`; integrity: `34174322771`; Events Manager reads: `34174699401`; artifact `10036756929`; SHA-256 `ff5c958c2bcdeefaaa1a87ff7d53eb45f6448c6f03679ed6f9cf0f42271d7bba`.

| Capability | State | Evidence / boundary |
| --- | --- | --- |
| Single-site product scope | REFERENCE_VERIFIED | Multisite/network behavior removed; static source gate rejects its reintroduction. |
| PHP 7.4 / 8.2 + WordPress 7.1 activation | REFERENCE_VERIFIED | Current single-site candidate passed. |
| Native Abilities registry | REFERENCE_VERIFIED | 62 candidate abilities. |
| Maintenance backup/update/rollback/cache/privacy/error model | REFERENCE_VERIFIED | Full single-site maintenance regression green. |
| Post/page CRUD/revisions/status | REFERENCE_VERIFIED | Dedicated content runtime green. |
| Category/post-tag terms/relationships | REFERENCE_VERIFIED | Conflict and rollback fault gates green. |
| Member list/search/detail/role reads | REFERENCE_VERIFIED | Bounded allowlists; credentials/private internals absent. |
| Member profile display-name/URL update | REFERENCE_VERIFIED | Expected-state, readback and injected-fault rollback green. |
| Member account email mutation | NOT_IMPLEMENTED | Explicitly excluded after WordPress notification side effect was observed; separate notification/security gate required. |
| Member role-state replacement | REFERENCE_VERIFIED | Expected-state, editable-role validation, self guard and rollback green. |
| Member mutation notifications | REFERENCE_VERIFIED | Zero mail attempts in corrected runtime gate. |
| Password/reset/session operations | NOT_IMPLEMENTED | Separate security-sensitive contract. |
| Member permanent deletion/account creation | NOT_IMPLEMENTED | Separate destructive/lifecycle contracts. |
| Events Manager live contract discovery | LIVE_READ_ONLY_VERIFIED | 39 existing site abilities discovered; no live mutation. |
| Events Manager 7.4.3 disposable model | REFERENCE_VERIFIED | Native installer, classes/helpers, post types and event taxonomies verified. |
| Event/location bounded reads | REFERENCE_VERIFIED | Four typed abilities; native permissions, bounded search/list/get allowlists and not-found fail-closed passed. |
| Ordinary event/location create/update | ACTIVE | Next disposable gate; expected-before/readback/rollback required. |
| Recurring-event administration | NOT_IMPLEMENTED | Not a current target; add only for a concrete Chattanooga need. |
| Event/location trash/delete | NOT_IMPLEMENTED | Separate destructive gate if required. |
| Booking/ticket/payment administration | NOT_IMPLEMENTED | Not a current target; higher-risk layer. |
| Media/featured-image administration | NOT_IMPLEMENTED | Not an automatic priority; only when required by an actual content/event workflow. |
| Multisite/network administration | OUT_OF_SCOPE | User explicitly does not plan to support it; dedicated workflow and candidate branches removed. |
| Candidate live MCP discovery | UNKNOWN | Candidate not deployed. |
| Production deployment | NOT_DEPLOYED | Workbench evidence does not imply live installation. |
