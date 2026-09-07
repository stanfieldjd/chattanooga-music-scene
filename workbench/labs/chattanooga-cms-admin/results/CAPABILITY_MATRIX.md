# Chattanooga CMS Admin Capability Matrix

Evidence states:

- `SOURCE_PRESENT` — implementation exists in the workbench candidate source.
- `STUB_VERIFIED` — executed successfully under the workbench WordPress stubs.
- `REFERENCE_VERIFIED` — executed successfully in a disposable real WordPress reference runtime in GitHub Actions.
- `CONDITIONAL` — source/API exists but the exact operation or Chattanooga/DreamHost condition is not execution-verified.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — insufficient evidence for the target environment.
- `NOT_YET_IMPLEMENTED` — intentionally not present in the current maintenance-layer candidate.

Latest full reference runtime evidence: CMS Admin Workbench Lab run `34164417137`, test commit `f52ee6431fb0111ea5e9499466a2f04fe034aa71`. PHP 7.4, PHP 8.2, and the complete disposable WordPress 7.1 regression chain passed. Runtime capability artifact id `10033701473`, SHA-256 `e74eef622406878219d6cbd89d429befbfae2b2fe93d02771b1de013686a2712`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | Full GitHub lab passed source-shape, security/privacy-boundary, registration, syntax, and regression checks. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | Full GitHub lab passed the same test suite. |
| Workbench candidate source | REFERENCE_VERIFIED | Version-controlled and exercised under `workbench/mars`; not promoted to feature source or `main`. |
| WordPress 7.1 activation | REFERENCE_VERIFIED | Candidate activated successfully in disposable WordPress 7.1. |
| Native Abilities API | REFERENCE_VERIFIED | `wp_register_ability()` and category API exist in real WordPress 7.1. |
| CMS Admin category + 24 abilities | REFERENCE_VERIFIED | Category and all expected 24 abilities registered and were retrieved through the real registry. |
| MCP discovery metadata | SOURCE_PRESENT | Candidate declares MCP exposure metadata; actual Chattanooga transport discovery remains UNKNOWN. |
| Central capability/permission callbacks | REFERENCE_VERIFIED | All 24 denied anonymously, all 24 allowed for administrator, and isolated across exactly 10 intended capabilities. |
| WordPress Abilities REST isolation | REFERENCE_VERIFIED | Six core Abilities REST routes enumerated; candidate `show_in_rest=false` abilities absent from collection and direct execution probes failed closed. |
| Candidate-owned generic REST routes | REJECTED | Static test rejects direct custom REST execution surface. |
| Arbitrary shell/PHP/SQL execution | REJECTED | Static checks reject generic shell, PHP eval/code execution, and generic SQL ability surfaces. |
| Direct candidate HTTP/vendor calls | REJECTED | Candidate source has no direct outbound transport; WordPress core package APIs are separately tested. |
| Direct candidate member enumeration/access | REJECTED | Candidate source contract rejects direct user enumeration/meta/credential/secret access. |
| WordPress package-request privacy | REFERENCE_VERIFIED | Five disposable marker classes were absent in raw, URL-encoded, and base64 forms across 12 captured package requests. Only `api.wordpress.org` and `downloads.wordpress.org` were observed. |
| Error-output sensitive-marker redaction | REFERENCE_VERIFIED | Fault injection exposed raw plugin-API and MySQL restore diagnostics in run `34163733148`; repair commit `a21e834b7c866c696dd34015e223f099c1dd1f3d`; runs `34164107475` and `34164417137` passed plugin-API, plugin-updater, and database-restore marker absence with rollback preserved. |
| `WP_Filesystem`, `ZipArchive`, core copy/unzip primitives | REFERENCE_VERIFIED | Available and exercised in reference runtime; DreamHost behavior remains UNKNOWN. |
| Plugin/theme directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost remains UNKNOWN. |
| Preferred backup path outside web root | REFERENCE_VERIFIED | Created/writable in reference runtime; DreamHost remains UNKNOWN. |
| Plugin updater | REFERENCE_VERIFIED | Real WordPress.org plugin update passed with verified rollback backup. |
| Theme updater | REFERENCE_VERIFIED | Twenty Twenty-One 1.8 -> 2.9 update passed and exact rollback to 1.8 passed. |
| Core updater | REFERENCE_VERIFIED | Candidate-controlled WordPress 7.0 -> 7.1 update passed. |
| Plugin activation/deactivation | REFERENCE_VERIFIED | Candidate controls passed with state verification. |
| Plugin auto-update policy | REFERENCE_VERIFIED | Enable/disable policy passed with stored-state verification. |
| Theme switch | REFERENCE_VERIFIED | Candidate switched to `cmsa-lab-theme` and returned to the original Twenty Twenty-Five theme in run `34164417137`. |
| Theme auto-update policy | REFERENCE_VERIFIED | Enable/disable persisted and original policy state was restored in run `34164417137`. |
| Backup-protected plugin deletion/restore | REFERENCE_VERIFIED | Backup verified before deletion and exact fixture bytes restored. |
| Backup-protected theme deletion/restore | REFERENCE_VERIFIED | Backup verified before deletion and exact fixture bytes restored. |
| Forced plugin automatic rollback | REFERENCE_VERIFIED | Deliberate target-version mismatch triggered automatic rollback to Classic Editor 1.6 with exact pre-update main-file hash. |
| Database backup creation/checksum | REFERENCE_VERIFIED | Real MySQL dump and recorded SHA-256 verification passed. |
| Database restore | REFERENCE_VERIFIED | Sentinel value and exact numeric `option_id` identity restored after schema-aware numeric serialization repair. |
| Core rollback archive | REFERENCE_VERIFIED | Core+DB snapshot verification and deliberate core/root/database mutation restore passed exactly. |
| Normal core update transaction | REFERENCE_VERIFIED | 7.0 -> 7.1 passed; bootstrap, `wp-config.php`, `wp-content`, DB sentinel, and plugin activation preserved. |
| Forced core automatic rollback | REFERENCE_VERIFIED | Deliberate post-update validation failure restored exact WordPress 7.0 core/database state while preserving config/content/plugin activation. |
| Single-site cache clearing | REFERENCE_VERIFIED | Object/options cache path passed after root-cause repair of multisite-only call. |
| Multisite cache branch | CONDITIONAL | Dedicated real WordPress 7.1 multisite runtime gate is now defined and awaiting execution evidence. |
| WP Super Cache clearing | CONDITIONAL | WP Super Cache absent in reference runtime; Chattanooga-specific verification required. |
| Corrupt/incomplete backup rejection | CONDITIONAL | Checksum rejection primitives exist; dedicated corruption/partial-write/storage-failure suite remains pending. |
| Chattanooga/DreamHost filesystem behavior | UNKNOWN | Requires read-only live capability probe before deployment/mutation. |
| Chattanooga MCP discovery | UNKNOWN | Requires separately authorized installation/activation and actual transport discovery. |
| WooCommerce-specific update/migration/network behavior | UNKNOWN | Needs dedicated disposable WooCommerce tests. |
| Production-scale `wp-content` backup | UNKNOWN | Production-size storage/performance constraints remain unverified. |
| Self-hosted transport replacement | NOT_YET_IMPLEMENTED | Separate architecture/build task. |
| Member/content/event/commerce administration surface | NOT_YET_IMPLEMENTED | Broader typed abilities remain separate from maintenance layer. |

