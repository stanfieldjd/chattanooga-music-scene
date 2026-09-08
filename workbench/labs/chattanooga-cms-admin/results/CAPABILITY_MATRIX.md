# Chattanooga CMS Admin Capability Matrix

Latest reference candidate checkpoint: `2eaed7eab4575ffc4c5db036a513924494d50cf4`; event/location source correction: `9874d5192cef627292df09bd81fd1f7aaa59315d`.
Single-site maintenance: `34183243913`; content/member: `34183243906`; integrity: `34183243890`; Events Manager runtime: `34183462063`; artifact `10039648550`; SHA-256 `ba3c42d62ea5c0f87df81cea6eabcb6614724a2cf53d2b30d35bbc87291393d3`.

| Capability | State | Evidence / boundary |
| --- | --- | --- |
| Single-site product scope | REFERENCE_VERIFIED | Multisite/network behavior is out of scope and not an acceptance target. |
| Third-party plugin source boundary | REFERENCE_VERIFIED | Installed plugins are immutable dependency surfaces; only Chattanooga CMS Admin and disposable harness code are changed. |
| PHP 7.4 / 8.2 + WordPress 7.1 activation | REFERENCE_VERIFIED | Current single-site candidate passed. |
| Native Abilities registry | REFERENCE_VERIFIED | 66 candidate abilities. |
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
| Events Manager live contract discovery | LIVE_READ_ONLY_VERIFIED | Existing site transport discovered event/location surfaces; no live mutation. |
| Events Manager 7.4.3 disposable model | REFERENCE_VERIFIED | Native installer/model used only as dependency fixture. |
| Event/location bounded reads | REFERENCE_VERIFIED | Four typed abilities; native permissions, bounded search/list/get allowlists and fail-closed missing IDs. |
| Ordinary event/location create/update | REFERENCE_VERIFIED | Four typed abilities; exact-state update conflicts, readback, rollback/cleanup, publish/object authority, isolation green. |
| Event dependency-state preservation | REFERENCE_VERIFIED | Metadata updates preserve active/booking/private state outside CMS Admin contract. |
| Referenced venue isolation | REFERENCE_VERIFIED | Event transaction preserves existing venue state despite dependency side effects; fail-closed if preservation cannot be verified. |
| Recurring-event administration | NOT_IMPLEMENTED | Not a current target; add only for a concrete Chattanooga workflow. |
| Event/location trash/delete | NOT_IMPLEMENTED | Separate destructive gate if actual administration requires it. |
| Booking/ticket/payment administration | NOT_IMPLEMENTED | Not an automatic target; higher-risk dependency surfaces. |
| Media/featured-image administration | NOT_IMPLEMENTED | Not an automatic priority; add only for a concrete content/event workflow. |
| Site administration autonomy gap review | ACTIVE | Recalculate missing typed abilities from actual Chattanooga workflows rather than plugin inventories. |
| Multisite/network administration | OUT_OF_SCOPE | Single-site production target. |
| Candidate live MCP discovery | UNKNOWN | Candidate not deployed. |
| Production deployment | NOT_DEPLOYED | Workbench evidence does not imply live installation. |
