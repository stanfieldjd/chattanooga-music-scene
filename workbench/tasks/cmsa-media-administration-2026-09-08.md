# Task: cmsa-media-administration-2026-09-08

Status: WORKBENCH_INTEGRATED_VERIFIED_NOT_DEPLOYED

## Objective

Expand the Chattanooga CMS Admin candidate with a bounded core WordPress media-administration surface that advances browser-independent site administration without introducing arbitrary command execution or weakening the existing permission, rollback, audit, MCP-only, and conflict-checking architecture.

## Target set

- Source branch used for implementation: `work/cmsa-media-administration`.
- Integration branch: `workbench/mars`.
- `workbench/labs/chattanooga-cms-admin/candidate/chattanooga-cms-admin.php`.
- `workbench/labs/chattanooga-cms-admin/candidate/includes/class-cmsa-plugin.php`.
- Bounded media implementation/ability classes under `workbench/labs/chattanooga-cms-admin/candidate/includes/`.
- Media ability manifest and disposable WordPress probes under `workbench/labs/chattanooga-cms-admin/fixtures/` and `workbench/labs/chattanooga-cms-admin/probes/`.
- `.github/workflows/cmsa-content-lab.yml` only as required to execute the media probes.
- Workbench task/status/state/journal records.

## Exclusion set

- No mutation of `main`, `feature/chattanooga-cms-admin`, or live WordPress during this source-validation and workbench-integration pass.
- No production deployment, miniOrange permission change, or live content/media mutation.
- No arbitrary filesystem, shell, database, generic option, remote-URL sideload, or third-party plugin/theme source endpoint.
- No media replacement or permanent media deletion in this slice; those remain separate destructive/recovery designs.
- No account, booking, payment, recurrence, or unrelated content changes.

## Evidence

- Live MCP discovery before this source slice exposed 82 Chattanooga CMS Admin abilities and no media-specific ability family.
- The governing capability roadmap identifies media inspection, upload/replace/delete, and featured-image relationships as a missing WordPress content-administration layer.
- The existing candidate uses typed WordPress transactions with native capabilities, exact-state guards, readback verification, audit logging, and `show_in_rest=false`; the media layer preserves those boundaries.
- Source branch base: `4c03251beccfb89fa9039285ec240232cab35473`.
- Source implementation checkpoint exercised by CI: `fce5ef0a1cc470f483891cb60edf7a499d3584d0`.
- Pull request #9 merged `work/cmsa-media-administration` into `workbench/mars` at integration commit `1b73e5f8902062dc5ee9006ef30d0784438c5379`.
- Pre-integration WordPress 7.1 content runtime run `34271173933` passed. Registration reported `PASS (87 abilities)`; media permissions reported `PASS abilities=5 object-scope=verified`; media transactions reported `PASS upload validation metadata conflict rollback featured-image conflict rollback isolation`.
- Pre-integration CMS Admin Workbench Lab `34271173931`, Events Manager Lab `34271173926`, and Mars Workbench Integrity `34271174027` passed.
- PR-head verification at `24bac6d6c35ae6c6715be554b7efb0c2a3c5f988` passed all four required workflows.
- Post-integration push validation passed: content runtime `34271628425`, Events Manager runtime `34271628277`, Mars integrity `34271628426`, and CMS Admin Workbench Lab `34271628265` including PHP 7.4, PHP 8.2, WordPress 7.1 registry/permissions/REST isolation, backup/update/rollback, normal core update, and forced core rollback.

## Mutation set

- Added typed media list/get operations.
- Added bounded base64 media creation with WordPress-native MIME/extension validation and an 8 MiB decoded-size limit; no arbitrary remote URL fetch.
- Added exact-state metadata update for title, caption, description, and alt text with readback verification and semantic rollback after injected verification failure.
- Added exact-state featured-image relationship set/clear for posts/pages with target-image validation, readback verification, and relationship rollback/preservation after injected verification failure.
- Registered five new media abilities in the existing WordPress Abilities/MCP-only architecture.
- Added permission, registration, transaction, stale-state, invalid-input, rollback, and isolation probes.
- Integrated the execution-verified source slice into `workbench/mars` only.

## Risk set

- Large or malformed base64 payloads could exhaust memory or create invalid files; bounded encoded/decoded limits and strict decoding are enforced.
- MIME/extension mismatches could allow unsafe or incorrectly typed uploads; WordPress file-type/extension validation plus payload MIME probing is enforced.
- Metadata or featured-image writes could overwrite concurrent administrator changes; exact-state/timestamp and relationship checks are required.
- Failed upload or relationship verification could leave orphaned files/attachments or altered relationships; failure paths delete the just-created attachment or restore/preserve the prior relationship and are execution-tested.
- Ability annotations or permissions could accidentally expose write operations to underprivileged users; anonymous/subscriber denial and object-scope behavior passed runtime probes.

## Rollback point

- Pre-integration source/workbench base: `4c03251beccfb89fa9039285ec240232cab35473`.
- Integrated source checkpoint: `1b73e5f8902062dc5ee9006ef30d0784438c5379`.
- Workbench rollback path: revert the media integration commit from `workbench/mars`; production remains unaffected because no production deployment occurred.

## Acceptance tests

- [x] Real WordPress 7.1 registry contains the complete existing ability set plus the new media abilities, with no duplicate names. Verified count: 87 total, 5 media.
- [x] Anonymous and insufficiently privileged users are denied; an administrator with native upload/edit authority is allowed.
- [x] Media list/get returns bounded allowlisted metadata and stable exact-state tokens.
- [x] Valid bounded base64 upload creates and verifies one attachment; malformed/oversize/MIME-mismatched payloads fail closed without orphaned attachments/files.
- [x] Metadata update rejects stale/no-change writes, verifies exact requested fields, and restores prior semantic metadata after injected verification failure.
- [x] Featured-image set/clear rejects stale relationship state and non-image attachments, verifies readback, and restores/preserves the prior relationship after injected verification failure.
- [x] Unrelated posts and attachments remain unchanged in transaction probes.
- [x] New abilities remain MCP-visible and candidate abilities remain public-REST hidden (`show_in_rest=false`); real-WordPress REST isolation passed.
- [x] Applicable PHP/static and WordPress runtime CI passed before promotion into `workbench/mars`.
- [x] Post-integration push CI passed on `workbench/mars`.

## Source position

- Repository: `stanfieldjd/chattanooga-music-scene`
- Workbench branch: `workbench/mars`
- Source implementation checkpoint: `fce5ef0a1cc470f483891cb60edf7a499d3584d0`
- PR integration checkpoint: `1b73e5f8902062dc5ee9006ef30d0784438c5379`
- Candidate registry after integration: 87 abilities, including 5 media abilities.

## Production state

NOT_DEPLOYED

The production plugin remains the separately verified 82-ability runtime. This workbench increment does not establish that the five new media abilities are installed or exposed live.

## Result journal

- 2026-09-08: Task opened from verified workbench state; no production mutation authorized or performed.
- 2026-09-08: Added five bounded media abilities: list/get, validated bounded base64 create, exact-state metadata update, and exact-state featured-image relationship management.
- 2026-09-08: WordPress 7.1 runtime probes passed with 87 registered abilities; media permission and transaction probes passed; PHP 7.4/8.2, Events Manager, workbench runtime, and integrity regression workflows passed.
- 2026-09-08: PR #9 merged the execution-verified slice into `workbench/mars` at `1b73e5f8902062dc5ee9006ef30d0784438c5379`.
- 2026-09-08: All post-integration push workflows passed. Source/workbench media integration is E2 verified; production remains unchanged and requires separate A3 authorization for deployment.
