# Task: Chattanooga CMS Admin Runtime Integration

Status: READY_FOR_RUNTIME_GATE

## Objective

Promote the validated Chattanooga CMS Admin source into an authorized WordPress test/deployment path, verify its native Abilities API registration on Chattanooga Music Scene, and prove backup/health/rollback behavior before using it for production maintenance.

## Target set

- Source: `site-plugins/chattanooga-cms-admin`
- Source branch: `feature/chattanooga-cms-admin`
- WordPress plugin installation for Chattanooga CMS Admin only during the deployment phase.

## Exclusion set

- Existing Chattanooga Music Scene content and member records are not mutation targets for installation testing.
- The existing Weekend Feature plugin is not part of this task.
- Existing MCP transport is not removed as an incidental operation.
- No production plugin/theme/core update is bundled into initial runtime validation.

## Evidence

- Verified source checkpoint: `0b34773ebc8073cb657477770b34cabc280f5892`.
- PHP 7.4 syntax workflow passed at GitHub Actions run `34115195808`.
- Source has not been merged to `main` and has not been installed on Chattanooga Music Scene.

## Mutation set

1. Establish an authorized installation path for the exact validated plugin package/source.
2. Install and activate Chattanooga CMS Admin.
3. Discover registered `cms-admin/*` abilities.
4. Run read-only health/update inventory.
5. Create a local backup and verify checksums.
6. Exercise a non-destructive control such as cache clear only if appropriate to the verified live state.
7. Test rollback capability on a deliberately controlled component before using maintenance abilities on production components.

## Risk set

- Plugin activation failure.
- WordPress Abilities API signature/registration mismatch not detectable by syntax lint.
- Filesystem permission mismatch on DreamHost.
- Backup directory or ZipArchive unavailable.
- Update/rollback engine behavior differing from source assumptions.

## Rollback point

- Source rollback: branch parent `d3167ab8d084523c63d22004955d781962e41623` plus task branch history.
- Runtime rollback: deactivate/remove only the Chattanooga CMS Admin installation if activation/runtime validation fails, provided removal is specifically authorized at that point.

## Acceptance tests

- [ ] Exact plugin version `0.1.0` is installed and active.
- [ ] Expected `cms-admin/*` abilities are discoverable through the active AI transport.
- [ ] Health-check ability returns current WordPress/runtime state without fatal error.
- [ ] Local backup creation succeeds.
- [ ] Backup checksum verification passes.
- [ ] No member/content mutation occurs during installation validation.
- [ ] Controlled rollback behavior is execution-verified before production update abilities are used.

## Source position

- Repository: `stanfieldjd/chattanooga-music-scene`
- Branch: `feature/chattanooga-cms-admin`
- Observed commit: `0b34773ebc8073cb657477770b34cabc280f5892`

## Production state

NOT_DEPLOYED

## Result journal

- 2026-09-07: Source built and PHP 7.4 syntax validation passed. Runtime gate remains open.
