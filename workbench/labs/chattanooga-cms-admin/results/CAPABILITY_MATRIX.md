# Chattanooga CMS Admin Capability Matrix

Evidence states:

- `SOURCE_PRESENT` — implementation exists in the workbench candidate source.
- `STUB_VERIFIED` — executed successfully under the workbench WordPress stubs.
- `REFERENCE_VERIFIED` — executed successfully in a disposable real WordPress reference runtime in GitHub Actions.
- `LIVE_READ_ONLY_VERIFIED` — verified non-mutating fact from the connected Chattanooga WordPress environment.
- `CONDITIONAL` — source/API exists but the exact operation or Chattanooga/DreamHost condition is not execution-verified.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — insufficient evidence for the target environment.
- `NOT_YET_IMPLEMENTED` — intentionally not present in the current candidate.

Latest full maintenance evidence: CMS Admin Workbench Lab run `34168314554`, candidate commit `b5ba61e0f435624a6f834566a3fa85ede13221f7`, artifact `10034902046`, SHA-256 `ebf4f3e668d0e73dd19a539732570b1d7cd66d7b8c34c31921da2c7222f20b95`.
Latest bounded content evidence: CMS Admin Content Layer Lab run `34168314548`.
Current-candidate multisite source evidence: run `34168247940`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | Full maintenance lab passed. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | Full maintenance and content labs passed. |
| WordPress 7.1 activation | REFERENCE_VERIFIED | Candidate activates in disposable single-site and multisite runtimes. |
| Native Abilities API | REFERENCE_VERIFIED | `wp_register_ability()` and category API exercised in real WordPress 7.1. |
| CMS Admin category + 38 current abilities | REFERENCE_VERIFIED | 24 maintenance + 14 content abilities registered; content run output `PASS (38 abilities)`. |
| Maintenance permission matrix | REFERENCE_VERIFIED | 24 maintenance abilities isolated across 10 WordPress capabilities. |
| Content coarse permission matrix | REFERENCE_VERIFIED | 14 content abilities: anonymous denied, administrator allowed, post-only limited user isolated from page abilities. |
| Content object-level authorization | REFERENCE_VERIFIED | Limited user can read own post but not another author's post and cannot read page without page authority. |
| WordPress Abilities REST isolation | REFERENCE_VERIFIED | Candidate namespace remains hidden from direct REST execution/collection exposure. |
| MCP discovery metadata | SOURCE_PRESENT | Current candidate declares MCP discovery metadata; live candidate discovery awaits deployment. |
| Candidate-owned generic REST routes | REJECTED | Static gate rejects direct generic REST execution surface. |
| Arbitrary shell/PHP/SQL execution | REJECTED | Static gate rejects generic code/shell/SQL backdoors. |
| Direct candidate HTTP/vendor calls | REJECTED | No direct vendor transport; official WordPress package APIs are separately tested. |
| WordPress package-request privacy | REFERENCE_VERIFIED | Seeded private markers absent across captured official WordPress.org package operations. |
| Public error sensitive-marker redaction | REFERENCE_VERIFIED | Plugin API/updater/database restore diagnostics bounded and marker-free. |
| Database backup/checksum/restore | REFERENCE_VERIFIED | Real MySQL backup and exact restore including numeric PK identity. |
| Corrupt/missing rollback rejection | REFERENCE_VERIFIED | Invalid material rejected before target mutation. |
| Storage unavailable handling | REFERENCE_VERIFIED | All configured paths non-writable => no backup created. |
| Partial/stalled database write handling | REFERENCE_VERIFIED | Short writes completed; stalled writes rejected; incomplete SQL removed. |
| Component/core archive finalization | REFERENCE_VERIFIED | ZIP close/zero-byte failure rejected and incomplete archive removed. |
| Plugin updater/lifecycle | REFERENCE_VERIFIED | Install/update/activate/deactivate/auto-update/delete+rollback gates passed. |
| Theme updater/lifecycle | REFERENCE_VERIFIED | Install/update/switch/auto-update/delete+rollback gates passed. |
| Core update + automatic rollback | REFERENCE_VERIFIED | 7.0→7.1 plus deliberate validation failure exact rollback passed. |
| Single-site cache clearing | REFERENCE_VERIFIED | Object/options cache path passed. |
| Multisite cache branch | REFERENCE_VERIFIED | Current candidate source network-activated and cache probe passed in run `34168247940`. |
| Post list/get | REFERENCE_VERIFIED | Bounded list/search/status and object-level read exercised. |
| Page list/get | REFERENCE_VERIFIED | Bounded page query and object-level read exercised. |
| Draft post creation | REFERENCE_VERIFIED | Candidate forces draft; no publish path in this ability. |
| Draft page creation + parent validation | REFERENCE_VERIFIED | Child page created with validated parent relationship. |
| Post/page optimistic concurrency | REFERENCE_VERIFIED | Stale `post_modified_gmt` fails closed without mutation. |
| Post/page update rollback revision | REFERENCE_VERIFIED | Native WordPress pre-update revision created and validated. |
| Post/page revision restore | REFERENCE_VERIFIED | Target-owned revision restored after conflict check with rollback revision for pre-restore state. |
| Post/page trash + restore | REFERENCE_VERIFIED | Recoverable lifecycle exercised; no permanent-delete path in this slice. |
| Unrelated content isolation | REFERENCE_VERIFIED | Control sentinel unchanged across content transaction suite. |
| Publish/unpublish/schedule/private/pending transitions | NOT_YET_IMPLEMENTED | Next bounded Layer B sub-gate. |
| Taxonomy/category/tag administration | NOT_YET_IMPLEMENTED | Needs typed term/relationship contracts and tests. |
| Media + featured-image administration | NOT_YET_IMPLEMENTED | Needs file/type/relationship gates. |
| Permanent content deletion | NOT_YET_IMPLEMENTED | Requires separate explicit destructive contract and tests. |
| Member/account administration | NOT_YET_IMPLEMENTED | Separate Layer C with privacy/role/profile contracts. |
| Events Manager administration | NOT_YET_IMPLEMENTED | Separate Layer D using verified Events Manager model. |
| WooCommerce/marketplace administration | NOT_YET_IMPLEMENTED | Separate Layer E with financial/order safeguards. |
| Chattanooga WordPress/PHP/MySQL versions | LIVE_READ_ONLY_VERIFIED | WordPress 7.1, PHP 8.2.30, MySQL 8.0.41. |
| Chattanooga WordPress core/wp-content/plugin/theme/upload writability | LIVE_READ_ONLY_VERIFIED | Existing system-status ability reported writable; no mutation performed. |
| Chattanooga WP Super Cache presence | LIVE_READ_ONLY_VERIFIED | WP Super Cache active and WP_CACHE enabled. |
| Existing Chattanooga MCP discovery surface | LIVE_READ_ONLY_VERIFIED | Existing transport returned 311 abilities; candidate namespace absent because not installed. |
| Chattanooga free disk/backup capacity | UNKNOWN | Current connected surface did not expose it. |
| Chattanooga WordPress filesystem method | UNKNOWN | Current connected surface did not expose it. |
| Chattanooga live ZipArchive availability | UNKNOWN | Not directly exposed by current live read-only abilities. |
| Chattanooga outside-web-root backup parent writability | UNKNOWN | Not exposed by current read-only surface. |
| Chattanooga candidate MCP discovery | UNKNOWN | Requires separately authorized candidate installation/activation. |
| Production-scale wp-content backup | UNKNOWN | Capacity/performance remains unverified. |
| Self-hosted transport replacement | NOT_YET_IMPLEMENTED | Separate architecture/build task. |

## Current gate

System maintenance and the first bounded post/page content slice are reference-verified. The non-mutating Chattanooga preflight is partially verified with remaining server-level storage/filesystem facts explicitly unknown. Next engineering work should extend Layer B through separately tested status transitions, taxonomy relationships, and media relationships while production deployment/MCP discovery remains a separate authorization boundary.
