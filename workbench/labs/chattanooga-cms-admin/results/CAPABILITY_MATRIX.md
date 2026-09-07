# Chattanooga CMS Admin Capability Matrix

Evidence states:

- `SOURCE_PRESENT` — implementation exists in the immutable baseline/candidate source.
- `STUB_VERIFIED` — executed successfully under the workbench WordPress stubs.
- `REFERENCE_VERIFIED` — executed successfully in a disposable real WordPress 7.1 / PHP 8.2 reference runtime in GitHub Actions.
- `CONDITIONAL` — source/API exists but the exact operation or Chattanooga/DreamHost condition is not execution-verified.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — insufficient evidence for the target environment.

Latest reference runtime evidence: CMS Admin Workbench Lab run `34137540169`, commit `44aaf8cbf9b82e1dc0ac85b98714b62ea1dfb001`. PHP 7.4 lab, PHP 8.2 lab, and the disposable WordPress 7.1 runtime job all passed. Runtime capability artifact: `cmsa-runtime-capabilities`, artifact id `10024603660`, SHA-256 digest `557ee8efecfeed8a607efe304ed69ded00fcebc97f9ecfdb1d3f1912970dd4cc`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | Latest GitHub PHP 7.4 lab passed, including manifest, source-shape, security, privacy-boundary, and registration tests. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | Latest GitHub PHP 8.2 lab passed, including manifest, source-shape, security, privacy-boundary, and registration tests. |
| Immutable source mirror | REFERENCE_VERIFIED | Candidate source is mirrored in the workbench and checked against the feature-source manifest. |
| WordPress 7.1 plugin activation | REFERENCE_VERIFIED | Candidate activated successfully on disposable WordPress 7.1. Chattanooga production remains a separate target. |
| `wp_register_ability()` | REFERENCE_VERIFIED | Function exists in real WordPress 7.1 runtime. |
| `wp_register_ability_category()` | REFERENCE_VERIFIED | Function exists in real WordPress 7.1 runtime. |
| CMS Admin ability category | REFERENCE_VERIFIED | Registered in WordPress's real ability registry. |
| Expected 24 CMS Admin abilities | REFERENCE_VERIFIED | All 24 registered and were retrieved successfully through the real WordPress 7.1 registry. |
| MCP discovery metadata | SOURCE_PRESENT | Actual Chattanooga AI/MCP transport discovery remains UNKNOWN. |
| Direct custom REST route registration | REJECTED | Static test rejects candidate-owned generic REST routes. WordPress core Abilities REST infrastructure is separate and remains governed by WordPress permissions/exposure metadata. |
| Central capability/permission callbacks | STUB_VERIFIED | All 24 have callable permission callbacks under stubs. Real authenticated role/capability matrix tests remain pending. |
| Arbitrary shell execution | REJECTED | Static scan rejects `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, and generic WP-CLI command execution. |
| Arbitrary PHP/code execution | REJECTED | Static scan rejects `eval` and generic execute-code ability patterns. |
| Generic arbitrary SQL ability | REJECTED | No generic SQL execution ability exists; database operations are bounded backup/restore operations. |
| Direct candidate HTTP/vendor calls | REJECTED | Privacy-boundary test rejects `wp_remote_get`, `wp_remote_post`, `wp_remote_request`, direct cURL/socket transports, and literal external URLs in candidate PHP. |
| Direct candidate member enumeration/access | REJECTED | Privacy-boundary test rejects `get_users`, `WP_User_Query`, direct user-meta access, `$wpdb->users`, credential fields, and WordPress secret constants. This is a candidate-source contract, not a claim that WordPress core or unrelated plugins never make network calls. |
| `WP_Filesystem` | REFERENCE_VERIFIED | Available in disposable WordPress 7.1; DreamHost filesystem method/credentials remain UNKNOWN. |
| `ZipArchive` | REFERENCE_VERIFIED | Available in disposable PHP 8.2 runtime; Chattanooga PHP extension state must still be probed live. |
| `wp_mkdir_p`, `unzip_file`, `copy_dir` | REFERENCE_VERIFIED | Available in reference WordPress runtime. |
| Plugin directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost target remains UNKNOWN. |
| Theme directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost target remains UNKNOWN. |
| Backup directory outside WordPress web root | REFERENCE_VERIFIED | Preferred parent-directory backup path was created/writable in reference runtime. DreamHost remains UNKNOWN. |
| `Plugin_Upgrader` | REFERENCE_VERIFIED | Class available; an actual WordPress.org plugin update transaction remains CONDITIONAL. |
| `Theme_Upgrader` | REFERENCE_VERIFIED | Class available; an actual theme update transaction remains CONDITIONAL. |
| `Core_Upgrader` | REFERENCE_VERIFIED | Class available; core update/rollback transaction remains CONDITIONAL. |
| `plugins_api()` / `themes_api()` | REFERENCE_VERIFIED | Functions available. Their WordPress.org package/network behavior is core behavior and must be treated separately from the no-third-party-management-service requirement. |
| Plugin activation | REFERENCE_VERIFIED | Candidate itself activated successfully in the disposable WordPress runtime. |
| Plugin auto-update policy control | REFERENCE_VERIFIED | Disposable fixture plugin auto-update was enabled, verified, disabled, and verified. |
| Backup-protected plugin deletion | REFERENCE_VERIFIED | Disposable fixture plugin was backed up, checksum-verified, deleted through `CMSA_Lifecycle`, then restored. |
| Plugin rollback fidelity | REFERENCE_VERIFIED | Fixture `state.txt` SHA-256 after restore exactly matched the pre-delete SHA-256. |
| Theme deletion/rollback | CONDITIONAL | Shared component-backup engine exists, but a disposable theme deletion/restore fixture test has not yet been executed. |
| Local database backup creation | REFERENCE_VERIFIED | Real database dump created in WordPress 7.1 reference runtime. |
| Backup SHA-256 verification | REFERENCE_VERIFIED | Database and component archive checksums passed. |
| Plugin component rollback archive | REFERENCE_VERIFIED | Fixture plugin archive creation, verification, mutation/deletion, restoration, and byte-fidelity test passed. |
| Database restore | CONDITIONAL | Restore code exists; a controlled sentinel database restore test remains pending. |
| WordPress core rollback archive | SOURCE_PRESENT | Creation/restoration execution tests remain pending. |
| Health report on single-site WordPress 7.1 | REFERENCE_VERIFIED | DB response, writable directories, backup storage, ZipArchive, version, and environment checks passed. |
| WordPress cache clearing on single-site | REFERENCE_VERIFIED | Root-cause defect found and repaired in the workbench candidate: `clean_blog_cache()` was multisite-only. Single-site now invalidates `alloptions`/`notoptions` cache and flushes object cache; runtime test passed. |
| Multisite cache branch | CONDITIONAL | Code now gates `clean_blog_cache()` behind multisite/function checks, but a disposable multisite runtime test has not yet been executed. |
| WP Super Cache clearing | CONDITIONAL | WP Super Cache was not installed in the disposable reference runtime; Chattanooga-specific verification remains required. |
| Chattanooga/DreamHost filesystem behavior | UNKNOWN | Requires a read-only live capability probe before deployment/mutation. |
| Chattanooga MCP discovery of the 24 abilities | UNKNOWN | Requires plugin installation/activation and actual transport discovery in a separately authorized production phase. |
| Actual WordPress.org plugin installation | CONDITIONAL | Required WordPress APIs are present; no real package installation transaction has been exercised yet. |
| Actual WordPress.org plugin update | CONDITIONAL | Required upgrader APIs are present; no real plugin update transaction has been exercised yet. |
| Actual theme installation/update | CONDITIONAL | Required APIs are present; no real transaction has been exercised yet. |
| WordPress core update transaction | CONDITIONAL | `Core_Upgrader` is present; no disposable core update + rollback test has been completed. |
| WooCommerce-specific update/migration behavior | UNKNOWN | Needs dedicated disposable WooCommerce migration/update tests before production use. |
| Full `wp-content` backup at Chattanooga production scale | UNKNOWN | Algorithm exists, but production-size performance, storage capacity, and DreamHost constraints are not verified. |
| Self-hosted transport replacing third-party MCP transport | NOT_YET_IMPLEMENTED | Current workbench concerns the WordPress administration ability layer. Private transport replacement remains a separate architecture/build task. |
| Member/content/event/commerce administration surface | NOT_YET_IMPLEMENTED | Current candidate is the maintenance layer. Expansion is tracked separately; no generic backdoor substitutes for typed abilities. |

## Defect evidence and repair

The expanded runtime lab initially failed on a normal single-site WordPress 7.1 install because `CMSA_Health::clear_cache()` called `clean_blog_cache()` unconditionally. The runtime produced a fatal `Call to undefined function clean_blog_cache()` after activation, Abilities registration, database backup, component rollback, and lifecycle tests had already passed.

The workbench candidate was repaired at the source of the defect: multisite uses `clean_blog_cache()` only when available; single-site invalidates the WordPress options cache through `wp_cache_delete( 'alloptions', 'options' )` and `wp_cache_delete( 'notoptions', 'options' )`, while retaining object-cache and WP Super Cache clearing when available. The corrected WordPress 7.1 runtime job passed.

## Current gate

The workbench now establishes that the maintenance architecture loads and operates in a disposable real WordPress 7.1 environment, and that its tested backup/lifecycle/health paths are viable. It does **not** establish DreamHost filesystem behavior, Chattanooga production installation, actual MCP discovery, database/core restore correctness, WordPress.org update transactions, WooCommerce migrations, WP Super Cache integration, or full member/content administration. Those remain explicit separate gates rather than assumptions.
