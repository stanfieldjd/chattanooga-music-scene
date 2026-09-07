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

- Install candidate in disposable WordPress 7.1.
- Activate candidate.
- Verify native Abilities API functions.
- Execute Abilities lifecycle in the disposable process.
- Verify category and all 24 abilities through WordPress registry getters.

## Gate 2 — Backup primitives

Status: PARTIAL PASS

Passed:
- database backup creation;
- SHA-256 backup verification;
- plugin component archive;
- deliberate fixture mutation;
- component restore with exact-byte verification.

Still required:
- database sentinel restore;
- theme fixture backup/restore;
- WordPress core snapshot creation and verification;
- controlled core-file restore in disposable runtime;
- incomplete/corrupt backup rejection tests;
- disk-space and partial-write failure handling.

## Gate 3 — Permission and exposure model

Status: PENDING

- Test administrator permission success.
- Test lower-privilege users against each capability class.
- Confirm destructive abilities require their intended WordPress capabilities.
- Enumerate WordPress Abilities REST routes after registration and prove `show_in_rest=false` prevents unintended candidate execution through REST.
- Confirm error outputs do not disclose credentials/secrets.

## Gate 4 — Lifecycle operations

Status: API PRESENCE VERIFIED; EXECUTION PENDING

Using disposable fixture plugins/themes only:
- activate/deactivate fixture;
- auto-update policy add/remove;
- delete fixture only after verified rollback archive;
- restore deleted fixture;
- switch to fixture theme and return to original test theme;
- delete fixture theme after rollback proof.

## Gate 5 — Update engine

Status: PENDING

- Build local v1/v2 fixture plugin packages.
- Seed or intercept WordPress update metadata deterministically.
- Update v1 -> v2 through candidate update engine.
- Verify pre-update backup.
- Verify post-update version.
- Force validation failure and prove automatic rollback.
- Repeat for theme update.
- Test no-update-available behavior and malformed package behavior.

## Gate 6 — Core update/rollback

Status: API PRESENCE VERIFIED; EXECUTION PENDING

- Use disposable WordPress version pair only.
- Create core + DB rollback snapshot and verify checksums.
- Exercise controlled core upgrader path.
- Verify version and site bootstrap.
- Exercise controlled rollback to original snapshot.
- Confirm `wp-content` and `wp-config.php` remain outside unintended core replacement.

## Gate 7 — Package-network/privacy surface

Status: PENDING

- Instrument WordPress HTTP request arguments for `plugins_api()` and `themes_api()`.
- Record destination, headers, query/body fields, and user agent.
- Prove no member records, user emails, orders, private content, credentials, or backup contents enter package lookup requests.
- Keep direct vendor telemetry prohibited.

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

Every candidate change must identify the failing/desired test first when practical, modify only `candidate/`, run the complete applicable gate set, and update the capability matrix. A candidate is never promoted because it merely compiles.
