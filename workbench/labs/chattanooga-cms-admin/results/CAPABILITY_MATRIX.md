# Chattanooga CMS Admin Capability Matrix

Evidence states:

- `SOURCE_PRESENT` — implementation exists in the workbench candidate source.
- `STUB_VERIFIED` — executed successfully under the workbench WordPress stubs.
- `REFERENCE_VERIFIED` — executed successfully in a disposable real WordPress reference runtime in GitHub Actions.
- `CONDITIONAL` — source/API exists but the exact operation or Chattanooga/DreamHost condition is not execution-verified.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — insufficient evidence for the target environment.
- `NOT_YET_IMPLEMENTED` — intentionally not present in the current maintenance-layer candidate.

Latest full reference runtime evidence: CMS Admin Workbench Lab run `34162917097`, source checkpoint `c0a54be38ed087d3b428eaa8e76d4ba7fdcaacb6`. PHP 7.4, PHP 8.2, and the disposable WordPress 7.1 / PHP 8.2.33 / MySQL 8.0.46 regression runtime passed. The core phases then used WordPress 7.0 fixtures for a successful 7.0 -> 7.1 candidate update and a forced post-update validation failure that automatically rolled back to exact 7.0 state. Runtime capability artifact: `cmsa-runtime-capabilities`, artifact id `10033217958`, SHA-256 digest `e5a033888454e7f583bb50cf39716874e593c0b2dc02058d16f0884b32055ae9`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | Latest GitHub PHP 7.4 lab passed manifest, source-shape, security, privacy-boundary, registration, and syntax checks. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | Latest GitHub PHP 8.2 lab passed the same static/test suite. |
| Workbench candidate source | REFERENCE_VERIFIED | Candidate source is version-controlled under `workbench/mars` and exercised by the disposable lab. It contains workbench-only repairs not yet promoted to the feature branch. |
| WordPress 7.1 plugin activation | REFERENCE_VERIFIED | Candidate activated successfully on disposable WordPress 7.1. Chattanooga production remains a separate target. |
| `wp_register_ability()` / `wp_register_ability_category()` | REFERENCE_VERIFIED | Native functions exist in real WordPress 7.1. |
| CMS Admin ability category and expected 24 abilities | REFERENCE_VERIFIED | Category and all 24 abilities registered and were retrieved through the real registry. |
| MCP discovery metadata | SOURCE_PRESENT | Candidate declares MCP exposure metadata; actual Chattanooga MCP transport discovery remains UNKNOWN. |
| Direct custom REST route registration | REJECTED | Static test rejects candidate-owned generic REST routes. WordPress core Abilities REST infrastructure is separate. |
| Central capability/permission callbacks | REFERENCE_VERIFIED | Real WordPress denied all 24 abilities anonymously, allowed all 24 for an administrator, and isolated them across exactly 10 intended capabilities with no cross-capability grants. |
| WordPress Abilities REST isolation | REFERENCE_VERIFIED | Six Abilities REST routes were enumerated. Candidate abilities marked `show_in_rest=false` were absent from the collection and direct GET/POST probes did not expose or execute `get-health`. |
| Arbitrary shell execution | REJECTED | Static scan rejects `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, and generic WP-CLI command execution. |
| Arbitrary PHP/code execution | REJECTED | Static scan rejects `eval` and generic execute-code ability patterns. |
| Generic arbitrary SQL ability | REJECTED | No generic SQL execution ability exists; database operations are bounded backup/restore operations. |
| Direct candidate HTTP/vendor calls | REJECTED | Privacy-boundary test rejects candidate-owned direct HTTP transports and literal external URLs. WordPress core package APIs are separately tested. |
| Direct candidate member enumeration/access | REJECTED | Privacy-boundary test rejects direct user enumeration/meta/credential/secret access in candidate source. |
| `WP_Filesystem`, `ZipArchive`, `wp_mkdir_p`, `unzip_file`, `copy_dir` | REFERENCE_VERIFIED | Available and exercised in the disposable runtime; DreamHost behavior remains UNKNOWN. |
| Plugin/theme directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost remains UNKNOWN. |
| Backup directory outside WordPress web root | REFERENCE_VERIFIED | Preferred parent-directory backup path was created/writable in reference runtime. DreamHost remains UNKNOWN. |
| `Plugin_Upgrader` | REFERENCE_VERIFIED | Actual plugin update transaction passed. |
| `Theme_Upgrader` | REFERENCE_VERIFIED | Actual Twenty Twenty-One update transaction passed. |
| `Core_Upgrader` | REFERENCE_VERIFIED | Actual candidate-controlled WordPress 7.0 -> 7.1 transaction passed. |
| `plugins_api()` / `themes_api()` | REFERENCE_VERIFIED | Functions available and package retrieval was exercised; request-content privacy capture remains pending. |
| Candidate plugin activation/deactivation controls | REFERENCE_VERIFIED | Classic Widgets was installed, activated, verified active, deactivated, and verified inactive through candidate controls. |
| Plugin auto-update policy control | REFERENCE_VERIFIED | Disposable plugin auto-update policy was enabled, verified, disabled, and verified. |
| Backup-protected plugin deletion | REFERENCE_VERIFIED | Disposable plugin was backed up, checksum-verified, deleted, and restored. |
| Plugin rollback fidelity | REFERENCE_VERIFIED | Restored fixture bytes matched pre-delete SHA-256. |
| Forced plugin validation rollback | REFERENCE_VERIFIED | Deliberate target-version mismatch triggered automatic rollback to Classic Editor 1.6 with exact pre-update main-file SHA-256. |
| Theme deletion/rollback | REFERENCE_VERIFIED | Disposable theme was backed up, deleted, restored, and exact fixture bytes returned. |
| Local database backup creation | REFERENCE_VERIFIED | Real WordPress/MySQL dump created successfully. |
| Backup SHA-256 verification | REFERENCE_VERIFIED | Database, component, and core rollback artifacts passed checksum verification. |
| Database restore | REFERENCE_VERIFIED | Sentinel value and exact numeric `option_id` identity were restored. Schema-aware numeric serialization prevents binary-literal integer overflow. |
| WordPress core rollback archive | REFERENCE_VERIFIED | Core+database snapshot creation and verification passed; deliberate core/root/database mutations restored exactly. |
| Health report on single-site WordPress 7.1 | REFERENCE_VERIFIED | DB response, writable directories, backup storage, ZipArchive, version, and environment checks passed. |
| WordPress cache clearing on single-site | REFERENCE_VERIFIED | Single-site options-cache invalidation and object-cache flush passed. |
| Multisite cache branch | CONDITIONAL | Code gates multisite behavior, but a disposable multisite runtime test has not yet executed. |
| WP Super Cache clearing | CONDITIONAL | WP Super Cache was not installed in the disposable reference runtime; Chattanooga-specific verification remains required. |
| Actual WordPress.org plugin installation/update | REFERENCE_VERIFIED | Candidate installed Classic Widgets and updated Classic Editor 1.6 -> 1.7.0 with rollback backup. |
| Actual WordPress.org theme installation/update | REFERENCE_VERIFIED | Candidate installed Twenty Twenty-One and updated 1.8 -> 2.9; rollback restored exact 1.8 `style.css`. |
| WordPress core update transaction | REFERENCE_VERIFIED | Candidate created/verified rollback snapshot, updated 7.0 -> 7.1, and preserved bootstrap, `wp-config.php`, `wp-content`, database sentinel, and plugin activation. |
| Core backup/restore fidelity | REFERENCE_VERIFIED | Core archive/database snapshot restored exact pre-mutation files and DB values. |
| Core automatic rollback on update validation failure | REFERENCE_VERIFIED | A real 7.1 package was installed while only the expected selected version was deliberately mismatched; `rollback_core_error()` returned exact 7.0 core state and database state, with configuration/content unchanged and candidate still active. |
| Chattanooga/DreamHost filesystem behavior | UNKNOWN | Requires a read-only live capability probe before deployment/mutation. |
| Chattanooga MCP discovery of the 24 abilities | UNKNOWN | Requires installation/activation and actual transport discovery in a separately authorized production phase. |
| WooCommerce-specific update/migration behavior | UNKNOWN | Needs dedicated disposable WooCommerce migration/update tests before production use. |
| Full `wp-content` backup at Chattanooga production scale | UNKNOWN | Algorithm exists, but production-size performance, storage capacity, and DreamHost constraints are not verified. |
| Self-hosted transport replacing third-party MCP transport | NOT_YET_IMPLEMENTED | Current workbench concerns the WordPress administration ability layer. |
| Member/content/event/commerce administration surface | NOT_YET_IMPLEMENTED | Current candidate is the maintenance layer; broader typed abilities remain separate. |

## Defect evidence and repair

### Single-site cache invalidation

The first expanded runtime test found a fatal call to multisite-only `clean_blog_cache()` on single-site WordPress. The candidate was repaired at the cause: multisite uses that API only when available, while single-site invalidates WordPress options caches and flushes the object cache. Regression testing passed.

### Database empty-string serialization

The first database restore fidelity test found that an empty database value was serialized as invalid SQL token `0x`. The dump writer was corrected to distinguish null, empty string, and non-empty bytes. Regression testing passed.

### Numeric database-column serialization

The first forced-core rollback run exposed that numeric primary keys were also serialized as byte-hex literals. A larger `wp_options.option_id` created during the core update was coerced by MySQL into an overflowing unsigned integer, causing duplicate-primary-key failure during rollback. The dump writer now inspects each table's column definitions and emits validated numeric columns as numeric SQL literals while preserving separate null, empty-string, and binary/string encodings. Run `34162917097` proved exact `option_id` identity across restore and the previously failing forced-core rollback passed.

### Historical update fixture

The first WordPress.org update fixture asked for nonexistent Classic Editor `1.6.0`; WordPress.org returned 404 before candidate code ran. The fixture was corrected to published version `1.6`, then the candidate update path passed.

## Current gate

The maintenance-layer candidate now has reference-runtime proof for registration, capability isolation, REST isolation, backups/restores, plugin/theme lifecycle operations already exercised, plugin/theme updates, plugin automatic rollback, core update, core restore, and forced core automatic rollback. The next unresolved workbench gates are package-request privacy capture, error-output redaction, explicit theme switch/theme auto-update verification, multisite cache behavior, and backup failure handling. DreamHost filesystem behavior, live Chattanooga installation, actual MCP discovery, WooCommerce migrations, production-scale backup performance, private transport replacement, and broader site-administration abilities remain explicitly unproven.
