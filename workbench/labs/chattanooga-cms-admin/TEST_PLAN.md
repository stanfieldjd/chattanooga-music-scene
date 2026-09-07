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
- Register category and all current 38 abilities: 24 maintenance + 14 bounded post/page abilities.
- Latest registration evidence: run `34168314548`, `wordpress-ability-registration: PASS (38 abilities)`.

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
Latest full regression: `34168314554`.

## Gate 3 — Permission, exposure, and error model
Status: PASS
Maintenance:
- 24 maintenance abilities denied anonymous and allowed administrator.
- Exact isolation across 10 intended maintenance capabilities.
Content slice:
- 14 post/page abilities denied anonymous and allowed administrator.
- Limited user with only post edit/delete capabilities receives only post-level ability access.
- Object-level checks prevent access to another author's post without `edit_others_posts`.
- Page access remains denied without page capabilities.
Exposure/error boundaries:
- Candidate abilities remain `show_in_rest=false` and absent from direct REST execution surface.
- Sensitive-marker error-output regression remains green.
Evidence: maintenance run `34168314554`; content run `34168314548`.

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
Latest full evidence: `34168314554`.

## Gate 6 — Core update/rollback
Status: PASS
- Core+DB rollback snapshot.
- Exact independent restore.
- Candidate-controlled 7.0 -> 7.1 core transaction.
- Configuration/content/database/plugin preservation.
- Deliberate validation mismatch and automatic exact core/database rollback.
Latest full evidence: `34168314554`.

## Gate 7 — Package-network/privacy surface
Status: PASS for exercised WordPress.org package operations
- Seeded private markers absent from captured WordPress.org API/download requests.
- No direct candidate vendor transport.
- Public upstream errors bounded/redacted.

## Gate 8 — Multisite cache execution
Status: PASS
- Real WordPress 7.1 multisite network installation and candidate network activation.
- Cache branch verified.
- Layer B candidate source remained compatible with multisite network activation in run `34168247940`.

## Gate 9 — Bounded WordPress content administration
Status: PASS for first post/page slice
Evidence: CMS Admin Content Layer Lab run `34168314548`, candidate commit `b5ba61e0f435624a6f834566a3fa85ede13221f7`.

Registration:
- 14 explicit content abilities added separately from the 24 maintenance abilities.

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
- trash and restore only; permanent deletion is not in this slice;
- author-scoped listing when user cannot edit others' content;
- page parent relationship preserved;
- unrelated control content remains unchanged.

Exact outputs:
- `content-permission-cli: PASS abilities=14 limited=post-only object-scope=verified`
- `content-transaction-cli: PASS post=draft-conflict-update-revision-trash-restore page=parent-update-trash-restore unrelated=unchanged`

Next content sub-gates:
- publish/unpublish/schedule/private/pending transitions;
- taxonomy/category/tag assignment and lifecycle;
- media and featured-image relationships;
- permanent deletion only with a separately tested explicit contract;
- menus/navigation/options/comments where actually required.

## Gate 10 — Chattanooga/DreamHost read-only preflight
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

## Gate 11 — Chattanooga installation/runtime
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
