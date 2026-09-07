# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_STORAGE_FAILURE_VERIFIED — DETERMINISTIC_UPDATE + PARTIAL_WRITE + DREAMHOST/MCP PENDING

## Objective

Develop Chattanooga CMS Admin through an isolated engineering lab, determine which WordPress capabilities are actually usable, and establish evidence-backed gates before production maintenance or broader site administration is permitted.

## Target set

- Source: `site-plugins/chattanooga-cms-admin`
- Source branch: `feature/chattanooga-cms-admin`
- Workbench lab: `workbench/labs/chattanooga-cms-admin`
- Immutable baseline: `workbench/labs/chattanooga-cms-admin/plugin`
- Mutable coding candidate: `workbench/labs/chattanooga-cms-admin/candidate`
- WordPress plugin installation for Chattanooga CMS Admin only during a separately authorized production phase.

## Exclusion set

- `main` is not a mutation target for lab work.
- `feature/chattanooga-cms-admin` is not modified by lab experiments.
- Existing Chattanooga Music Scene content and member records are not mutation targets for lab/runtime preflight testing.
- The existing Weekend Feature plugin is not part of this task.
- Existing MCP transport is not removed as an incidental operation.
- No production plugin/theme/core update is bundled into workbench validation.

## Evidence

- Verified source checkpoint: `0b34773ebc8073cb657477770b34cabc280f5892`.
- Source PHP 7.4 syntax workflow passed at GitHub Actions run `34115195808`.
- Immutable workbench baseline reuses the exact source Git blobs.
- Latest complete single-site/full-regression execution: CMS Admin Workbench Lab run `34165355234`, test commit `7b6e25b4a1103f260fdf36237482d9e7c7787934`.
- Runtime capability artifact id `10033988433`, SHA-256 `bea034a0a86a3de8ed85208cf61a059a996ae64ff45eaae3cfefd640479d28df`.
- Real WordPress 7.1 multisite cache execution: run `34164758622`; exact cache result `object-cache,wordpress-blog-cache`.
- Corrupt/missing rollback material gate passed: `corrupt-backup-rejection-cli: PASS component=checksum-rejected missing=fail-closed database=checksum-rejected target=unchanged`.
- Unavailable-storage fault gate passed in run `34165355234`: `storage-failure-cli: PASS all-backup-paths=unwritable backup=not-created`.
- The complete downstream redaction, package privacy, plugin/theme update rollback, core backup/restore, normal core update, and forced core rollback chain stayed green after storage failure injection.
- Source has not been merged to `main` and has not been installed on Chattanooga Music Scene.

## Workbench gates

Completed:

- [x] Verify immutable source Git blob identities.
- [x] PHP lint on 7.4 and 8.2.
- [x] Reject arbitrary shell/PHP execution primitives and direct custom REST route registration.
- [x] Verify expected 24 abilities under WordPress stubs and real WordPress registry.
- [x] Verify all 24 ability permission callbacks against anonymous, administrator, and capability-isolated users.
- [x] Verify candidate REST isolation.
- [x] Verify database backup/checksum, database restore, and numeric primary-key identity.
- [x] Verify controlled plugin and theme component rollback.
- [x] Verify WordPress.org plugin install/activate/deactivate/update transaction.
- [x] Verify theme update transaction and exact rollback fidelity.
- [x] Verify core+database snapshot and exact restore fidelity.
- [x] Force plugin validation failure and prove automatic exact rollback.
- [x] Execute candidate-controlled WordPress core update 7.0 -> 7.1 and preserve configuration/content/database/plugin state.
- [x] Force core post-update validation failure and prove automatic exact core/database rollback.
- [x] Capture candidate-triggered WordPress package requests and prove seeded private markers are absent.
- [x] Fault-inject upstream errors and prove public error outputs redact seeded sensitive markers while preserving rollback state.
- [x] Verify theme switch/return and theme auto-update enable/disable persistence with original state restoration.
- [x] Execute the cache branch in a real WordPress 7.1 multisite installation with network activation and verify `wordpress-blog-cache`.
- [x] Reject corrupted/missing rollback material before restore with target state unchanged.
- [x] Make every configured backup path non-writable and verify backup creation fails closed with no backup created.

Pending workbench/runtime tests:

- [ ] Deterministic local plugin update fixture: no-update behavior, v1→v2 success, malformed-package automatic rollback.
- [ ] Partial-write/disk-space backup failure handling.
- [ ] DreamHost/Chattanooga read-only capability probe.
- [ ] Actual Chattanooga MCP discovery of the registered abilities.
- [ ] Broader content/member/event/commerce/site-specific typed abilities from `ROADMAP.md`.

## Runtime mutation set — future separately authorized phase

1. Establish an authorized installation path for the exact validated candidate/package.
2. Create/verify a production rollback point.
3. Install and activate Chattanooga CMS Admin.
4. Discover registered `chattanooga-cms-admin/*` abilities through the actual AI transport.
5. Run read-only health/runtime inventory.
6. Create and verify a local backup.
7. Perform only separately authorized live mutations.

## Risk set

- Reference GitHub filesystem behavior does not prove DreamHost behavior.
- WP-CLI registration lifecycle behavior differs from normal web bootstrap and is explicitly invoked in disposable tests where required.
- Actual MCP transport may expose/filter metadata differently than the WordPress registry.
- Backup storage capacity/permissions may differ on DreamHost.
- Package privacy evidence covers the exercised WordPress.org operations only; WooCommerce-specific or unrelated plugin traffic remains separate.

## Rollback point

- Workbench history remains recoverable through Git commits.
- Immutable baseline preserves exact source checkpoint `0b34773e...`.
- Candidate experiments are isolated from source and production.
- Production rollback must be separately established before deployment.

## Production state

NOT_DEPLOYED

## Result journal

- 2026-09-07: Source built and PHP 7.4 syntax validation passed.
- 2026-09-07: Workbench lab established with immutable baseline, mutable candidate, static/stub tests, and capability matrix.
- 2026-09-07: Real WordPress 7.1 registration, permissions, REST isolation, backup/restore, lifecycle, update, and rollback gates advanced through repeated full regression runs.
- 2026-09-07: Numeric database serialization defect found by forced-core rollback, repaired at the serializer, and exact numeric primary-key restore fidelity proven.
- 2026-09-07: WordPress package-network privacy gate passed with 12 captured requests limited to WordPress.org API/download hosts and seeded private markers absent.
- 2026-09-07: Error-output redaction passed after fault injection exposed and repaired raw plugin-API and database diagnostics.
- 2026-09-07: Expanded theme lifecycle and real multisite cache branches passed.
- 2026-09-07: Corrupt/missing backup material and fully unavailable backup storage both failed closed in real WordPress reference runs.
