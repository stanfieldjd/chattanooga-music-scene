# Task: cmsa-weekend-feature-adapter-2026-09-08

Status: SOURCE_ENGINEERING_IN_PROGRESS

## Objective

Extend the Chattanooga CMS Admin workbench candidate with a typed, auditable adapter for the existing Chattanooga Music Scene Weekend Feature so the established Weekend Feature administrative operations can be performed without a browser while preserving the site-specific plugin as the authoritative implementation.

## Position and selected line

- Workbench base: `c99860480d628146e5f3f2805c822017eb0b8db6`.
- Source branch: `work/cmsa-weekend-feature-adapter`.
- The authoritative Weekend Feature implementation is `site-plugins/chattanooga-music-scene-core/includes/class-cms-weekend-posts.php` at the verified workbench base.
- That implementation already exposes public generation behavior through `CMS_Weekend_Posts::generate()`, canonical settings sanitation through `sanitize_settings()`, the settings option constant, post-type/week-key constants, and WordPress hooks that synchronize its schedule after settings updates.
- The CMS Admin roadmap explicitly requires a site-specific Weekend Feature adapter.
- Two source-only candidate lines were evaluated: (A) status/settings/draft only; (B) status/settings/draft plus the existing `Publish Now` operation. Line B is selected because the existing WordPress administration surface already provides Publish Now and omitting it would leave a material browser dependency. The ability may be engineered and fault-tested only in a disposable runtime here; any later live publication remains a separate external-effect authorization.

## Pre-execution control record

### OBJECTIVE

Provide four bounded Weekend Feature abilities inside Chattanooga CMS Admin: exact status inspection, exact settings replacement, guarded draft generation/update, and guarded immediate publication using the existing Weekend Feature plugin API/state model.

### TARGET_SET

- `workbench/labs/chattanooga-cms-admin/candidate/chattanooga-cms-admin.php`.
- `workbench/labs/chattanooga-cms-admin/candidate/includes/class-cmsa-plugin.php`.
- New Weekend Feature adapter and ability classes under `workbench/labs/chattanooga-cms-admin/candidate/includes/`.
- New expected-ability manifest and disposable probes under `workbench/labs/chattanooga-cms-admin/fixtures/` and `workbench/labs/chattanooga-cms-admin/probes/`.
- `.github/workflows/cmsa-content-lab.yml` and/or `.github/workflows/cmsa-events-lab.yml` only as needed to execute the adapter tests against real WordPress, real Events Manager, and the source-controlled Weekend Feature plugin.
- This task record and, after execution verification, the normal workbench status/state/queue/journal records.

### EXCLUSION_SET

- No mutation of `main`, `feature/chattanooga-cms-admin`, DreamHost, live WordPress, live Weekend Feature settings/posts, or miniOrange policy.
- Do not change `site-plugins/chattanooga-music-scene-core` merely to make the adapter easier to implement; it remains the authoritative dependency unless a separately evidenced source defect requires a direct repair.
- No generic option-management endpoint, arbitrary post-type mutation, arbitrary cron mutation, shell/database/filesystem backdoor, remote command execution, or unrelated site-specific adapter.
- No live publication or other live external effect.
- No recurrence, bookings, tickets, payments, account security, location deletion, or unrelated roadmap expansion.

### EVIDENCE

- The current workbench candidate is execution-verified at 87 abilities after the media integration.
- `workbench/labs/chattanooga-cms-admin/ROADMAP.md` explicitly names Weekend Feature under Layer G site-specific adapters.
- The source-controlled Weekend Feature plugin version 0.2.1 implements its administration in `CMS_Weekend_Posts`, including settings, Thursday scheduling, Friday-Sunday window calculation, Events Manager-backed event retrieval, draft generation, immediate publication, and exact week-key metadata.
- `generate()` refuses a published guide overwrite and requires `publish_posts` outside cron.
- Settings writes through `update_option(CMS_Weekend_Posts::OPTION_SETTINGS, ...)` invoke the plugin's own `update_option_...` hook, which re-synchronizes the scheduled event.

