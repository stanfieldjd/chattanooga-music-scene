# Task: cmsa-media-administration-2026-09-08

Status: IN_PROGRESS

## Objective

Expand the Chattanooga CMS Admin candidate with a bounded core WordPress media-administration surface that advances browser-independent site administration without introducing arbitrary command execution or weakening the existing permission, rollback, audit, MCP-only, and conflict-checking architecture.

## Target set

- Branch: `work/cmsa-media-administration`.
- `workbench/labs/chattanooga-cms-admin/candidate/chattanooga-cms-admin.php`.
- `workbench/labs/chattanooga-cms-admin/candidate/includes/class-cmsa-plugin.php`.
- New bounded media implementation/ability classes under `workbench/labs/chattanooga-cms-admin/candidate/includes/`.
- Media ability manifest and disposable WordPress probes under `workbench/labs/chattanooga-cms-admin/fixtures/` and `workbench/labs/chattanooga-cms-admin/probes/`.
- `.github/workflows/cmsa-content-lab.yml` only as required to execute the new probes.
- This task record and later workbench status/state/journal records after verified results.

## Exclusion set

- No mutation of `main`, `feature/chattanooga-cms-admin`, or live WordPress during this source-validation pass.
- No production deployment, miniOrange permission change, or live content/media mutation.
- No arbitrary filesystem, shell, database, generic option, remote-URL sideload, or third-party plugin/theme source endpoint.
- No media replacement or permanent media deletion in this slice; those remain separate destructive/recovery designs.
- No account, booking, payment, recurrence, or unrelated content changes.

## Evidence

- Live MCP discovery currently exposes 82 Chattanooga CMS Admin abilities and no media-specific ability family.
- The governing capability roadmap identifies media inspection, upload/replace/delete, and featured-image relationships as a missing WordPress content-administration layer.
- The current candidate already implements typed WordPress content transactions with native capabilities, exact-state guards, readback verification, audit logging, and `show_in_rest=false`; the media layer must preserve those boundaries.
- Current workbench head observed before branch creation: `4c03251beccfb89fa9039285ec240232cab35473`.

## Mutation set

- Add typed media list/get operations.
- Add bounded base64 media creation with WordPress-native MIME/extension validation and decoded-size limit; do not fetch arbitrary remote URLs.
- Add exact-state metadata update for title, caption, description, and alt text with verification and rollback to the prior metadata state on verification failure.
- Add exact-state featured-image relationship set/clear for posts/pages with target-image validation, verification, and relationship rollback on verification failure.
- Register the new abilities in the existing plugin bootstrap and real WordPress Abilities registry.
- Add permission, registration, transaction, stale-state, invalid-input, and isolation probes.

## Risk set

- Large or malformed base64 payloads could exhaust memory or create invalid files.
- MIME/extension mismatches could allow unsafe or incorrectly typed uploads if validation is insufficient.
- Metadata or featured-image writes could overwrite concurrent administrator changes without exact-state checks.
- Failed upload or relationship verification could leave orphaned files/attachments or altered relationships.
- Ability annotations or permissions could accidentally expose write operations as read-only or to underprivileged users.

## Rollback point

- Branch/base commit: `4c03251beccfb89fa9039285ec240232cab35473`.
- Restoration path: discard/reset `work/cmsa-media-administration` to the base commit; no production state is involved.

## Acceptance tests

- [ ] Real WordPress 7.1 registry contains the complete existing ability set plus the new media abilities, with no duplicate names.
- [ ] Anonymous and insufficiently privileged users are denied; an administrator with native upload/edit authority is allowed.
- [ ] Media list/get returns only bounded allowlisted metadata and stable exact-state tokens.
- [ ] Valid bounded base64 upload creates and verifies one attachment; malformed/oversize/MIME-mismatched payloads fail closed without orphaned attachments/files.
- [ ] Metadata update rejects stale/no-change writes, verifies exact requested fields, and can restore the prior metadata state after injected verification failure.
- [ ] Featured-image set/clear rejects stale relationship state and non-image attachments, verifies readback, and restores the prior relationship after injected verification failure.
- [ ] Unrelated posts and attachments remain unchanged in transaction probes.
- [ ] New abilities remain MCP-visible but public-REST hidden (`show_in_rest=false`).
- [ ] Applicable PHP/static and WordPress runtime CI passes before promotion into `workbench/mars`.

## Source position

- Repository: `stanfieldjd/chattanooga-music-scene`
- Branch: `work/cmsa-media-administration`
- Observed commit: `4c03251beccfb89fa9039285ec240232cab35473`

## Production state

NOT_DEPLOYED

## Result journal

- 2026-09-08: Task opened from verified workbench state; no production mutation authorized or performed.
