# Chattanooga CMS Admin Capability Matrix

Evidence states used here:

- `SOURCE_PRESENT` — implementation is present in the mirrored source.
- `CI_VERIFIED` — executed successfully in the GitHub workbench CI environment.
- `CONDITIONAL` — source path exists but depends on a runtime capability that must be probed.
- `REJECTED` — intentionally excluded from the architecture.
- `UNKNOWN` — not yet execution-verified in WordPress 7.1 on Chattanooga Music Scene.

| Capability | Current state | Required evidence before production use |
| --- | --- | --- |
| WordPress Abilities registration hooks | SOURCE_PRESENT | Real WordPress 7.1 probe + discovery through active AI transport |
| `wp_register_ability()` | UNKNOWN | Runtime function probe |
| `wp_register_ability_category()` | UNKNOWN | Runtime function probe |
| MCP discovery metadata | SOURCE_PRESENT | Actual MCP discovery of all expected abilities |
| Direct REST exposure | REJECTED | Static test must continue to reject `register_rest_route`; runtime should confirm abilities are not exposed through unintended REST paths |
| Capability/permission callbacks | SOURCE_PRESENT | Stub registration test + authenticated runtime permission test |
| Arbitrary shell execution | REJECTED | Static scan must remain clean for `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, and WP-CLI runcommand |
| Arbitrary PHP/code execution | REJECTED | Static scan must remain clean for `eval` and generic execute-code abilities |
| Generic arbitrary SQL ability | REJECTED | Ability manifest review; database access only through bounded backup/restore operations |
| Plugin update via WordPress upgrader APIs | CONDITIONAL | `Plugin_Upgrader` runtime probe, controlled component test, rollback verification |
| Theme update via WordPress upgrader APIs | CONDITIONAL | `Theme_Upgrader` runtime probe, controlled component test, rollback verification |
| Core update via WordPress upgrader APIs | CONDITIONAL | `Core_Upgrader` runtime probe, core backup verification, controlled validation path |
| WordPress.org plugin/theme installation APIs | CONDITIONAL | `plugins_api` / `themes_api` runtime probe; verify outbound requests contain no member data |
| Plugin activation/deactivation | CONDITIONAL | WordPress admin function probe + controlled test plugin |
| Plugin/theme deletion | CONDITIONAL | Filesystem/admin function probe + verified rollback archive + exact destructive authorization |
| Local database backup | SOURCE_PRESENT | Real database dump + checksum verification on Chattanooga Music Scene |
| Local plugin/theme rollback archive | SOURCE_PRESENT | `ZipArchive` + writable filesystem probe, controlled restore execution |
| WordPress core rollback archive | SOURCE_PRESENT | Filesystem capacity/writability probe + checksum verification + controlled restore plan |
| `ZipArchive` | UNKNOWN | Runtime class probe |
| `WP_Filesystem` | UNKNOWN | Runtime function probe and credential/filesystem-method behavior on DreamHost |
| Backup directory outside web root | CONDITIONAL | Runtime parent-directory writability probe |
| Backup directory under `wp-content` fallback | CONDITIONAL | Runtime writability/protection verification; use only if preferred location unavailable and security controls are validated |
| WP Super Cache clearing | CONDITIONAL | Runtime cache-function probe |
| WordPress object-cache clearing | CONDITIONAL | Runtime cache-function probe |
| Custom third-party telemetry | REJECTED | Static/network surface review must stay free of vendor telemetry clients |
| Member data visibility to AI | FUTURE_SCOPE | Separate bounded abilities are required; maintenance plugin tests must not silently invent member-data endpoints |

## Current gate

No entry marked `UNKNOWN` or `CONDITIONAL` is approved for production mutation solely because source or CI tests pass. Runtime probes and controlled execution evidence are still required.
