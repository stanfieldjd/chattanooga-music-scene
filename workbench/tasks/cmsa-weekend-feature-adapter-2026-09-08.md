# Task: cmsa-weekend-feature-adapter-2026-09-08

Status: COMPLETE

## Objective

Extend the Chattanooga CMS Admin workbench candidate with a typed, auditable adapter for the existing Chattanooga Music Scene Weekend Feature so the established Weekend Feature administrative operations can be performed without a browser while preserving the site-specific plugin as the authoritative implementation.

## Position and selected line

- Workbench base: `c99860480d628146e5f3f2805c822017eb0b8db6`.
- Source branch: `work/cmsa-weekend-feature-adapter`.
- Source checkpoint: `e0e29c0786f8a2ae21d070d64d0d2408138d74f4`.
- Workbench integration checkpoint: `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98`.
- The authoritative Weekend Feature implementation is `site-plugins/chattanooga-music-scene-core/includes/class-cms-weekend-posts.php`.
- That implementation exposes public generation behavior through `CMS_Weekend_Posts::generate()`, canonical settings sanitation through `sanitize_settings()`, the settings option constant, post-type/week-key constants, and source-owned schedule synchronization hooks.
- The CMS Admin roadmap explicitly requires a site-specific Weekend Feature adapter.
- Two source-only candidate lines were evaluated: (A) status/settings/draft only; (B) status/settings/draft plus the existing `Publish Now` operation. Line B was selected because the existing WordPress administration surface already provides Publish Now and omitting it would leave a material browser dependency. The ability was engineered and fault-tested only in disposable runtimes; any later live publication remains a separate external-effect authorization.
- During execution the first-save scheduler probe exposed a source defect: the Weekend Feature source listened only to `update_option_cms_weekend_post_settings`, which does not fire when WordPress creates the option for the first time. The direct source repair added the corresponding `add_option_...` synchronization hook and advanced the source contract to `0.2.2`; no adapter bypass or duplicate scheduler path was introduced.

## Pre-execution control record

### OBJECTIVE

Provide four bounded Weekend Feature abilities inside Chattanooga CMS Admin: exact status inspection, exact settings replacement, guarded draft generation/update, and guarded immediate publication using the existing Weekend Feature plugin API/state model.

### TARGET_SET

- `workbench/labs/chattanooga-cms-admin/candidate/chattanooga-cms-admin.php`.
- `workbench/labs/chattanooga-cms-admin/candidate/includes/class-cmsa-plugin.php`.
- Weekend Feature adapter and ability classes under `workbench/labs/chattanooga-cms-admin/candidate/includes/`.
- Expected-ability manifest and disposable probes under `workbench/labs/chattanooga-cms-admin/fixtures/` and `workbench/labs/chattanooga-cms-admin/probes/`.
- `.github/workflows/cmsa-content-lab.yml` and `.github/workflows/cmsa-events-lab.yml` as required to execute the adapter tests against real WordPress, real Events Manager, and the source-controlled Weekend Feature plugin.
- `site-plugins/chattanooga-music-scene-core/chattanooga-music-scene-core.php` only for the separately evidenced first-save scheduler source defect and its direct source repair.
- This task record and normal workbench status/state/queue/journal records.

### EXCLUSION_SET

- No mutation of `main`, `feature/chattanooga-cms-admin`, DreamHost, live WordPress, live Weekend Feature settings/posts, or miniOrange policy.
- Do not change `site-plugins/chattanooga-music-scene-core` merely to make the adapter easier to implement; it remains the authoritative dependency. The only source change made there was the separately evidenced first-save scheduler defect repair.
- No generic option-management endpoint, arbitrary post-type mutation, arbitrary cron mutation, shell/database/filesystem backdoor, remote command execution, or unrelated site-specific adapter.
- No live publication or other live external effect.
- No recurrence, bookings, tickets, payments, account security, location deletion, or unrelated roadmap expansion.

### EVIDENCE

- The starting workbench candidate was execution-verified at 87 abilities after the media integration.
- `workbench/labs/chattanooga-cms-admin/ROADMAP.md` explicitly names Weekend Feature under Layer G site-specific adapters.
- The source-controlled Weekend Feature plugin implements its administration in `CMS_Weekend_Posts`, including settings, Thursday scheduling, Friday-Sunday window calculation, Events Manager-backed event retrieval, draft generation, immediate publication, and exact week-key metadata.
- `generate()` refuses a published guide overwrite and requires `publish_posts` outside cron.
- The initial real WordPress scheduler diagnostic proved that first-time option creation persisted enabled settings without creating the cron event because only the update-option hook was registered.
- Weekend Feature source version `0.2.2` adds the source-owned add-option synchronization hook; its direct workflow and the integrated Events Manager diagnostic verify first-save Thursday scheduling.
- The CMS Admin adapter contract is pinned to Weekend Feature `0.2.2`, uses exact settings/schedule and feature state tokens, and restores the exact prior cron timestamp/schedule/args when verification is fault-injected.

### MUTATION_SET

- Added four MCP-visible/public-REST-hidden Chattanooga CMS Admin abilities:
  1. `get-weekend-feature-status`.
  2. `update-weekend-feature-settings`.
  3. `generate-weekend-feature-draft`.
  4. `publish-weekend-feature-now`.