## Defect evidence and repair

### Single-site cache invalidation
A reference-runtime test exposed a fatal multisite-only cache call on single-site WordPress. The candidate was repaired at the cause and regression-tested.

### Database empty-string serialization
A database restore test exposed invalid SQL for empty bytes. Serialization now distinguishes null, empty string, and non-empty content.

### Numeric database-column serialization
The first forced-core rollback exposed numeric primary keys serialized as byte-hex literals, causing MySQL integer overflow/coercion during restore. The dump writer now inspects table column definitions and emits validated numeric columns as numeric SQL literals. Exact numeric primary-key identity and forced-core rollback then passed.

### Error-output raw diagnostics
Fault injection exposed upstream plugin-API and MySQL restore detail crossing the public ability boundary. Candidate error handling now maps those diagnostics to bounded public errors; dedicated marker regression passes while preserving rollback metadata.

### Historical package fixture
The first Classic Editor fixture used nonexistent version identifier `1.6.0`; corrected to published `1.6`, after which candidate update testing passed.

## Current gate

The maintenance layer now has disposable-reference proof for registration, capability isolation, REST isolation, backup/restore, plugin/theme package operations, package-request privacy, error-output redaction, plugin/theme lifecycle including theme switch and auto-update state, plugin/theme updates, plugin automatic rollback, core update, core restore, forced core automatic rollback, and single-site cache invalidation. The active unresolved workbench gate is real multisite cache execution, followed by backup failure handling and deterministic update-edge fixtures. DreamHost behavior, live installation, actual MCP discovery, WooCommerce-specific behavior, production-scale backup performance, private transport replacement, and broader site-administration abilities remain unproven.
