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
Status: PARTIAL PASS / ACTIVE
Passed:
- database backup + SHA-256 verification;
- plugin component exact-byte restore;
- database sentinel restore and numeric primary-key identity;
- theme deletion/restore fidelity;
- core + database snapshot and exact deliberate-mutation restore.
Active now:
- corrupted component archive rejection without target mutation;
- missing component archive rejection;
- corrupted database snapshot rejection without database mutation.
Still required after corruption/missing-file gate:
- disk-space and partial-write/storage-failure handling.

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
Status: PARTIAL PASS
Passed:
- real WordPress.org plugin update with rollback backup;
- Twenty Twenty-One 1.8 -> 2.9 theme update and exact rollback;
- forced plugin post-update validation failure and exact automatic rollback.
Still required:
- deterministic local v1/v2 fixtures independent of WordPress.org current versions;
- no-update-available behavior;
- malformed-package behavior.

## Gate 6 — Core update/rollback
Status: PASS
- Verified core+DB rollback snapshot.
- Exact independent core/database restore.
- Candidate-controlled real `Core_Upgrader` 7.0 -> 7.1 transaction.
- Post-update bootstrap and preservation of config/content/database/plugin state.
- Deliberate post-update validation mismatch and exact automatic rollback.
- Schema-aware numeric DB serialization repair regression.

## Gate 7 — Package-network/privacy surface
Status: PASS for exercised WordPress.org package operations
- Five disposable private-marker classes scanned in raw, URL-encoded, and base64 forms.
- Run `34163308270` captured 12 requests; only `api.wordpress.org` and `downloads.wordpress.org` were observed.
- No seeded private marker appeared in any captured request.

## Gate 8 — Multisite cache execution
Status: PASS
Evidence: CMS Admin Multisite Lab run `34164758622`, commit `a81c962c007163698de13b2d9b4f8bfe7bfcac7c`.
- Real WordPress 7.1 multisite network installed.
- Candidate network activation verified.
- Exact output: `health-cache-cli: PASS methods=object-cache,wordpress-blog-cache`.
- No mocked `is_multisite()` behavior was used.

## Gate 9 — Chattanooga/DreamHost read-only preflight
Status: NOT RUN
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
- existing MCP transport's ability discovery behavior.

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
