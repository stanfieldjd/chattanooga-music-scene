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

Latest full maintenance evidence: CMS Admin Workbench Lab run `34168825545`, candidate commit `2f299aa743f82f03888954dc1beec9ad1bafb999`, artifact `10035054428`, SHA-256 `3af7c6d621fafe7146cd825da165f655f313e7ef922ff4e22a336c85fc900e06`.
Latest bounded content/status evidence: CMS Admin Content Layer Lab run `34168825779`.
Current-candidate multisite evidence: run `34168825541`.
Workbench integrity: run `34168825835`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | Full maintenance lab passed. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | Full maintenance and content labs passed. |
| WordPress 7.1 activation | REFERENCE_VERIFIED | Candidate activates in disposable single-site and multisite runtimes. |
| Native Abilities API | REFERENCE_VERIFIED | `wp_register_ability()` and category API exercised in real WordPress 7.1. |
| CMS Admin category + 40 current abilities | REFERENCE_VERIFIED | 24 maintenance + 14 CRUD/revision + 2 status abilities; registration output `PASS (40 abilities)`. |
| Maintenance permission matrix | REFERENCE_VERIFIED | 24 maintenance abilities isolated across 10 WordPress capabilities. |
| Content CRUD coarse permission matrix | REFERENCE_VERIFIED | 14 content abilities: anonymous denied, administrator allowed, post-only limited user isolated from page abilities. |
| Content object-level authorization | REFERENCE_VERIFIED | Limited user can read own post but not another author's post and cannot read page without page authority. |
| Status ability coarse permissions | REFERENCE_VERIFIED | Anonymous denied, administrator gets post/page, limited edit-post user gets post only. |
| Publish authority enforcement | REFERENCE_VERIFIED | Edit-only user may move own post to pending but cannot publish/private/schedule without `publish_posts`; analogous page service uses `publish_pages`. |
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
| Multisite cache branch | REFERENCE_VERIFIED | Current 40-ability source network-activated and cache probe passed in run `34168825541`. |
| Post list/get | REFERENCE_VERIFIED | Bounded list/search/status and object-level read exercised. |
| Page list/get | REFERENCE_VERIFIED | Bounded page query and object-level read exercised. |
| Draft post creation | REFERENCE_VERIFIED | Candidate forces draft. |
| Draft page creation + parent validation | REFERENCE_VERIFIED | Child page created with validated parent relationship. |
| Post/page optimistic concurrency | REFERENCE_VERIFIED | Stale `post_modified_gmt` fails closed without mutation. |
| Post/page update rollback revision | REFERENCE_VERIFIED | Native WordPress pre-update revision created and validated. |
| Post/page revision restore | REFERENCE_VERIFIED | Target-owned revision restored after conflict check with rollback revision for pre-restore state. |
| Post/page trash + restore | REFERENCE_VERIFIED | Recoverable lifecycle exercised; no permanent-delete path in this slice. |
| Post/page pending transition | REFERENCE_VERIFIED | Draft→pending exercised for posts and pages. |
| Post/page publish transition | REFERENCE_VERIFIED | Publish authority enforced; post/page publish exercised. |
| Post private transition | REFERENCE_VERIFIED | Pending→private exercised under publisher authority. |
| Post scheduling | REFERENCE_VERIFIED | Publish→future with explicit UTC date verified; future→publish-now date normalization verified. |
| Status optimistic concurrency | REFERENCE_VERIFIED | Both stale modified timestamp and stale expected status fail closed. |
| Status verification rollback path | SOURCE_PRESENT | Exact prior status/date rollback is implemented for verification/readback failures; normal successful and denial paths are runtime-verified. Deliberate post-write verification fault injection can be added if needed before promotion. |
| Unrelated content isolation | REFERENCE_VERIFIED | Control sentinel unchanged across CRUD/revision/status transaction suites. |
| Taxonomy/category/tag administration | NOT_YET_IMPLEMENTED | Active next bounded Layer B gate. |
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

System maintenance, bounded post/page CRUD/revision, and bounded publication-status transitions are reference-verified. The active engineering gate is taxonomy/category/tag relationships. The non-mutating Chattanooga preflight remains partial, and production deployment/MCP discovery remains a separate authorization boundary.
