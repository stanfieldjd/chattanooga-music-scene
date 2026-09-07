# Chattanooga CMS Admin Capability Matrix

Latest reference candidate: `119c32800af409b7c6d3e61afcd2e14abb68083d`.
Maintenance: `34171114708`; content/member: `34171114778`; multisite: `34171114718`; integrity: `34171114712`; artifact `10035759884`; SHA-256 `eac121d727b24129406bde3832ffdced8bdba7c2c5cbb8e29c0059923723f0f0`.

| Capability | State | Evidence / boundary |
| --- | --- | --- |
| PHP 7.4 / 8.2 + WordPress 7.1 activation | REFERENCE_VERIFIED | Current candidate passed. |
| Native Abilities registry | REFERENCE_VERIFIED | 58 candidate abilities. |
| Maintenance backup/update/rollback/cache/privacy/error model | REFERENCE_VERIFIED | Full maintenance regression green. |
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
| Events Manager disposable runtime | ACTIVE | Dedicated lab is next. |
| Event/location bounded reads in candidate | NOT_IMPLEMENTED | Requires disposable Events Manager model proof first. |
| Event/location create/update/trash | NOT_IMPLEMENTED | Separate transaction/rollback gates after reads. |
| Booking/ticket/payment administration | NOT_IMPLEMENTED | Higher-risk layer; separate gates. |
| Media/featured-image administration | NOT_IMPLEMENTED | Not an automatic priority; only when required by event/content administration. |
| Candidate live MCP discovery | UNKNOWN | Candidate not deployed. |
| Production deployment | NOT_DEPLOYED | Workbench evidence does not imply live installation. |
