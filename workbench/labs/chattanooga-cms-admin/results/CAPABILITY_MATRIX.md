# Chattanooga CMS Admin Capability Matrix

Evidence states:

- `SOURCE_PRESENT` — implementation exists in the workbench candidate source.
- `STUB_VERIFIED` — executed successfully under the workbench WordPress stubs.
- `REFERENCE_VERIFIED` — executed successfully in a disposable real WordPress reference runtime in GitHub Actions.
- `CONDITIONAL` — source/API exists but the exact operation or Chattanooga/DreamHost condition is not execution-verified.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — insufficient evidence for the target environment.
- `NOT_YET_IMPLEMENTED` — intentionally not present in the current maintenance-layer candidate.

Latest complete single-site/full-regression evidence: CMS Admin Workbench Lab run `34165355234`, commit `7b6e25b4a1103f260fdf36237482d9e7c7787934`, artifact `10033988433`, SHA-256 `bea034a0a86a3de8ed85208cf61a059a996ae64ff45eaae3cfefd640479d28df`. Real multisite evidence: CMS Admin Multisite Lab run `34164758622`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | Full GitHub lab passed. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | Full GitHub lab passed. |
| Workbench candidate source | REFERENCE_VERIFIED | Version-controlled and exercised under `workbench/mars`; not promoted to feature source or `main`. |
| WordPress 7.1 activation | REFERENCE_VERIFIED | Candidate activated successfully in disposable WordPress 7.1. |
| Native Abilities API | REFERENCE_VERIFIED | `wp_register_ability()` and category API exist in real WordPress 7.1. |
| CMS Admin category + 24 abilities | REFERENCE_VERIFIED | Category and all expected 24 abilities registered and retrieved through the real registry. |
| MCP discovery metadata | SOURCE_PRESENT | Candidate declares MCP exposure metadata; actual Chattanooga transport discovery remains UNKNOWN. |
| Central capability/permission callbacks | REFERENCE_VERIFIED | All 24 denied anonymously, all 24 allowed for administrator, isolated across exactly 10 intended capabilities. |
| WordPress Abilities REST isolation | REFERENCE_VERIFIED | Candidate `show_in_rest=false` abilities absent from collection and direct execution probes failed closed. |
| Candidate-owned generic REST routes | REJECTED | Static test rejects direct custom REST execution surface. |
| Arbitrary shell/PHP/SQL execution | REJECTED | Static checks reject generic shell, PHP eval/code execution, and generic SQL ability surfaces. |
| Direct candidate HTTP/vendor calls | REJECTED | Candidate source has no direct outbound transport; WordPress core package APIs are separately tested. |
| Direct candidate member enumeration/access | REJECTED | Candidate source contract rejects direct user enumeration/meta/credential/secret access. |
| WordPress package-request privacy | REFERENCE_VERIFIED | Five disposable marker classes absent across 12 captured package requests; only WordPress.org API/download hosts observed. |
| Error-output sensitive-marker redaction | REFERENCE_VERIFIED | Fault injection and repair verified plugin API, updater rollback, and DB restore public errors do not contain seeded markers. |
| `WP_Filesystem`, `ZipArchive`, core copy/unzip primitives | REFERENCE_VERIFIED | Available and exercised in reference runtime; DreamHost behavior remains UNKNOWN. |
| Plugin/theme directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost remains UNKNOWN. |
| Preferred backup path outside web root | REFERENCE_VERIFIED | Created/writable in reference runtime; DreamHost remains UNKNOWN. |
| Plugin updater | REFERENCE_VERIFIED | Real WordPress.org plugin update passed with verified rollback backup. |
| Theme updater | REFERENCE_VERIFIED | Twenty Twenty-One 1.8 -> 2.9 update passed and exact rollback to 1.8 passed. |
| Core updater | REFERENCE_VERIFIED | Candidate-controlled WordPress 7.0 -> 7.1 update passed. |
| Plugin activation/deactivation | REFERENCE_VERIFIED | Candidate controls passed with state verification. |
| Plugin auto-update policy | REFERENCE_VERIFIED | Enable/disable policy passed with stored-state verification. |
| Theme switch | REFERENCE_VERIFIED | Candidate switched to fixture and returned to original theme. |
| Theme auto-update policy | REFERENCE_VERIFIED | Enable/disable persisted and original policy state restored. |
| Backup-protected plugin deletion/restore | REFERENCE_VERIFIED | Backup verified before deletion and exact fixture bytes restored. |
| Backup-protected theme deletion/restore | REFERENCE_VERIFIED | Backup verified before deletion and exact fixture bytes restored. |
| Forced plugin automatic rollback | REFERENCE_VERIFIED | Deliberate target-version mismatch triggered exact rollback. |
| Database backup creation/checksum | REFERENCE_VERIFIED | Real MySQL dump and recorded SHA-256 verification passed. |
| Database restore | REFERENCE_VERIFIED | Sentinel and exact numeric primary-key identity restored. |
| Core rollback archive | REFERENCE_VERIFIED | Core+DB snapshot mutation/restore passed exactly. |
| Normal core update transaction | REFERENCE_VERIFIED | 7.0 -> 7.1 passed with config/content/database/plugin state preserved. |
| Forced core automatic rollback | REFERENCE_VERIFIED | Deliberate post-update validation failure restored exact 7.0 core/database state. |
| Single-site cache clearing | REFERENCE_VERIFIED | `object-cache,wordpress-options-cache` path passed. |
| Multisite cache branch | REFERENCE_VERIFIED | Real WordPress 7.1 network install + candidate network activation passed; exact result `object-cache,wordpress-blog-cache` in run `34164758622`. |
| WP Super Cache clearing | CONDITIONAL | WP Super Cache absent in reference runtime; Chattanooga-specific verification required. |
| Corrupt/incomplete backup rejection | REFERENCE_VERIFIED | Corrupted component, missing component archive, and corrupted DB snapshot were rejected before restore with target unchanged. |
| Storage unavailable handling | REFERENCE_VERIFIED | Run `34165355234`: all configured backup paths non-writable; `cmsa_backup_directory`; backup not created; complete downstream regression green. |
| Partial-write/disk-space failure handling | CONDITIONAL | Remains pending. |
| Deterministic plugin update edge cases | CONDITIONAL | Active gate: no-update, local v1→v2 package, malformed-package rollback. |
| Chattanooga/DreamHost filesystem behavior | UNKNOWN | Requires read-only live capability probe before deployment/mutation. |
| Chattanooga MCP discovery | UNKNOWN | Requires separately authorized installation/activation and actual transport discovery. |
| WooCommerce-specific update/migration/network behavior | UNKNOWN | Needs dedicated disposable WooCommerce tests. |
| Production-scale `wp-content` backup | UNKNOWN | Production-size storage/performance constraints remain unverified. |
| Self-hosted transport replacement | NOT_YET_IMPLEMENTED | Separate architecture/build task. |
| Member/content/event/commerce administration surface | NOT_YET_IMPLEMENTED | Broader typed abilities remain separate from maintenance layer. |

## Current gate

Registration, permissions, REST isolation, backup/restore, corrupt/missing rollback rejection, unavailable-storage handling, package privacy, error redaction, plugin/theme lifecycle and updates, core update/rollback, and both single-site and real multisite cache branches are reference-verified. The active workbench gate is deterministic local plugin update edge behavior, followed by partial-write/disk-space failure handling. DreamHost behavior, live installation, actual MCP discovery, WooCommerce-specific behavior, production-scale backup performance, private transport replacement, and broader site-administration abilities remain unproven.
