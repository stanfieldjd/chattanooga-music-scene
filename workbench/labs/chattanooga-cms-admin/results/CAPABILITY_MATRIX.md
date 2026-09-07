# Chattanooga CMS Admin Capability Matrix

Evidence states:

- `SOURCE_PRESENT` — implementation exists in the immutable baseline/candidate source.
- `STUB_VERIFIED` — executed successfully under the workbench WordPress stubs.
- `REFERENCE_VERIFIED` — executed successfully in a disposable real WordPress 7.1 / PHP 8.2 reference runtime in GitHub Actions.
- `CONDITIONAL` — source/API exists but the exact operation or Chattanooga/DreamHost condition is not execution-verified.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — insufficient evidence for the target environment.

Reference runtime evidence: CMS Admin Workbench Lab run `34118396822`, commit `76f7f8a05f07014b13712b011cfec9fb69d0b666`.

| Capability | Current state | Evidence / remaining gate |
| --- | --- | --- |
| Candidate PHP 7.4 compatibility | REFERENCE_VERIFIED | GitHub PHP 7.4 lab passed. |
| Candidate PHP 8.2 compatibility | REFERENCE_VERIFIED | GitHub PHP 8.2 lab passed. |
| Immutable source mirror | REFERENCE_VERIFIED | All 9 baseline Git blob identities verified against source checkpoint `0b34773e...`. |
| WordPress 7.1 plugin activation | REFERENCE_VERIFIED | Candidate activated successfully on disposable WordPress 7.1. Chattanooga production still separate. |
| `wp_register_ability()` | REFERENCE_VERIFIED | Function exists in real WordPress 7.1 runtime. |
| `wp_register_ability_category()` | REFERENCE_VERIFIED | Function exists in real WordPress 7.1 runtime. |
| CMS Admin ability category | REFERENCE_VERIFIED | Registered in WordPress's real ability registry after lifecycle execution. |
| Expected 24 CMS Admin abilities | REFERENCE_VERIFIED | All 24 retrieved successfully through `wp_get_ability()` in real WordPress 7.1. |
| MCP discovery metadata | SOURCE_PRESENT | Actual Chattanooga AI/MCP transport discovery remains UNKNOWN. |
| Direct custom REST route registration | REJECTED | Static test rejects `register_rest_route()`. WordPress core itself contains Abilities REST controllers; candidate-specific REST visibility still requires an explicit route/exposure test. |
| Central capability/permission callbacks | STUB_VERIFIED | All 24 have callable permission callbacks under stubs. Real role/capability matrix still needs controlled authenticated tests. |
| Arbitrary shell execution | REJECTED | Static scan rejects `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, and WP-CLI `runcommand`. |
| Arbitrary PHP/code execution | REJECTED | Static scan rejects `eval` and generic execute-code ability patterns. |
| Generic arbitrary SQL ability | REJECTED | No generic SQL execution ability exists; DB operations are bounded backup/restore functions. |
| `WP_Filesystem` | REFERENCE_VERIFIED | Available in disposable WordPress 7.1; DreamHost filesystem method/credentials remain UNKNOWN. |
| `ZipArchive` | REFERENCE_VERIFIED | Available in reference PHP 8.2 runtime; Chattanooga PHP must still be probed. |
| `wp_mkdir_p`, `unzip_file`, `copy_dir` | REFERENCE_VERIFIED | Available in reference WordPress runtime. |
| Plugin directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost target remains UNKNOWN. |
| Theme directory writability | REFERENCE_VERIFIED | Writable in reference runtime; DreamHost target remains UNKNOWN. |
| Backup directory outside WordPress web root | REFERENCE_VERIFIED | Preferred parent-directory backup path was created/writable in reference runtime. DreamHost remains UNKNOWN. |
| `Plugin_Upgrader` | REFERENCE_VERIFIED | Class available; actual controlled update transaction still CONDITIONAL. |
| `Theme_Upgrader` | REFERENCE_VERIFIED | Class available; actual controlled update transaction still CONDITIONAL. |
| `Core_Upgrader` | REFERENCE_VERIFIED | Class available; core update/rollback transaction still CONDITIONAL. |
| `plugins_api()` / `themes_api()` | REFERENCE_VERIFIED | Functions available. Outbound request payload/privacy behavior must be captured before relying on them. |
| Plugin activation/deactivation functions | REFERENCE_VERIFIED | Functions present; controlled lifecycle execution test still planned. |
| Plugin/theme deletion functions | REFERENCE_VERIFIED | Functions present; destructive execution only against disposable fixtures until exact live authorization exists. |
| Local database backup creation | REFERENCE_VERIFIED | Real DB dump created in WordPress 7.1 reference runtime. |
| Backup SHA-256 verification | REFERENCE_VERIFIED | Database and component archive checksums passed. |
| Plugin component rollback archive | REFERENCE_VERIFIED | Fixture plugin archived, deliberately mutated, restored, and original bytes verified. |
| Theme component rollback archive | CONDITIONAL | Same engine is shared, but a theme fixture restore has not yet been executed. |
| Database restore | CONDITIONAL | Restore code exists; controlled sentinel restore test remains pending. |
| WordPress core rollback archive | SOURCE_PRESENT | Creation/restoration execution tests remain pending. |
| WordPress object-cache flush | REFERENCE_VERIFIED | Core cache flush functions available in reference runtime. |
| WP Super Cache clearing | CONDITIONAL | WP Super Cache was not installed in the disposable reference runtime; Chattanooga-specific probe required. |
| Custom vendor telemetry | REJECTED | Static scan found no direct cURL/vendor telemetry primitive. Only WordPress package API calls (`plugins_api`, `themes_api`) were detected as external package surfaces. |
| Chattanooga/DreamHost filesystem behavior | UNKNOWN | Requires read-only live capability probe before deployment/mutation. |
| Chattanooga MCP discovery of the 24 abilities | UNKNOWN | Requires plugin installation/activation and actual transport discovery in a separately authorized phase. |
| Member/content/event/commerce administration surface | NOT_YET_IMPLEMENTED | Current candidate is the maintenance layer. Expansion is tracked in `ROADMAP.md`; no hidden or generic backdoor substitutes for those typed abilities. |

## Current gate

The reference environment establishes that the architecture is viable on real WordPress 7.1 and that the current backup/component rollback path executes successfully. It does **not** establish DreamHost filesystem behavior, Chattanooga production installation, actual MCP discovery, database/core restore correctness, update transactions, or full member/content administration. Those remain separate gates.
