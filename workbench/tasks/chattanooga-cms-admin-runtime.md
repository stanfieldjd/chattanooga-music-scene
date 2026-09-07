# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_MAINTENANCE_AND_CONTENT_SLICE_VERIFIED — LIVE PREFLIGHT PARTIAL / MCP DEPLOYMENT PENDING

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

## Current evidence

- Verified source checkpoint: `0b34773ebc8073cb657477770b34cabc280f5892`.
- Current workbench content candidate: `b5ba61e0f435624a6f834566a3fa85ede13221f7`.
- Full maintenance regression: CMS Admin Workbench Lab run `34168314554`; PHP 7.4, PHP 8.2, and complete WordPress 7.1 runtime all passed.
- Runtime artifact: id `10034902046`, SHA-256 `ebf4f3e668d0e73dd19a539732570b1d7cd66d7b8c34c31921da2c7222f20b95`.
- Dedicated Layer B content regression: CMS Admin Content Layer Lab run `34168314548`.
- Content registration result: `wordpress-ability-registration: PASS (38 abilities)` — 24 maintenance plus 14 post/page abilities.
- Content permission result: `content-permission-cli: PASS abilities=14 limited=post-only object-scope=verified`.
- Content transaction result: `content-transaction-cli: PASS post=draft-conflict-update-revision-trash-restore page=parent-update-trash-restore unrelated=unchanged`.
- Real WordPress 7.1 multisite network activation for the same candidate source passed in run `34168247940`.
- Workbench integrity passed in run `34168314530`.
- Source has not been merged to `main` and has not been installed on Chattanooga Music Scene.

## Maintenance gates completed

- [x] Exact immutable source Git blob identities.
- [x] PHP 7.4 and PHP 8.2 compatibility.
- [x] No arbitrary shell/PHP/SQL execution surface and no candidate-owned generic REST route.
- [x] 24 maintenance abilities in real WordPress registry.
- [x] Maintenance permission matrix and REST isolation.
- [x] Database/component/theme/core backup and exact rollback paths.
- [x] Plugin/theme lifecycle and WordPress.org update/install transactions.
- [x] Normal core update 7.0 -> 7.1 and forced core rollback.
- [x] Package-network privacy and public error redaction.
- [x] Single-site and multisite cache behavior.
- [x] Corrupt/missing rollback material rejection.
- [x] Fully unavailable backup storage rejection.
- [x] Deterministic plugin update edges.
- [x] Progressive/stalled database write enforcement.
- [x] Component/core ZIP finalization enforcement.

## Layer B — bounded WordPress content administration

Reference-verified first slice:

- [x] List posts/pages with bounded pagination/search/status filters.
- [x] Object-level get for posts/pages.
- [x] Draft-only post/page creation; no implicit publishing.
- [x] Conflict-checked title/content/excerpt updates using exact `post_modified_gmt`.
- [x] Native WordPress revision rollback point before updates.
- [x] Restore a target-owned revision after conflict validation.
- [x] Trash and restore posts/pages; no permanent-delete path in this slice.
- [x] Page parent validation and preservation.
- [x] Author scoping for users without `edit_others_*` authority.
- [x] Unrelated-content sentinel remains unchanged through transactions.

Not yet implemented/tested in Layer B:

- [ ] Publish/unpublish/schedule/private/pending status transitions.
- [ ] Permanent content deletion.
- [ ] Taxonomy/category/tag assignment and term lifecycle.
- [ ] Media upload/replace/delete and featured-image relationships.
- [ ] Menus/navigation and bounded site-option administration.
- [ ] Comments/moderation if required.

## Chattanooga/DreamHost read-only preflight

Partially verified through the existing connected WordPress surface, with no mutation:

- WordPress `7.1`.
- PHP `8.2.30`.
- MySQL `8.0.41`.
- WordPress root, `wp-content`, uploads, plugins, themes, and MU-plugins reported writable.
- WP Super Cache is active and `WP_CACHE` is enabled.
- Existing MCP transport exposed 311 current abilities.
- No `chattanooga-cms-admin/*` abilities were present, consistent with the candidate not being deployed.

Still UNKNOWN from the current live read-only surface:

- free disk capacity / production backup-size feasibility;
- WordPress filesystem method;
- direct live `ZipArchive` availability;
- preferred outside-web-root backup parent writability.

## Pending runtime/deployment gates

- [ ] Establish a separately authorized installation and rollback transaction for the exact validated candidate.
- [ ] Install/activate candidate on Chattanooga Music Scene only after that rollback point exists.
- [ ] Discover the candidate abilities through the actual Chattanooga MCP transport.
- [ ] Run candidate health inventory and verify a local production rollback backup before live maintenance mutations.
- [ ] Continue broader typed administration layers from `ROADMAP.md` in disposable workbench fixtures first.

## Risk set

- Reference GitHub filesystem behavior does not prove all DreamHost filesystem/storage behavior.
- Actual free disk capacity, filesystem method, ZipArchive, and preferred backup-parent writability remain live-environment facts.
- Actual MCP transport may expose/filter metadata differently than the WordPress registry.
- Layer B currently covers only bounded post/page draft/update/revision/trash/restore operations; publishing, taxonomy/media, member, event, and commerce operations require separate contracts and tests.
- Package privacy evidence covers the exercised WordPress.org operations only; unrelated plugins and WooCommerce-specific traffic remain separate.

## Rollback point

- Workbench history remains recoverable through Git commits.
- Immutable baseline preserves exact source checkpoint `0b34773e...`.
- Candidate experiments are isolated from source and production.
- Production rollback must be separately established before deployment.

## Production state

NOT_DEPLOYED

## Result journal

- 2026-09-07: Maintenance layer advanced through registration, permissions, backup/restore, update/rollback, privacy, redaction, storage-integrity, and multisite gates.
- 2026-09-07: Read-only Chattanooga preflight confirmed WordPress/PHP/MySQL versions, relevant WordPress directory writability, WP Super Cache presence, and current MCP discovery surface; unavailable server-level facts remain unknown.
- 2026-09-07: Added bounded Layer B post/page service and 14 typed abilities. A syntax defect in the new permission probe was corrected without changing candidate product source.
- 2026-09-07: Layer B run `34168314548` passed all 38 ability registration, content permission/object scoping, draft/conflict/revision/trash/restore, page-parent, and unrelated-content tests.
- 2026-09-07: Full maintenance run `34168314554` and multisite run `34168247940` remained green with the Layer B candidate.
