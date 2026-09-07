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

Status: PASS in reference runtime

- Install and activate candidate in disposable WordPress 7.1.
- Verify native Abilities API functions.
- Execute Abilities lifecycle and verify category/all 24 abilities through registry getters.

## Gate 2 — Backup primitives

Status: PARTIAL PASS

Passed:
- database backup creation and SHA-256 verification;
- plugin component archive and exact-byte restore;
- database sentinel restore;
- exact numeric primary-key identity across database restore;
- theme fixture deletion/restore fidelity;
- WordPress core + database snapshot creation and verification;
- deliberate core/root/database mutation followed by exact restore.

Still required:
- incomplete/corrupt backup rejection tests;
- disk-space and partial-write failure handling.

## Gate 3 — Permission and exposure model

Status: PARTIAL PASS

Passed in real WordPress 7.1:
- all 24 abilities denied anonymously;
- all 24 abilities allowed for administrator;
- all 24 abilities isolated across exactly 10 intended capabilities with no cross-capability grants;
- six WordPress Abilities REST routes enumerated;
- `show_in_rest=false` candidate abilities absent from REST collection;
- direct GET/POST probes did not expose or execute `chattanooga-cms-admin/get-health`.

Still required:
- dedicated error-output secret/credential redaction regression tests.

## Gate 4 — Lifecycle operations

Status: PARTIAL PASS

Passed with disposable fixtures:
- plugin activate/deactivate;
- plugin auto-update policy add/remove;
- backup-protected plugin deletion and restore;
- backup-protected theme deletion and restore.

Still required:
- explicit switch-theme transaction and return-to-original-theme verification;
- theme auto-update policy add/remove verification.

## Gate 5 — Update engine

Status: PARTIAL PASS

Passed:
- actual WordPress.org plugin update with verified pre-update rollback backup;
- actual Twenty Twenty-One 1.8 -> 2.9 theme update;
- post-update version verification;
- exact theme rollback to 1.8 with `style.css` SHA-256 fidelity;
- forced plugin post-update validation mismatch with automatic exact rollback.

Still required:
- deterministic local v1/v2 update fixtures independent of current WordPress.org versions;
- no-update-available behavior;
- malformed package behavior.

## Gate 6 — Core update/rollback

Status: PASS in reference runtime

Passed:
- create core + DB rollback snapshot and verify checksums;
- deliberately mutate `wp-includes/version.php`, `readme.html`, and database sentinel;
- restore verified core snapshot with exact files/database;
- preserve `wp-content` and `wp-config.php` outside core archive/restore set;
- move only disposable runtime to WordPress 7.0 after 7.1 regression suite;
- run `CMSA_Updates::update_core()` through real `Core_Upgrader` transaction;
- verify candidate update 7.0 -> 7.1, valid rollback snapshot, bootstrap, unchanged config/content/database sentinel, and plugin activation;
- deliberately force post-update version validation failure after a real 7.1 package install;
- execute `rollback_core_error()` and verify exact WordPress 7.0 core restore, database restore, unchanged `wp-config.php`, unchanged `wp-content`, and candidate plugin active.

Defect found and repaired during this gate:
- numeric database columns were initially byte-hex serialized, which could overflow integer primary keys on restore after update-created rows;
- serializer now uses schema-aware validated numeric literals;
- exact numeric `option_id` restoration and full forced-core rollback passed in run `34162917097`.

## Gate 7 — Package-network/privacy surface

Status: PENDING

- Instrument WordPress HTTP request arguments for `plugins_api()` and `themes_api()` and package-download calls reached through candidate operations.
- Record only safe destination/method/field-name evidence; do not persist private request values in artifacts.
- Seed unique private markers representing member email, private content, order-like data, credential material, and backup contents.
- Prove none of those private markers enter package lookup/download requests.
- Confirm destinations are expected WordPress-owned package/API endpoints for the exercised operations.
- Keep direct candidate vendor telemetry prohibited.

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
