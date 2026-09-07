# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_REDACTION_AND_THEME_LIFECYCLE_VERIFIED — MULTISITE + BACKUP_FAILURE + DREAMHOST/MCP PENDING

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
- Latest complete reference execution: CMS Admin Workbench Lab run `34164417137`, test commit `f52ee6431fb0111ea5e9499466a2f04fe034aa71`.
- Runtime capability artifact id `10033701473`, SHA-256 `e74eef622406878219d6cbd89d429befbfae2b2fe93d02771b1de013686a2712`.
- Candidate passed PHP 7.4 and PHP 8.2 lab gates.
- Disposable real WordPress 7.1 accepted and activated the candidate.
- All expected 24 abilities were found through WordPress's actual ability registry after lifecycle execution.
- Real permission testing denied all 24 abilities anonymously, allowed all 24 to an administrator, and isolated them across 10 intended WordPress capabilities.
- Candidate abilities marked `show_in_rest=false` did not leak through the WordPress Abilities REST collection or direct GET/POST probes.
- Database backup/checksum, sentinel restore, and exact numeric `option_id` identity passed.
- Plugin component rollback, theme deletion/restore, plugin update, theme update/rollback, core update, core exact restore, forced plugin rollback, and forced core rollback passed.
- Package-network privacy capture exercised candidate WordPress.org package operations with five disposable private-marker classes. Across 12 captured HTTP requests, only `api.wordpress.org` and `downloads.wordpress.org` appeared and no private marker was present in raw, URL-encoded, or base64 form.
- Error-output fault injection first exposed raw plugin-API and MySQL restore diagnostics in run `34163733148`; candidate error boundaries were repaired at commit `a21e834b7c866c696dd34015e223f099c1dd1f3d`, then run `34164107475` passed plugin-API, plugin-updater, and database-restore marker redaction with rollback preserved.
- Expanded theme lifecycle passed in run `34164417137`: delete/restore fidelity, candidate-controlled switch to `cmsa-lab-theme`, auto-update enable/disable persistence, return to the original theme, and restoration of the original auto-update state.
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
- [x] Capture candidate-triggered WordPress package requests and prove seeded member/private-content/order/credential/backup markers are absent; observed hosts were limited to WordPress.org API/download endpoints.
- [x] Fault-inject upstream errors and prove public error outputs redact seeded sensitive markers while preserving rollback state.
- [x] Verify theme switch/return and theme auto-update enable/disable persistence with original state restoration.

Pending workbench/runtime tests:

- [ ] Multisite cache branch in a real disposable WordPress multisite runtime.
- [ ] Incomplete/corrupt backup rejection and storage failure handling.
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
- Multisite cache behavior remains unproven until the dedicated multisite job passes.

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
- 2026-09-07: Real WordPress 7.1 registration, permissions, REST isolation, backup/restore, lifecycle, plugin/theme transactions, and rollback gates advanced through repeated full regression runs.
- 2026-09-07: Numeric database serialization defect found by forced-core rollback, repaired at the serializer, and exact numeric primary-key restore fidelity proven.
- 2026-09-07: Candidate-controlled core update and forced core automatic rollback passed.
- 2026-09-07: WordPress package-network privacy gate passed in run `34163308270`: 12 requests, only `api.wordpress.org` and `downloads.wordpress.org`, five private-marker classes absent.
- 2026-09-07: Error-output redaction passed in run `34164107475` after fault injection exposed and repaired raw plugin-API and database diagnostics.
- 2026-09-07: Expanded theme lifecycle passed in run `34164417137`, including switch/return and theme auto-update state restoration.
