# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_CORRUPT_BACKUP_REJECTION_VERIFIED — STORAGE_FAILURE + DETERMINISTIC_UPDATE + DREAMHOST/MCP PENDING

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
- Latest complete single-site/full-regression execution: CMS Admin Workbench Lab run `34165007012`, test commit `a42e0be1e4fb52c48aee0617078a0f43d53f7ae3`.
- Runtime capability artifact id `10033890050`, SHA-256 `d47cb1d77e97f8cddda5825f924dfd1f9b50e14698a9de5d87dc69e55c50abf5`.
- Real WordPress 7.1 multisite cache execution: CMS Admin Multisite Lab run `34164758622` on commit `a81c962c007163698de13b2d9b4f8bfe7bfcac7c`.
- Exact multisite evidence: `multisite-bootstrap: PASS`, `multisite-network-activation: PASS`, and `health-cache-cli: PASS methods=object-cache,wordpress-blog-cache`.
- Corrupt/missing rollback material gate passed in run `34165007012`: corrupted component archive, missing component archive, and corrupted database snapshot all failed closed before restore; target state remained unchanged.
- Exact backup-rejection output: `corrupt-backup-rejection-cli: PASS component=checksum-rejected missing=fail-closed database=checksum-rejected target=unchanged`.
- Candidate passed PHP 7.4 and PHP 8.2 lab gates and the complete downstream package/update/core rollback chain after the new backup-rejection test.
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
- [x] Reject corrupted component archives, missing component archives, and corrupted database snapshots before restore with target state unchanged.

Pending workbench/runtime tests:

- [ ] All configured backup storage paths unavailable/read-only must fail before backup creation.
- [ ] Partial-write/disk-space failure handling.
- [ ] Deterministic local update fixtures, no-update behavior, and malformed-package behavior.
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
- 2026-09-07: Expanded theme lifecycle passed, including switch/return and theme auto-update state restoration.
- 2026-09-07: Real WordPress 7.1 multisite installation/network activation passed and cache clearing returned `object-cache,wordpress-blog-cache`.
- 2026-09-07: Corrupted/missing component and database rollback material was rejected before restore in run `34165007012`; full downstream regression remained green.
