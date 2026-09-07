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
