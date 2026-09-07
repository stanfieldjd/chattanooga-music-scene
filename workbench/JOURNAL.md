# Workbench Journal

Append-only record of material workbench state changes. Do not rewrite prior entries to make later work appear cleaner; append corrections or superseding entries.

## 2026-09-07 — Workbench initialization

- Created dedicated branch `workbench/mars` from verified `main` commit `d3167ab8d084523c63d22004955d781962e41623`.
- Established persistent status, task queue, protocol, machine-readable state, task template, and current CMS Admin runtime-integration task.
- Kept the workbench separate from `feature/chattanooga-cms-admin` so unfinished plugin source is not implicitly promoted or merged.
- Added an integrity check to validate required workbench files, JSON state, and shell syntax.

## 2026-09-07 — CMS Admin laboratory

- Established `workbench/labs/chattanooga-cms-admin` for isolated plugin engineering and testing.
- Mirrored the verified CMS Admin source checkpoint `0b34773ebc8073cb657477770b34cabc280f5892` by reusing the exact Git blobs; the feature branch itself was not modified.
- Added source-manifest verification, PHP syntax testing, architecture/security scanning, and stubbed WordPress Abilities registration tests.
- Added a read-only WordPress runtime capability probe for Abilities API, updater classes, filesystem support, ZipArchive, backup-path writability, cache APIs, and policy constants.
- Added a capability matrix that keeps runtime-dependent features UNKNOWN/CONDITIONAL until execution evidence exists.
- Added CI matrix targets for PHP 7.4 and PHP 8.2.

## 2026-09-07 — Baseline/candidate split and real WordPress registry

- Preserved `plugin/` as the immutable exact source checkpoint.
- Added `candidate/` as the mutable coding surface so experiments cannot destroy the source-evidence baseline.
- Updated lint, security, source-shape, and stub-registration tests to run against the candidate.
- Added a disposable WordPress 7.1 + MySQL runtime job.
- Confirmed WordPress 7.1 exposes the native Abilities API and WordPress upgrader/filesystem classes required by the current architecture.
- Confirmed WP-CLI bootstrap did not automatically fire the Abilities lifecycle in the initial read-only probe; added an explicit disposable lifecycle/registry test rather than treating API presence as registration.
- Verified all expected 24 CMS Admin abilities through WordPress's real ability registry.

## 2026-09-07 — Backup and rollback execution evidence

- Added a disposable fixture plugin used only inside the workbench runtime.
- Created a real database backup and verified its recorded SHA-256 checksum.
- Created and verified a fixture-plugin rollback archive.
- Deliberately mutated the fixture plugin state in the disposable runtime.
- Restored the component from the rollback archive and verified the original fixture bytes returned exactly.
- Recorded reference runtime evidence at CMS Admin Workbench Lab run `34118396822`, test commit `76f7f8a05f07014b13712b011cfec9fb69d0b666`.
- Kept DreamHost/Chattanooga production state, actual MCP discovery, database/core restore, update transactions, and broader site-administration abilities explicitly unresolved.

## 2026-09-07 — Theme update rollback and core rollback fidelity

- Advanced `workbench/mars` to test commit `32f1db5b36f1c8c5bfb94bb725bb474adaabdb81` without modifying `main` or `feature/chattanooga-cms-admin`.
- Added a disposable Twenty Twenty-One 1.8 fixture, updated it through `CMSA_Updates::update_theme()`, verified the rollback archive, restored it, and verified both the original 1.8 version and exact `style.css` SHA-256 returned.
- Added a WordPress core rollback probe that created and verified a core+database snapshot, deliberately mutated `wp-includes/version.php`, `readme.html`, and a database sentinel, then restored the snapshot and verified both file hashes and the database value returned exactly.
- CMS Admin Workbench Lab run `34160856274` passed PHP 7.4, PHP 8.2, and the complete disposable WordPress 7.1 runtime job, including the new theme-update rollback and core-backup/restore steps.
- Core upgrader execution remains untested. DreamHost filesystem behavior and actual Chattanooga MCP discovery remain unverified production gates.

## 2026-09-07 — Ability permission matrix and REST isolation

- Added real WordPress capability isolation testing at commit `39d699cbf6415427a0c1a5ae29eed327a43e6a78`.
- CMS Admin Workbench Lab run `34161223016` denied all 24 abilities anonymously, allowed all 24 to an administrator, and isolated them across exactly 10 required WordPress capabilities with no cross-capability grants.
- The runtime enumerated six WordPress Abilities REST routes: namespace, categories, category detail, ability list, ability detail, and ability run.
- Candidate abilities marked `show_in_rest=false` did not appear in the REST ability collection. Direct GET/POST probes for `chattanooga-cms-admin/get-health` did not expose or execute the candidate ability.
- The complete regression suite remained green after the permission/REST gate: PHP 7.4, PHP 8.2, plugin lifecycle, theme lifecycle, database restore, WordPress.org plugin update, theme update rollback, core backup/restore, and health/cache all passed.
- Runtime capability artifact id `10032670494` was uploaded with ZIP SHA-256 `8fdbe0d203ffc3980de8a115a0c1f7f6a1bee450942586684048044cc9c7ae33`.

## 2026-09-07 — Forced update validation rollback

- Added a deliberate post-update validation mismatch probe at commit `a34d8e260778762d434cf91e10fde7932f704df2`.
- The probe reset Classic Editor to 1.6, preserved its exact main-file SHA-256, retained the real WordPress.org update package, and changed only the advertised target version seen by the candidate to force post-install validation failure.
- `CMSA_Updates::update_plugin()` returned the expected validation error and reported automatic rollback success rather than accepting the mismatched post-update state.
- The restored Classic Editor version returned to 1.6 and the main-file SHA-256 exactly matched the pre-update file.
- CMS Admin Workbench Lab run `34161501638` passed the forced rollback step and the complete downstream regression suite.
- Runtime capability artifact id `10032758991` was uploaded with SHA-256 `545293ebd672297f218b1e4ced92ed4f352b59e42253c2af87ded792f6eb5bcf`.

## 2026-09-07 — Core updater transaction

- Added an actual `Core_Upgrader` transaction probe at commit `37839c3a95b21394dab139b31a5d22d12e41676d`.
- The disposable runtime was moved to WordPress 7.0 only after the earlier WordPress 7.1 regression gates completed, then the candidate itself was asked to perform the currently offered core update.
- `CMSA_Updates::update_core()` created and verified a pre-update core+database rollback snapshot and upgraded WordPress from 7.0 to 7.1.
- Post-update verification confirmed the on-disk version was 7.1, `wp-config.php` was byte-identical, a `wp-content` sentinel was byte-identical, a database sentinel was unchanged, the site still bootstrapped, and Chattanooga CMS Admin remained active.
- CMS Admin Workbench Lab run `34161768634` passed PHP 7.4, PHP 8.2, the full disposable WordPress regression suite, and the core update transaction.
- Exact transaction output: `core-update-cli: PASS from=7.0 to=7.1 ... config=unchanged wp-content=unchanged database=unchanged plugin=active`.
- Runtime capability artifact id `10032846544` was uploaded with SHA-256 `ac6892ba1fe16d9483608366fd36c50128e843a8f5d6375c101f1422ef7656a7`.
- The core automatic rollback path on a deliberately failed core update remains unverified and is the next core-specific fault-injection gate.
