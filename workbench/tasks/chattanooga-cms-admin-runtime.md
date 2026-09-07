# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_PERMISSION_REST_VERIFIED — CORE UPDATE/FAILURE + DREAMHOST/MCP PENDING

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
- Current reference execution: CMS Admin Workbench Lab run `34161223016`, test commit `39d699cbf6415427a0c1a5ae29eed327a43e6a78`.
- Candidate passed PHP 7.4 and PHP 8.2 lab gates.
- Disposable real WordPress 7.1 accepted and activated the candidate.
- All expected 24 abilities were found through WordPress's actual ability registry after lifecycle execution.
- Real permission testing denied all 24 abilities anonymously, allowed all 24 for administrator, and isolated them across 10 intended WordPress capabilities.
- Real WordPress Abilities REST routes were enumerated; candidate abilities marked `show_in_rest=false` did not leak through the collection or direct GET/POST probes.
- Database backup creation, SHA-256 verification, and sentinel restore passed.
- Plugin component backup/restore and lifecycle rollback passed.
- Theme deletion/restore passed.
- Real WordPress.org plugin installation, activation/deactivation, and plugin update transaction passed.
- Real theme update from Twenty Twenty-One 1.8 to 2.9 passed, followed by verified rollback to version 1.8 with exact `style.css` byte fidelity.
- WordPress core+database backup creation and verification passed, followed by deliberate core/root/database mutation and exact restore verification.
- Source has not been merged to `main` and has not been installed on Chattanooga Music Scene.

## Workbench gates

Completed:

- [x] Verify immutable source Git blob identities.
- [x] PHP lint on 7.4 and 8.2.
- [x] Reject arbitrary shell/PHP execution primitives and direct custom REST route registration.
- [x] Verify expected 24 abilities under WordPress stubs.
- [x] Install and activate candidate on disposable WordPress 7.1.
- [x] Verify native Abilities API functions in real WordPress 7.1.
- [x] Verify all 24 abilities through WordPress's real registry.
- [x] Verify all 24 ability permission callbacks against anonymous, administrator, and capability-isolated users.
- [x] Verify candidate REST isolation under WordPress Abilities REST controllers.
- [x] Verify database backup + checksum.
- [x] Verify database sentinel restore.
- [x] Verify controlled plugin component rollback.
- [x] Verify theme component deletion/restore fidelity.
- [x] Verify WordPress.org plugin install/activate/deactivate/update transaction.
- [x] Verify theme update transaction and exact rollback fidelity.
- [x] Verify core+database snapshot creation and exact core/database restore fidelity.

Pending workbench/runtime tests:

- [ ] Core updater transaction with controlled rollback behavior.
- [ ] Forced-failure update rollback path.
- [ ] Error-output secret/credential redaction regression test.
- [ ] WordPress.org package request privacy capture.
- [ ] Explicit switch-theme and theme auto-update lifecycle probes.
- [ ] Multisite cache branch.
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
- WP-CLI does not automatically fire the Abilities registration lifecycle in the tested context; reference tests explicitly invoke the lifecycle in the disposable process.
- Actual MCP transport may expose/filter metadata differently than the WordPress registry.
- Backup storage capacity/permissions may differ on DreamHost.
- Package lookup/update operations can make network requests; payload/privacy capture remains required.
- Core updater execution and forced-failure automatic rollback paths are not yet execution-verified.

## Rollback point

- Workbench history remains fully recoverable through Git commits.
- Immutable baseline preserves exact source checkpoint `0b34773e...`.
- Candidate experiments are isolated from source and production.
- Production rollback must be separately established before deployment.

## Production state

NOT_DEPLOYED

## Result journal

- 2026-09-07: Source built and PHP 7.4 syntax validation passed.
- 2026-09-07: Workbench lab established with immutable baseline, mutable candidate, static/stub tests, and capability matrix.
- 2026-09-07: Disposable WordPress 7.1 proved core Abilities/upgrader/filesystem APIs are present and all 24 candidate abilities register in the real registry.
- 2026-09-07: Database backup/checksum and controlled plugin component rollback passed in the reference runtime.
- 2026-09-07: Database restore, theme rollback, WordPress.org package transactions, theme update rollback, and core+database backup/restore fidelity passed in the reference runtime.
- 2026-09-07: Permission isolation and REST isolation passed for all 24 abilities in CMS Admin Workbench Lab run `34161223016`.