### MUTATION_SET

- Add four MCP-visible/public-REST-hidden Chattanooga CMS Admin abilities:
  1. `get-weekend-feature-status`.
  2. `update-weekend-feature-settings`.
  3. `generate-weekend-feature-draft`.
  4. `publish-weekend-feature-now`.
- Add a dependency adapter that reads only the Weekend Feature's allowlisted settings/post/schedule state, derives stable exact-state tokens, calls the dependency's public methods for sanitation/generation, and verifies/rolls back candidate-controlled mutations.
- Add disposable permission, registration, exact-state, stale/no-change, schedule, generation, publication, rollback/fault-injection, and unrelated-state isolation probes.

### RISK_SET

- A stale settings/post snapshot could overwrite a concurrent administrator change; every mutation will require exact prior-state tokens/identity.
- Direct option mutation could bypass the Weekend Feature schedule lifecycle; the adapter will use the named source-owned option and rely on the source plugin's existing option-update hook, then verify schedule state.
- Settings sanitation can disable an incomplete schedule; the adapter must reject invalid requested enabled state rather than silently reporting the degraded state as success.
- Generation can create or update a Weekend Feature post. Tests must distinguish an absent target from an existing draft, preserve the exact pre-state when updating, remove only a newly created test object on verification failure, and never overwrite an already-published feature.
- Immediate publication is an external effect in production. This task will execute it only inside a disposable CI WordPress runtime; its presence in source does not authorize live invocation.
- Raw dependency errors could leak implementation details; public errors must remain bounded and candidate-owned.

### ROLLBACK_POINT

- Source rollback point: branch base `c99860480d628146e5f3f2805c822017eb0b8db6`.
- Before workbench integration, discard/reset `work/cmsa-weekend-feature-adapter` to the base commit.
- For tested settings mutations, restore the exact prior settings option and verify schedule restoration.
- For tested generation mutations, restore the exact existing Weekend Feature post snapshot or permanently remove only a newly created disposable fixture post when verification fails.
- Production is not part of this transaction.

### ACCEPTANCE_TESTS

- [ ] Registry contains exactly 91 expected abilities after adding four Weekend Feature abilities, with no duplicates.
- [ ] All four remain `show_in_rest=false` and MCP-visible under the existing candidate architecture.
- [ ] Anonymous/subscriber users are denied; required native WordPress capabilities are enforced.
- [ ] Status read fails closed when the Weekend Feature dependency is absent and returns only allowlisted normalized settings, schedule, current-weekend, event-count, and current-feature state when present.
- [ ] Settings state token is stable for unchanged state; stale/no-change requests are rejected.
- [ ] Enabled settings require a valid Thursday time and valid existing author; exact requested settings and resulting cron schedule are verified after mutation.
- [ ] Injected settings verification failure restores the exact prior settings and schedule state.
- [ ] Draft generation requires exact current-weekend/existing-feature state, creates or updates only the current weekend feature, verifies draft/week-key/content identity, and preserves unrelated posts/events.
- [ ] Draft generation refuses to overwrite an already-published current-weekend feature.
- [ ] Immediate publication uses the dependency's existing `generate('publish')` path in disposable WordPress only, verifies publish/week-key state, and preserves unrelated posts/events.
- [ ] Injected post verification failure restores an existing draft's exact semantic state or removes only the newly created disposable target.
- [ ] PHP 7.4/8.2/static checks and real WordPress 7.1/Events Manager regression suites remain green.
- [ ] No production mutation occurs.

## Production state

NOT_DEPLOYED

No source produced by this task is authorized for live installation or live execution by the current continuation.

## Result journal

- 2026-09-08: Task opened from integrity-verified workbench head `c99860480d628146e5f3f2805c822017eb0b8db6`; source-only engineering selected; production excluded.
