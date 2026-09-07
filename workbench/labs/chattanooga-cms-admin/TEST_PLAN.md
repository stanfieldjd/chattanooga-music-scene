# Chattanooga CMS Admin Workbench Test Plan

Tests are ordered so that a failure in a lower-risk prerequisite blocks higher-risk operations. Disposable GitHub/WordPress fixtures are used before any Chattanooga runtime mutation.

## Gate 0 — Source and architecture
Status: PASS
- Immutable baseline Git blob verification.
- PHP 7.4 and PHP 8.2 lint.
- No arbitrary shell/PHP/SQL execution surface.
- No custom direct REST route registration.
- Exact expected ability manifests.

## Gate 1 — Real WordPress 7.1 registration
Status: PASS
- Install and activate candidate.
- Verify native Abilities API.
- Register category and all current 40 abilities: 24 maintenance + 14 bounded post/page CRUD/revision + 2 publication-status abilities.
- Latest registration evidence: content run `34168825779`, `wordpress-ability-registration: PASS (40 abilities)`.

## Gate 2 — Backup primitives and fail-closed restore
Status: PASS in disposable reference runtime
- database backup + SHA-256 verification;
- plugin/theme/core rollback material and exact restore;
- numeric primary-key database fidelity;
- corrupt/missing material rejection before target mutation;
- all backup locations unavailable fails before backup creation;
- progressive short writes complete exactly;
- stalled writes fail and incomplete SQL is removed;
- component/core ZIP finalization failure or zero-byte output is rejected and removed.
Latest full regression: `34168825545`.

## Gate 3 — Permission, exposure, and error model
Status: PASS
Maintenance:
- 24 maintenance abilities denied anonymous and allowed administrator.
- Exact isolation across 10 intended maintenance capabilities.
Content CRUD/revision:
- 14 post/page abilities denied anonymous and allowed administrator.
- Limited user with only post edit/delete capabilities receives only post-level ability access.
- Object-level checks prevent access to another author's post without `edit_others_posts`.
Status transitions:
- set-post-status and set-page-status denied anonymous and allowed administrator.
- Limited edit-post user sees post status ability but not page status ability.
- Execution separately requires `publish_posts` / `publish_pages` for publish, private, and future states.
Exposure/error boundaries:
- Candidate abilities remain `show_in_rest=false` and absent from direct REST execution surface.
- Sensitive-marker error-output regression remains green.
Evidence: maintenance run `34168825545`; content run `34168825779`.

## Gate 4 — Lifecycle operations
Status: PASS
- Plugin activate/deactivate.
- Plugin auto-update enable/disable.
- Backup-protected plugin deletion/restore.
- Backup-protected theme deletion/restore.
- Theme switch/return.
- Theme auto-update enable/disable with original state restoration.

## Gate 5 — Update engine
Status: PASS
- Real WordPress.org plugin update with rollback backup.
- Theme update and exact rollback.
- Forced plugin post-update validation failure and exact automatic rollback.
- Deterministic no-update, local v1→v2, malformed-package fail-closed exact rollback.
Latest full evidence: `34168825545`.

## Gate 6 — Core update/rollback
Status: PASS
- Core+DB rollback snapshot.
- Exact independent restore.
- Candidate-controlled 7.0 -> 7.1 core transaction.
- Configuration/content/database/plugin preservation.
- Deliberate validation mismatch and automatic exact core/database rollback.
Latest full evidence: `34168825545`.

## Gate 7 — Package-network/privacy surface
Status: PASS for exercised WordPress.org package operations
- Seeded private markers absent from captured WordPress.org API/download requests.
- No direct candidate vendor transport.
- Public upstream errors bounded/redacted.

## Gate 8 — Multisite cache execution
Status: PASS
- Real WordPress 7.1 multisite network installation and candidate network activation.
- Cache branch verified.
- Current 40-ability candidate source remained compatible in run `34168825541`.

