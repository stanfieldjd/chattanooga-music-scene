# Chattanooga CMS Admin Capability Matrix

Evidence states: `SOURCE_PRESENT`, `STUB_VERIFIED`, `REFERENCE_VERIFIED`, `LIVE_READ_ONLY_VERIFIED`, `CONDITIONAL`, `REJECTED`, `UNKNOWN`, `NOT_YET_IMPLEMENTED`.

Latest current-candidate maintenance evidence: run `34170460953`, commit `bb34fcbc2ffbe132eaf4ac12b651e423a2ff979d`, artifact `10035547833`, SHA-256 `a1a07985311e733a875502a4508a09de5c7d844fcdc0e8f724bf3ea589bbfd39`.
Latest content/member evidence: run `34170460924`.
Current candidate multisite evidence: run `34170324597` (candidate source identical; later commit changed only the member permission probe).
Workbench integrity: run `34170461007`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| PHP 7.4 / PHP 8.2 compatibility | REFERENCE_VERIFIED | Current candidate/full lab passed. |
| WordPress 7.1 activation | REFERENCE_VERIFIED | Single-site and multisite activation passed. |
| Native Abilities API + 56 abilities | REFERENCE_VERIFIED | 24 maintenance + 14 CRUD/revision + 2 status + 12 taxonomy + 4 member-read; registration `PASS (56 abilities)`. |
| Maintenance permission matrix | REFERENCE_VERIFIED | 24 abilities isolated across intended capabilities. |
| REST isolation | REFERENCE_VERIFIED | Candidate remains hidden from direct REST ability execution. |
| Arbitrary shell/PHP/SQL | REJECTED | Static architecture gate. |
| Generic candidate REST routes | REJECTED | Static architecture gate. |
| Direct candidate vendor HTTP | REJECTED | Official WordPress package API path only. |
| Package-request privacy | REFERENCE_VERIFIED | Seeded private markers absent from exercised WordPress.org traffic. |
| Public error redaction | REFERENCE_VERIFIED | Sensitive upstream diagnostics bounded. |
| Database/component/theme/core backup + restore | REFERENCE_VERIFIED | Exact restore gates passed. |
| Corrupt/missing rollback rejection | REFERENCE_VERIFIED | Fails before target mutation. |
| Unavailable storage | REFERENCE_VERIFIED | No backup registered/created. |
| Partial/stalled database writes | REFERENCE_VERIFIED | Complete writes enforced; incomplete SQL removed. |
| ZIP archive finalization | REFERENCE_VERIFIED | Close/zero-byte failure rejected and incomplete archive removed. |
| Plugin/theme/core updater/lifecycle | REFERENCE_VERIFIED | Normal transactions and forced rollback gates green. |
| Single-site + multisite cache | REFERENCE_VERIFIED | Real WordPress 7.1 paths passed. |
| Post/page list/get/create/update/revision/trash/restore | REFERENCE_VERIFIED | Bounded CRUD/revision transaction suite green. |
| Publication status transitions | REFERENCE_VERIFIED | Conflict checks, authority, scheduling and readback green. |
| Category/post-tag list/get/create/update | REFERENCE_VERIFIED | Term state token and deliberate rollback fault gate passed. |
| Post category/tag relationships | REFERENCE_VERIFIED | Exact expected set, default category, stale conflict and rollback verified. |
| Taxonomy term deletion | NOT_YET_IMPLEMENTED | Separate destructive relationship-consequence gate. |
| Permanent content deletion | NOT_YET_IMPLEMENTED | Separate destructive contract. |
| Member list/search | REFERENCE_VERIFIED | Bounded pagination/search/role filter; list uses explicit field allowlist. |
| Member detail | REFERENCE_VERIFIED | Selected account fields only; credentials/private internals absent. |
| Role inventory/member-role read | REFERENCE_VERIFIED | Dummy-user runtime verified. |
| Member-read permission boundary | REFERENCE_VERIFIED | Anonymous/subscriber denied; administrator/explicit `list_users` capability allowed. |
| Member query privacy boundary | REFERENCE_VERIFIED | `WP_User_Query` isolated to typed service; no arbitrary usermeta, credential, activation-key, session-token or direct users-table access. |
| Member selected profile update | NOT_YET_IMPLEMENTED | Active next Layer C gate; expected-before state + rollback required. |
| Member role mutation | NOT_YET_IMPLEMENTED | Active next Layer C gate; exact role-state + authority + rollback required. |
| Password/reset/session administration | NOT_YET_IMPLEMENTED | Separate security-sensitive contract. |
| Member permanent deletion | NOT_YET_IMPLEMENTED | Separate destructive contract. |
| Member account creation | NOT_YET_IMPLEMENTED | Notification/password lifecycle must be designed separately. |
| Events Manager contracts | SOURCE_PRESENT | Existing Chattanooga transport discovery confirms typed event/location/booking/ticket/category/tag operations; disposable candidate adapter not yet implemented. |
| Media/featured-image administration | NOT_YET_IMPLEMENTED | Not an automatic priority; implement when required by administration plan. |
| WooCommerce/marketplace administration | NOT_YET_IMPLEMENTED | Separate financial/order layer. |
| Chattanooga WordPress/PHP/MySQL | LIVE_READ_ONLY_VERIFIED | WordPress 7.1, PHP 8.2.30, MySQL 8.0.41. |
| Chattanooga relevant directory writability | LIVE_READ_ONLY_VERIFIED | Existing read-only system status reported writable. |
| Chattanooga WP Super Cache | LIVE_READ_ONLY_VERIFIED | Active, WP_CACHE enabled. |
| Existing Chattanooga MCP surface | LIVE_READ_ONLY_VERIFIED | 311 existing abilities; candidate absent because not deployed. |
| Chattanooga disk capacity/filesystem method/ZipArchive/outside-webroot backup parent | UNKNOWN | Current live read-only surface does not expose these facts. |
| Candidate MCP discovery on Chattanooga | UNKNOWN | Requires separately authorized installation. |
| Production-scale backup behavior | UNKNOWN | Production capacity/performance gate remains. |
| Self-hosted transport replacement | NOT_YET_IMPLEMENTED | Separate architecture task. |

## Current gate

Maintenance, content CRUD/revisions, publication status, taxonomy relationships, and bounded member reads are reference-verified. The active workbench gate is selected member profile/role mutation on disposable accounts. Events Manager is the next site-critical layer after Layer C unless priorities are recalculated. Production remains untouched.
