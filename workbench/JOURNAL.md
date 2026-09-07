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
