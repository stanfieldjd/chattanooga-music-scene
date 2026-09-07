# Chattanooga CMS Admin Capability Matrix

Evidence states:

- `SOURCE_PRESENT` — implementation exists in the workbench candidate source.
- `STUB_VERIFIED` — executed successfully under the workbench WordPress stubs.
- `REFERENCE_VERIFIED` — executed successfully in a disposable real WordPress reference runtime in GitHub Actions.
- `CONDITIONAL` — source/API exists but the exact operation or Chattanooga/DreamHost condition is not execution-verified.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — insufficient evidence for the target environment.
- `NOT_YET_IMPLEMENTED` — intentionally not present in the current maintenance-layer candidate.

Latest full reference runtime evidence: CMS Admin Workbench Lab run `34161768634`, source checkpoint `37839c3a95b21394dab139b31a5d22d12e41676d`. PHP 7.4, PHP 8.2, and the disposable WordPress 7.1 / PHP 8.2.33 / MySQL 8.0.46 regression runtime all passed. The core transaction phase then moved only that disposable fixture to WordPress 7.0 and the candidate upgraded it back to 7.1. Runtime capability artifact: `cmsa-runtime-capabilities`, artifact id `10032846544`, SHA-256 digest `ac6892ba1fe16d9483608366fd36c50128e843a8f5d6375c101f1422ef7656a7`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | Latest GitHub PHP 7.4 lab passed manifest, source-shape, security, privacy-boundary, registration, and syntax checks. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | Latest GitHub PHP 8.2 lab passed the same static/test suite. |
| Workbench candidate source | REFERENCE_VERIFIED | Candidate source is version-controlled under `workbench/mars` and exercised by the disposable lab. It contains workbench-only repairs not yet promoted to the feature branch. |
| WordPress 7.1 plugin activation | REFERENCE_VERIFIED | Candidate activated successfully on disposable WordPress 7.1. Chattanooga production remains a separate target. |
| `wp_register_ability()` | REFERENCE_VERIFIED | Function exists in real WordPress 7.1 runtime. |
| `wp_register_ability_category()` | REFERENCE_VERIFIED | Function exists in real WordPress 7.1 runtime. |
| CMS Admin ability category | REFERENCE_VERIFIED | Registered in WordPress's real ability registry. |
| Expected 24 CMS Admin abilities | REFERENCE_VERIFIED | All 24 registered and were retrieved successfully through the real WordPress 7.1 registry. |
| MCP discovery metadata | SOURCE_PRESENT | Candidate declares MCP exposure metadata; actual Chattanooga MCP transport discovery remains UNKNOWN. |
| Direct custom REST route registration | REJECTED | Static test rejects candidate-owned generic REST routes. WordPress core Abilities REST infrastructure is separate. |
| Central capability/permission callbacks | REFERENCE_VERIFIED | Real WordPress 7.1 denied all 24 abilities anonymously, allowed all 24 for an administrator, and isolated the 24 abilities across exactly 10 intended WordPress capabilities with no cross-capability grants. |
| WordPress Abilities REST isolation | REFERENCE_VERIFIED | Runtime enumerated six Abilities REST routes, including list and run controllers. Candidate abilities marked `show_in_rest=false` were absent from the collection and the `get-health` ability was not exposed/executable through direct GET/POST route probes. |
| Arbitrary shell execution | REJECTED | Static scan rejects `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, and generic WP-CLI command execution. |
| Arbitrary PHP/code execution | REJECTED | Static scan rejects `eval` and generic execute-code ability patterns. |
| Generic arbitrary SQL ability | REJECTED | No generic SQL execution ability exists; database operations are bounded backup/restore operations. |
| Direct candidate HTTP/vendor calls | REJECTED | Privacy-boundary test rejects `wp_remote_get`, `wp_remote_post`, `wp_remote_request`, direct cURL/socket transports, and literal external URLs in candidate PHP. |
| Direct candidate member enumeration/access | REJECTED | Privacy-boundary test rejects `get_users`, `WP_User_Query`, direct user-meta access, `$wpdb->users`, credential fields, and WordPress secret constants. This is a candidate-source contract, not a claim that WordPress core or unrelated plugins never make network calls. |
| `WP_Filesystem` | REFERENCE_VERIFIED | Available in disposable WordPress; DreamHost filesystem method/credentials remain UNKNOWN. |
| `ZipArchive` | REFERENCE_VERIFIED | Available in disposable PHP 8.2.33; Chattanooga's live PHP extension state must still be probed. |
| `wp_mkdir_p`, `unzip_file`, `copy_dir` | REFERENCE_VERIFIED | Available and exercised in the reference WordPress runtime. |
| Plugin directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost target remains UNKNOWN. |
| Theme directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost target remains UNKNOWN. |
| Backup directory outside WordPress web root | REFERENCE_VERIFIED | Preferred parent-directory backup path was created/writable in reference runtime. DreamHost remains UNKNOWN. |
| `Plugin_Upgrader` | REFERENCE_VERIFIED | Class available and actual plugin update transaction passed. |
| `Theme_Upgrader` | REFERENCE_VERIFIED | Class available and an actual Twenty Twenty-One update transaction passed. |
| `Core_Upgrader` | REFERENCE_VERIFIED | Class available and an actual candidate-controlled WordPress 7.0 -> 7.1 core update transaction passed. |
| `plugins_api()` / `themes_api()` | REFERENCE_VERIFIED | Functions available and WordPress.org package retrieval was exercised. Their network behavior is WordPress core behavior, not a third-party management service. |
| Candidate plugin activation/deactivation controls | REFERENCE_VERIFIED | Classic Widgets was installed through the candidate, activated, verified active, deactivated, and verified inactive. |
| Plugin auto-update policy control | REFERENCE_VERIFIED | Disposable fixture plugin auto-update was enabled, verified, disabled, and verified. |
| Backup-protected plugin deletion | REFERENCE_VERIFIED | Disposable fixture plugin was backed up, checksum-verified, deleted through `CMSA_Lifecycle`, then restored. |
| Plugin rollback fidelity | REFERENCE_VERIFIED | Fixture `state.txt` SHA-256 after restore exactly matched the pre-delete SHA-256. |
| Forced plugin validation rollback | REFERENCE_VERIFIED | A real Classic Editor update package was installed while only the advertised target version was deliberately mismatched; candidate validation failed as intended, automatic rollback reported success, and version 1.6 plus the exact pre-update main-file SHA-256 were restored. |
| Theme deletion/rollback | REFERENCE_VERIFIED | Disposable theme fixture was backup-protected, deleted, restored, and its `state.txt` SHA-256 matched the pre-delete state. |
| Local database backup creation | REFERENCE_VERIFIED | Real WordPress / MySQL database dump created successfully. |
| Backup SHA-256 verification | REFERENCE_VERIFIED | Database, component, and core rollback artifacts passed checksum verification. |
| Plugin component rollback archive | REFERENCE_VERIFIED | Fixture plugin archive creation, verification, mutation/deletion, restoration, and byte-fidelity test passed. |
| Database restore | REFERENCE_VERIFIED | Sentinel option was backed up, mutated, database restored, and direct database read matched the backed-up value. Empty-string serialization defect was repaired and regression-tested. |
| WordPress core rollback archive | REFERENCE_VERIFIED | Core+database snapshot creation and verification passed; deliberate mutations to `wp-includes/version.php`, `readme.html`, and a database sentinel were restored exactly. |
| Health report on single-site WordPress 7.1 | REFERENCE_VERIFIED | DB response, writable directories, backup storage, ZipArchive, version, and environment checks passed. |
| WordPress cache clearing on single-site | REFERENCE_VERIFIED | Workbench repair separates single-site options-cache invalidation from multisite `clean_blog_cache()` behavior; regression test passed. |
| Multisite cache branch | CONDITIONAL | Code gates `clean_blog_cache()` behind multisite/function checks, but a disposable multisite runtime test has not yet been executed. |
| WP Super Cache clearing | CONDITIONAL | WP Super Cache was not installed in the disposable reference runtime; Chattanooga-specific verification remains required. |
| Actual WordPress.org plugin installation | REFERENCE_VERIFIED | Candidate installed `classic-widgets/classic-widgets.php` from WordPress.org and the installed plugin was verified. |
| Actual WordPress.org plugin activation/deactivation | REFERENCE_VERIFIED | Installed Classic Widgets was activated and deactivated through candidate controls with state verification. |
| Actual WordPress.org plugin update | REFERENCE_VERIFIED | Classic Editor was prepared at 1.6, candidate updated it to 1.7.0, version advancement was verified, and a rollback backup id was returned. |
| Actual WordPress.org theme installation | REFERENCE_VERIFIED | Candidate installed Twenty Twenty-One and WordPress verified the theme exists. |
| Actual theme update transaction | REFERENCE_VERIFIED | Twenty Twenty-One 1.8 was updated through `CMSA_Updates::update_theme()` to 2.9, the rollback archive verified, and rollback returned the theme to 1.8 with exact `style.css` SHA-256 fidelity. |
| WordPress core update transaction | REFERENCE_VERIFIED | After the full 7.1 regression suite, only the disposable fixture was moved to WordPress 7.0. `CMSA_Updates::update_core()` created and verified a rollback snapshot, upgraded to 7.1, and a fresh bootstrap check confirmed the site remained installed and the candidate remained active. `wp-config.php`, `wp-content`, and database sentinels were unchanged. |
| Core backup/restore fidelity | REFERENCE_VERIFIED | Core archive and database snapshot were verified, core/root files and DB were deliberately mutated, and restoration returned exact pre-mutation hashes/values. |
| Core automatic rollback on update failure | CONDITIONAL | The core success transaction and independent core restore fidelity are proven, but a deliberately failed core update has not yet exercised `rollback_core_error()`. |
| Chattanooga/DreamHost filesystem behavior | UNKNOWN | Requires a read-only live capability probe before deployment/mutation. |
| Chattanooga MCP discovery of the 24 abilities | UNKNOWN | Requires plugin installation/activation and actual transport discovery in a separately authorized production phase. |
| WooCommerce-specific update/migration behavior | UNKNOWN | Needs dedicated disposable WooCommerce migration/update tests before production use. |
| Full `wp-content` backup at Chattanooga production scale | UNKNOWN | Algorithm exists, but production-size performance, storage capacity, and DreamHost constraints are not verified. |
| Self-hosted transport replacing third-party MCP transport | NOT_YET_IMPLEMENTED | Current workbench concerns the WordPress administration ability layer. Private transport replacement remains a separate architecture/build task. |
| Member/content/event/commerce administration surface | NOT_YET_IMPLEMENTED | Current candidate is the maintenance layer. Expansion remains separate; no generic backdoor substitutes for typed abilities. |

## Defect evidence and repair

### Single-site cache invalidation

The first expanded runtime test found a fatal call to multisite-only `clean_blog_cache()` on single-site WordPress. The candidate was repaired at the cause: multisite uses that API only when available, while single-site invalidates WordPress's options caches and still flushes the object cache. Regression testing passed.

### Database empty-string serialization

The first database restore fidelity test found that an empty database value was serialized as invalid SQL token `0x`. The dump writer now emits `NULL` for null, `''` for an empty string, and `0x<hex>` for non-empty bytes. The post-repair database sentinel restore and the complete runtime suite passed.

### Historical update fixture

The first WordPress.org update fixture asked for nonexistent Classic Editor `1.6.0`; WordPress.org returned 404 before candidate code ran. The fixture was corrected to the published `1.6` identifier. The candidate then successfully updated Classic Editor from 1.6 to 1.7.0 with a rollback backup id.

## Current gate

The workbench establishes that the maintenance-layer candidate loads on WordPress 7.1; all 24 registered abilities enforce their intended capability boundaries; candidate abilities remain isolated from WordPress's REST execution surface; and the tested backup/restore, lifecycle, health/cache, plugin update, plugin automatic rollback, theme update, core update, and core restore paths operate in a disposable reference environment. It does **not** establish DreamHost filesystem behavior, live Chattanooga installation, actual MCP discovery, core automatic rollback after a deliberately failed core update, package-request privacy capture, multisite behavior, WP Super Cache integration, WooCommerce migrations, production-scale backup performance, private MCP transport replacement, or the broader member/content/event/commerce administration surface. Those remain explicit gates rather than assumptions.
