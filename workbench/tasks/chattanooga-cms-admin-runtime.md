# Task: Chattanooga CMS Admin Runtime Integration

Status: WORKBENCH_LAB_PENDING_CI

## Objective

Promote the validated Chattanooga CMS Admin source through an isolated engineering lab, determine which WordPress/DreamHost capabilities are actually available, then establish an authorized WordPress test/deployment path and prove backup/health/rollback behavior before production maintenance is permitted.

## Target set

- Source: `site-plugins/chattanooga-cms-admin`
- Source branch: `feature/chattanooga-cms-admin`
- Workbench lab: `workbench/labs/chattanooga-cms-admin`
- WordPress plugin installation for Chattanooga CMS Admin only during a separately authorized deployment phase.

## Exclusion set

- `main` is not a mutation target for lab work.
- `feature/chattanooga-cms-admin` is not modified by lab experiments.
- Existing Chattanooga Music Scene content and member records are not mutation targets for lab/runtime preflight testing.
- The existing Weekend Feature plugin is not part of this task.
- Existing MCP transport is not removed as an incidental operation.
- No production plugin/theme/core update is bundled into initial runtime validation.

## Evidence

- Verified source checkpoint: `0b34773ebc8073cb657477770b34cabc280f5892`.
- PHP 7.4 source syntax workflow passed at GitHub Actions run `34115195808`.
- Workbench lab mirrors the verified source using the exact Git blobs.
- Source has not been merged to `main` and has not been installed on Chattanooga Music Scene.

## Workbench lab gates

1. Verify mirrored Git blob identities.
2. PHP lint on 7.4 and 8.2.
3. Reject arbitrary shell/PHP execution primitives and direct REST route registration.
4. Verify expected Abilities category, names, schemas, annotations, and permission callbacks under WordPress stubs.
5. Preserve a capability matrix where runtime-dependent functions remain UNKNOWN/CONDITIONAL.
6. Prepare a read-only WordPress runtime probe for Abilities API, upgrader classes, filesystem, ZipArchive, backup paths, cache APIs, database availability, and file-modification policy constants.

## Runtime mutation set — future authorized phase

1. Establish an authorized installation path for the exact validated plugin package/source.
2. Install and activate Chattanooga CMS Admin.
3. Discover registered `chattanooga-cms-admin/*` abilities.
4. Run read-only health/update inventory and runtime capability probe.
5. Create a local backup and verify checksums.
6. Exercise a non-destructive control such as cache clear only if appropriate to the verified live state.
7. Test rollback capability on a deliberately controlled component before production maintenance abilities are used.

## Risk set

- Static/stub tests may pass while WordPress 7.1 rejects metadata or runtime behavior.
- WordPress Abilities API signature/registration mismatch may only appear in a real runtime.
- Filesystem permission behavior may differ on DreamHost.
- Backup directory or ZipArchive may be unavailable.
- WordPress upgrader/rollback behavior may differ from source assumptions.
- Network-dependent WordPress.org package APIs may be available but must not transmit member data.

## Rollback point

- Workbench rollback: `workbench/mars` parent commit before the lab transaction.
- Source rollback: feature-branch history; the lab uses copied Git blobs and does not mutate the feature branch.
- Runtime rollback: separately established before any installation or live mutation.

## Acceptance tests

- [ ] Workbench lab CI passes on PHP 7.4.
- [ ] Workbench lab CI passes on PHP 8.2.
- [ ] Source mirror blob verification passes.
- [ ] Stub registration returns exactly the expected 24 abilities.
- [ ] Architecture/security scan passes.
- [ ] Runtime capability probe executes successfully inside WordPress 7.1.
- [ ] Exact plugin version `0.1.0` is installed and active in the authorized runtime phase.
- [ ] Expected abilities are discoverable through the active AI transport.
- [ ] Local backup creation succeeds and checksum verification passes.
- [ ] No unintended member/content mutation occurs during validation.
- [ ] Controlled rollback behavior is execution-verified before production update abilities are used.

## Source position

- Repository: `stanfieldjd/chattanooga-music-scene`
- Branch: `feature/chattanooga-cms-admin`
- Observed commit: `0b34773ebc8073cb657477770b34cabc280f5892`

## Production state

NOT_DEPLOYED

## Result journal

- 2026-09-07: Source built and PHP 7.4 syntax validation passed.
- 2026-09-07: Workbench lab staged with exact source mirror, CI tests, security/architecture checks, and real-runtime capability probe. CI result pending.
