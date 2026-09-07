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

## Gate 2 — Backup primitives
Status: PARTIAL PASS
Passed:
- database backup + SHA-256 verification;
- plugin component exact-byte restore;
- database sentinel restore and numeric primary-key identity;
- theme deletion/restore fidelity;
- core + database snapshot and exact deliberate-mutation restore.
Still required:
- incomplete/corrupt backup rejection;
- disk-space and partial-write/storage-failure handling.

## Gate 3 — Permission, exposure, and error model
Status: PARTIAL PASS
Passed:
- all 24 abilities denied anonymous and allowed administrator;
- exact isolation across 10 intended WordPress capabilities;
- six WordPress Abilities REST routes enumerated;
- candidate `show_in_rest=false` abilities absent from REST collection and direct execution probes failed closed.
Still required:
- dedicated error-output secret/credential redaction regression test, including upstream error detail/data.

## Gate 4 — Lifecycle operations
Status: PARTIAL PASS
Passed:
- plugin activate/deactivate;
- plugin auto-update enable/disable;
- backup-protected plugin deletion/restore;
- backup-protected theme deletion/restore.
Still required:
- explicit switch-theme and return-to-original-theme transaction;
- theme auto-update enable/disable verification.

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
Passed:
- verified core+DB rollback snapshot;
- exact independent core/database restore;
- candidate-controlled real `Core_Upgrader` 7.0 -> 7.1 transaction;
- post-update bootstrap and preservation of config/content/database/plugin state;
- deliberate post-update validation mismatch;
- `rollback_core_error()` exact WordPress 7.0 core/database restore with config/content/plugin state preserved;
- schema-aware numeric DB serialization repair after the first fault-injection run exposed integer overflow/coercion.

## Gate 7 — Package-network/privacy surface
Status: PASS for exercised WordPress.org package operations
- Seeded five disposable private-marker classes: member email, private content, order-like data, credential-like data, and backup content.
- Captured WordPress HTTP requests during candidate plugin/theme package operations.
- Scanned URL and arguments for raw, URL-encoded, and base64 marker forms.
- Run `34163308270` captured 12 requests; only `api.wordpress.org` and `downloads.wordpress.org` were observed.
- No seeded private marker appeared in any captured request.
- Full downstream regression suite remained green.
Caveat: this does not establish privacy behavior for unrelated plugins, WooCommerce-specific operations, or live Chattanooga traffic.

## Gate 8 — Chattanooga/DreamHost read-only preflight
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

## Gate 9 — Chattanooga installation/runtime
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
