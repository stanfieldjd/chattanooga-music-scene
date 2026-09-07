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
- plugin component archive and exact-byte restore;
- database sentinel restore;
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
- all 24 abilities allowed for an administrator;
- all 24 abilities isolated across exactly 10 intended WordPress capabilities with no cross-capability grants;
- six WordPress Abilities REST routes enumerated;
- `show_in_rest=false` candidate abilities absent from the REST collection;
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
- theme auto-update policy add/remove verification if not covered by a later combined lifecycle probe.

## Gate 5 — Update engine

Status: PARTIAL PASS

Passed:
- actual WordPress.org plugin update through candidate with verified pre-update rollback backup;
- actual Twenty Twenty-One 1.8 -> 2.9 theme update through candidate;
- post-update version verification;
- exact theme rollback to 1.8 with `style.css` SHA-256 fidelity.

Still required:
- deterministic local v1/v2 update fixtures independent of current WordPress.org versions;
- forced validation/update failure with proof of automatic rollback;
- no-update-available behavior;
- malformed package behavior.

## Gate 6 — Core update/rollback

Status: PARTIAL PASS

Passed:
- create core + DB rollback snapshot and verify checksums;
- deliberately mutate `wp-includes/version.php`, `readme.html`, and a database sentinel;
- restore verified core snapshot;
- verify exact file hashes and database value returned;
- preserve `wp-content` and `wp-config.php` outside the core archive/restore set by construction.

Still required:
- controlled `Core_Upgrader` transaction using a disposable WordPress version pair;
- post-update WordPress bootstrap/version verification;
- automatic rollback behavior when a core update validation fails.

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

Every candidate change must identify the failing/desired test first when practical, modify only `candidate/` when product source must change, run the complete applicable gate set, and update the capability matrix. A candidate is never promoted because it merely compiles.