- Added a dependency adapter that reads only the Weekend Feature's allowlisted settings/post/schedule state, derives stable exact-state tokens, calls the dependency's public methods for sanitation/generation, and verifies/rolls back candidate-controlled mutations.
- Added disposable permission, registration, exact-state, stale/no-change, schedule, generation, publication, rollback/fault-injection, and unrelated-state isolation probes.
- Repaired the source-owned first-save scheduler lifecycle by synchronizing on both add-option and update-option events and advanced the verified source contract to `0.2.2`.

### RISK_SET

- A stale settings/post snapshot could overwrite a concurrent administrator change; every mutation requires exact prior-state tokens/identity.
- Direct option mutation can bypass schedule lifecycle if source hooks are incomplete; the discovered first-save defect was repaired in the source plugin and regression-tested rather than bypassed in the adapter.
- Settings sanitation can disable an incomplete schedule; the adapter rejects invalid requested enabled state rather than silently reporting degraded state as success.
- Generation can create or update a Weekend Feature post. Tests distinguish an absent target from an existing draft, preserve the exact pre-state when updating, remove only a newly created disposable fixture post on verification failure, and refuse to overwrite an already-published feature.
- Immediate publication is an external effect in production. This task executed it only inside disposable CI WordPress runtimes; its presence in source does not authorize live invocation.
- Raw dependency errors could leak implementation details; public errors remain bounded and candidate-owned.

### ROLLBACK_POINT

- Source rollback point: branch base `c99860480d628146e5f3f2805c822017eb0b8db6`.
- Integrated rollback point: parent of workbench merge commit `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98` is `c99860480d628146e5f3f2805c822017eb0b8db6`.
- For tested settings mutations, the adapter restores the exact prior settings option and exact prior cron timestamp/schedule/args and verifies restoration.
- For tested generation mutations, the adapter restores the exact existing Weekend Feature semantic snapshot or permanently removes only a newly created disposable fixture post when verification fails.
- Production is not part of this transaction.

### ACCEPTANCE_TESTS

- [x] Registry contains exactly 91 expected abilities after adding four Weekend Feature abilities, with no duplicates.
- [x] All four remain `show_in_rest=false` and MCP-visible under the existing candidate architecture.
- [x] Anonymous/subscriber users are denied; required native WordPress capabilities are enforced.
- [x] Status read fails closed when the Weekend Feature dependency is absent and returns only allowlisted normalized settings, schedule, current-weekend, event-count, and current-feature state when present.
- [x] Settings state token is stable for unchanged state; stale/no-change requests are rejected.
- [x] Enabled settings require a valid Thursday time and valid existing author; exact requested settings and resulting cron schedule are verified after mutation.
- [x] Injected settings verification failure restores the exact prior settings and schedule state.
- [x] Draft generation requires exact current-weekend/existing-feature state, creates or updates only the current weekend feature, verifies draft/week-key/content identity, and preserves unrelated posts/events.
- [x] Draft generation refuses to overwrite an already-published current-weekend feature.
- [x] Immediate publication uses the dependency's existing `generate('publish')` path in disposable WordPress only, verifies publish/week-key state, and preserves unrelated posts/events.
- [x] Injected post verification failure restores an existing draft's exact semantic state or removes only the newly created disposable target.
- [x] PHP 7.4/8.2/static checks and real WordPress 7.1/Events Manager regression suites remain green.
- [x] No production mutation occurs.

## Verification

Source checkpoint `e0e29c0786f8a2ae21d070d64d0d2408138d74f4` passed the full source-branch regression set before integration. PR #10 then merged the verified slice into `workbench/mars` at `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98`.

Post-integration push validation on `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98` passed:

- CMS Admin Workbench Lab `34276153946` — success, including PHP 7.4, PHP 8.2, WordPress 7.1 registry/permission/REST isolation, backup/update/rollback, normal core update, and forced-core rollback.
- Mars Workbench Integrity `34276153954` — success.
- Chattanooga Music Scene Weekend Feature `34276153963` — success.
- CMS Admin Events Manager Lab `34276154034` — success, including the first-save scheduler diagnostic and Weekend settings/draft/publish/rollback transaction probe.
- CMS Admin Content Layer Lab `34276154008` — success.

## Production state

NOT_DEPLOYED

The production Chattanooga CMS Admin runtime remains independently distinct from this source/workbench result. This task made no live Weekend Feature settings/post change, no live publication, no miniOrange policy change, and no production deployment.

## Result journal

- 2026-09-08: Task opened from integrity-verified workbench head `c99860480d628146e5f3f2805c822017eb0b8db6`; source-only engineering selected; production excluded.
- 2026-09-08: Real WordPress scheduling diagnostics exposed the source-owned first-save option lifecycle defect. The Weekend Feature source was repaired at the cause, advanced to contract version `0.2.2`, and exact scheduler rollback was strengthened to preserve cron args/timestamp/schedule.
- 2026-09-08: Source checkpoint `e0e29c0786f8a2ae21d070d64d0d2408138d74f4` passed all five source regression workflows.
- 2026-09-08: PR #10 merged into `workbench/mars` at `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98`; all five post-integration push workflows passed. Source/workbench acceptance is complete; production remains unchanged and not deployed.