## Gate 9 — Bounded WordPress content CRUD/revision
Status: PASS
Evidence: content run `34168825779` and full run `34168825545`.

Read/create/update behavior:
- bounded post/page list with page/per_page/search/status filters;
- object-level post/page read;
- draft-only creation;
- page-parent validation;
- exact `post_modified_gmt` optimistic-concurrency check;
- stale update fails closed without changing content;
- native WordPress revision rollback point before mutation;
- post/page title/content/excerpt update verification;
- target-owned revision restore with pre-restore rollback revision.

Lifecycle/isolation behavior:
- trash and restore only; permanent deletion remains absent;
- author-scoped listing when user cannot edit others' content;
- page parent relationship preserved;
- unrelated control content remains unchanged.

Exact outputs:
- `content-permission-cli: PASS abilities=14 limited=post-only object-scope=verified`
- `content-transaction-cli: PASS post=draft-conflict-update-revision-trash-restore page=parent-update-trash-restore unrelated=unchanged`

## Gate 10 — Publication/status transitions
Status: PASS
Evidence: content run `34168825779`, candidate commit `2f299aa743f82f03888954dc1beec9ad1bafb999`.

Contract:
- exact `expected_modified_gmt` and `expected_status` are mandatory;
- supported target states are draft, pending, publish, private, future;
- trash must be restored before publication-state mutation;
- publish/private/future require target-specific WordPress publish capability;
- future requires an explicit valid future UTC timestamp;
- transition output is read back and verified;
- verification/readback failure attempts exact rollback of prior status/date/date_gmt.

Runtime coverage:
- stale timestamp rejected with status unchanged;
- stale status rejected with status unchanged;
- post: draft -> pending -> private -> publish -> future -> publish-now;
- page: draft -> pending -> publish -> draft;
- limited edit-only user: pending allowed, publish denied and status remains pending;
- unrelated control post unchanged.

Exact outputs:
- `content-status-permission-cli: PASS anonymous=denied admin=post,page limited=post-only`
- `content-status-cli: PASS conflicts=timestamp,status post=pending-private-publish-future-publish page=pending-publish-draft limited=publish-denied unrelated=unchanged`

## Gate 11 — Taxonomy relationships
Status: ACTIVE NEXT
Planned bounded contract:
- registered taxonomy whitelist only (`category`, `post_tag`) for this first taxonomy slice;
- list/get terms;
- create/update terms with duplicate/parent validation;
- assign/remove terms to posts/pages only when the taxonomy is registered to that object type;
- exact before/after relationship verification;
- relationship rollback on verification failure;
- term deletion only after separately proving relationship consequences and default-category behavior;
- no arbitrary taxonomy or metadata writes.

## Gate 12 — Chattanooga/DreamHost read-only preflight
Status: PARTIAL PASS / NON-MUTATING
Verified through current connected WordPress surface:
- WordPress 7.1;
- PHP 8.2.30;
- MySQL 8.0.41;
- WordPress root, wp-content, uploads, plugins, themes, and MU-plugins writable;
- WP Super Cache active and WP_CACHE enabled;
- existing MCP surface discoverable with 311 abilities;
- candidate abilities absent because candidate is not deployed.

Still UNKNOWN through current surface:
- free disk capacity / production backup-size feasibility;
- WordPress filesystem method;
- direct live ZipArchive availability;
- outside-web-root preferred backup parent writability.

## Gate 13 — Chattanooga installation/runtime
Status: NOT AUTHORIZED BY WORKBENCH TESTING ALONE
Requires separate production authorization and rollback transaction.
- install exact validated candidate package;
- activate;
- discover expected abilities through actual AI transport;
- run health inventory;
- create and verify local backup;
- perform only separately authorized live mutations.

## Rule for new coding
Every candidate change must identify the failing/desired test first when practical, modify only `candidate/` when product source must change, run the complete applicable gate set, and update the capability matrix. A candidate is never promoted because it merely compiles.
