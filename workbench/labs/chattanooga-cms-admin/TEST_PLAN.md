# Chattanooga CMS Admin Workbench Test Plan

Tests are ordered so that a failure in a lower-risk prerequisite blocks higher-risk operations. Disposable GitHub/WordPress fixtures are used before any Chattanooga runtime test.

## Gate 0 — Source and architecture
Status: PASS
- Immutable baseline Git blob verification.
- PHP 7.4 and PHP 8.2 lint.
- No arbitrary shell/PHP/SQL execution surface.
- No custom direct REST route registration.
- Exact expected ability manifest.

## Gate 1 — Real WordPress 7.1 registration
Status: PASS
- Install and activate candidate.
- Verify native Abilities API.
- Register category/all 24 abilities and retrieve them through real registry.

## Gate 2 — Backup primitives and fail-closed restore
Status: PASS in disposable reference runtime
Evidence: runs `34165007012`, `34165355234`, `34166633321`, and `34166835095`.
- database backup + SHA-256 verification;
- plugin component exact-byte restore;
- database sentinel restore and numeric primary-key identity;
- theme deletion/restore fidelity;
- core + database snapshot and exact deliberate-mutation restore;
- corrupted component archive rejection without target mutation;
- missing component archive rejection;
- corrupted database snapshot rejection without database mutation;
- all configured backup paths unavailable/read-only returns `cmsa_backup_directory` and creates no backup;
- progressive short writes are completed exactly;
- stalled writes fail rather than accepting truncated database output;
- partial database output is removed when writing/finalization fails;
- component and core ZIP finalization failure is checked and incomplete/zero-byte archive output is removed;
- exact static/behavioral closure output: `backup-write-integrity-test: PASS progressive-partials=completed stalled-write=rejected database-dump=guarded archives=finalization-guarded`.
DreamHost capacity/ownership/filesystem behavior remains a separate Gate 9 fact.

## Gate 3 — Permission, exposure, and error model
Status: PASS
- All 24 abilities denied anonymous and allowed administrator.
- Exact isolation across 10 intended WordPress capabilities.
- Candidate `show_in_rest=false` abilities absent from REST collection and direct execution probes failed closed.
- Sensitive-marker error-output regression passed for plugin API, plugin updater rollback, and database restore after candidate repair.

## Gate 4 — Lifecycle operations
Status: PASS
- Plugin activate/deactivate.
- Plugin auto-update enable/disable.
- Backup-protected plugin deletion/restore.
- Backup-protected theme deletion/restore.
- Candidate-controlled switch to the fixture theme and return to original theme.
- Theme auto-update enable/disable persistence and original-state restoration.

## Gate 5 — Update engine
Status: PASS
Evidence includes full run `34166835095`.
- Real WordPress.org plugin update with rollback backup passed.
- Twenty Twenty-One 1.8 -> 2.9 theme update and exact rollback passed.
- Forced plugin post-update validation failure and exact automatic rollback passed.
- Deterministic local v1 fixture returns `cmsa_plugin_no_update` when no update is offered.
- Synthetic local v2 package updates v1→v2 and preserves activation.
- Malformed synthetic package fails closed and restores exact active v1 files from the verified rollback backup.

## Gate 6 — Core update/rollback
Status: PASS
- Verified core+DB rollback snapshot.
- Exact independent core/database restore.
- Candidate-controlled real `Core_Upgrader` 7.0 -> 7.1 transaction.
- Post-update bootstrap and preservation of config/content/database/plugin state.
- Deliberate post-update validation mismatch and exact automatic rollback.
- Schema-aware numeric DB serialization repair regression.
- Full regression remained green after database/ZIP storage-integrity repairs.

## Gate 7 — Package-network/privacy surface
Status: PASS for exercised WordPress.org package operations
- Five disposable private-marker classes scanned in raw, URL-encoded, and base64 forms.
- 12 requests observed only to WordPress.org API/download hosts in the privacy capture gate.
- No seeded private marker appeared in captured request material.

## Gate 8 — Multisite cache execution
Status: PASS
Latest evidence: CMS Admin Multisite Lab run `34166835071`, candidate commit `286a8d15d905b60380b5173cb084fabfd7c3ce43`.
- Real WordPress 7.1 multisite network installed.
- Candidate network activation verified.
- Multisite cache branch remained green after the storage-integrity repairs.

## Gate 9 — Chattanooga/DreamHost read-only preflight
Status: NEXT / NOT YET COMPLETE
No mutation at this gate.
- WordPress/PHP exact versions.
- Abilities API availability.
- ZipArchive.
- WordPress filesystem method.
- plugin/theme/wp-content writability.
- preferred backup directory parent writability.
- disk-space feasibility for backup sizes.
- WP Super Cache functions.
- file-modification policy constants.
- existing MCP transport's observable discovery behavior.
Only facts actually exposed by the current connected WordPress/DreamHost surface may be marked verified; unavailable server-level facts remain UNKNOWN.

## Gate 10 — Chattanooga installation/runtime
Status: NOT AUTHORIZED BY WORKBENCH TESTING ALONE
Requires separate production authorization and rollback transaction.
- install exact validated candidate package;
- activate;
- discover all expected abilities through actual AI transport;
- run health inventory;
- create and verify local backup;
- perform only separately authorized live mutations.

## Rule for new coding
Every candidate change must identify the failing/desired test first when practical, modify only `candidate/` when product source must change, run the complete applicable gate set, and update the capability matrix. A candidate is never promoted because it merely compiles.
