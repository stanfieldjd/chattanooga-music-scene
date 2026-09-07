# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_MAINTENANCE_CONTENT_AND_STATUS_VERIFIED — TAXONOMY NEXT / LIVE PREFLIGHT PARTIAL / MCP DEPLOYMENT PENDING

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
- Existing MCP transport is not removed as an incidental operation.
- No production plugin/theme/core/content mutation is bundled into workbench validation.

## Current evidence

- Verified source checkpoint: `0b34773ebc8073cb657477770b34cabc280f5892`.
- Current status-transition candidate: `2f299aa743f82f03888954dc1beec9ad1bafb999`.
- Full maintenance regression: run `34168825545`; PHP 7.4, PHP 8.2, and complete disposable WordPress 7.1 runtime all passed.
- Runtime artifact: id `10035054428`, SHA-256 `3af7c6d621fafe7146cd825da165f655f313e7ef922ff4e22a336c85fc900e06`.
- Dedicated content regression: run `34168825779`.
- Real registry result: `wordpress-ability-registration: PASS (40 abilities)` — 24 maintenance + 14 post/page CRUD/revision abilities + 2 publication-status abilities.
- Status coarse permission result: `content-status-permission-cli: PASS anonymous=denied admin=post,page limited=post-only`.
- Status transaction result: `content-status-cli: PASS conflicts=timestamp,status post=pending-private-publish-future-publish page=pending-publish-draft limited=publish-denied unrelated=unchanged`.
- Real WordPress 7.1 multisite source compatibility: run `34168825541`, passed.
- Workbench integrity: run `34168825835`, passed.
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

Reference-verified CRUD/revision slice:

- [x] List posts/pages with bounded pagination/search/status filters.
- [x] Object-level get for posts/pages.
- [x] Draft-only post/page creation.
- [x] Conflict-checked title/content/excerpt updates using exact `post_modified_gmt`.
- [x] Native WordPress revision rollback point before updates.
- [x] Restore a target-owned revision after conflict validation.
- [x] Trash and restore posts/pages; no permanent-delete path.
- [x] Page parent validation and preservation.
- [x] Author scoping for users without `edit_others_*` authority.
- [x] Unrelated-content sentinel remains unchanged through transactions.

Reference-verified publication/status slice:

- [x] Exact timestamp and expected-status conflict checks before transition.
- [x] Draft -> pending.
- [x] Pending -> private with publish authority.
- [x] Private -> publish.
- [x] Publish -> scheduled future with explicit UTC timestamp.
- [x] Scheduled future -> publish-now with date normalization.
- [x] Page draft -> pending -> publish -> draft.
- [x] Users with edit authority but without publish authority may submit pending but cannot publish/private/schedule.
- [x] Verification failure path contains automatic prior status/date rollback.
- [x] Unrelated-content sentinel remains unchanged.

Next Layer B workbench sub-gates:

- [ ] Taxonomy/category/tag list/get/create/update/delete and post/page relationship assignment/removal with exact target validation and rollback semantics.
- [ ] Media upload/replace/delete and featured-image relationships.
- [ ] Permanent content deletion only with a separately tested explicit destructive contract.
- [ ] Menus/navigation and bounded site-option administration where required.
- [ ] Comments/moderation if required.

## Chattanooga/DreamHost read-only preflight

Partially verified through the existing connected WordPress surface, with no mutation:

- WordPress `7.1`.
- PHP `8.2.30`.
- MySQL `8.0.41`.
- WordPress root, `wp-content`, uploads, plugins, themes, and MU-plugins reported writable.
- WP Super Cache active and `WP_CACHE` enabled.
- Existing MCP transport exposed 311 current abilities.
- No `chattanooga-cms-admin/*` abilities were present, consistent with candidate not being deployed.

Still UNKNOWN from the current live read-only surface:

- free disk capacity / production backup-size feasibility;
- WordPress filesystem method;
- direct live `ZipArchive` availability;
- preferred outside-web-root backup parent writability.

## Pending runtime/deployment gates

- [ ] Establish a separately authorized installation and rollback transaction for the exact validated candidate.
- [ ] Install/activate candidate on Chattanooga Music Scene only after that rollback point exists.
- [ ] Discover candidate abilities through the actual Chattanooga MCP transport.
- [ ] Run candidate health inventory and verify a local production rollback backup before live maintenance mutations.
- [ ] Continue broader typed administration layers from `ROADMAP.md` in disposable workbench fixtures first.

## Risk set

- Reference GitHub filesystem behavior does not prove all DreamHost filesystem/storage behavior.
- Actual free disk capacity, filesystem method, ZipArchive, and preferred backup-parent writability remain live-environment facts.
- Actual MCP transport may expose/filter metadata differently than the WordPress registry.
- Layer B status transitions are reference-verified but taxonomy/media/permanent-delete remain separate contracts and tests.
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
- 2026-09-07: Bounded post/page CRUD/revision slice passed dedicated content, full maintenance, multisite, and integrity regressions.
- 2026-09-07: Added `set-post-status` and `set-page-status` as separate typed abilities with exact timestamp/status conflict checks, publish capability enforcement, scheduling validation, and automatic prior-state rollback on verification failure.
- 2026-09-07: Status run `34168825779` passed 40-ability registration, status permission isolation, post/page publication transitions, non-publisher denial, and unrelated-content isolation; maintenance run `34168825545`, multisite run `34168825541`, and integrity run `34168825835` remained green.
