# Task: cmsa-media-lifecycle-2026-09-09

Status: REPLACEMENT_IMPLEMENTATION

## Objective

Extend Chattanooga CMS Admin from its verified 97-ability workbench position with the remaining bounded WordPress media lifecycle operations only after their exact native deletion/replacement semantics, permission model, reference effects, rollback requirements, and file/metadata behavior are execution-verified in disposable WordPress.

## Target set

- Source branch `work/cmsa-media-lifecycle` only.
- `workbench/tasks/cmsa-media-lifecycle-2026-09-09.md` for the control/evidence record.
- `workbench/labs/chattanooga-cms-admin/candidate/includes/class-cmsa-media.php` and `class-cmsa-media-abilities.php` only after runtime evidence admits a concrete operation.
- `workbench/labs/chattanooga-cms-admin/candidate/includes/class-cmsa-media-lifecycle.php` for the separately evidenced destructive lifecycle state/replacement implementation, keeping file-heavy destructive logic out of the ordinary media inventory/metadata service.
- Existing candidate bootstrap/registry fixtures only if required to load or register an admitted media lifecycle operation.
- New or extended disposable probes under `workbench/labs/chattanooga-cms-admin/probes/` and a task-owned workflow if needed for exact media lifecycle verification.

## Exclusion set

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live Media Library item, live post/page, or live file mutation.
- No arbitrary filesystem, shell, database, postmeta, or remote-command endpoint.
- No arbitrary remote URL fetch.
- No media deletion/replacement ability until the exact WordPress native contract and rollback/reference behavior are execution-verified.
- No silent deletion of referenced media, no reference rewriting by assumption, and no broken featured-image/content relationship accepted as a successful result.
- No weakening of existing 8 MiB upload, MIME validation, exact-state, REST-isolation, permission, or rollback boundaries.
- No unrelated content, Events Manager, WooCommerce, Marketplace, Weekend Feature, member, navigation, plugin/theme, or production-source changes.

## Evidence

- `workbench/mars` reconciliation checkpoint `606ccb81f4fb7b02ce9bd2ed5bd5758c2b2804a7` is execution-verified by Mars Workbench Integrity run `34385296225` and contains the 97-ability Chattanooga CMS Admin candidate.
- Existing `CMSA_Media` already provides bounded media list/get, validated base64 create, exact-state metadata update, and exact-state featured-image set/clear.
- Existing media creation already uses native `media_handle_sideload()` and removes a just-created failed-verification attachment through `wp_delete_attachment( $id, true )`, but that rollback use does not establish a safe public permanent-delete contract for pre-existing attachments.
- The workbench roadmap explicitly identifies media upload/replace/delete as the remaining Layer B media lifecycle surface.
- Existing queue state identifies media replacement/permanent deletion as a non-automatic target requiring its own evidence and design.
- WordPress 7.1 media lifecycle run `34385984944` execution-verified permanent attachment deletion on disposable fixtures: the attachment post, primary file, generated derivatives, and attachment metadata are removed; featured-image relationships are cleared; the parent/consumer post is preserved; direct media URLs embedded in post content are not rewritten and therefore become broken references after deletion; native delete permission is object-scoped.
- WordPress core source confirms `wp_delete_attachment()` clears `_thumbnail_id` relationships and invokes `wp_delete_attachment_files()`, while ordinary post-content references are not rewritten. Core also does not propagate the boolean result from `wp_delete_attachment_files()` through `wp_delete_attachment()`; a guarded delete implementation must independently verify file removal rather than treating the attachment-post return value as sufficient evidence.
- Initial replacement diagnostic run `34387603787` failed only because the probe incorrectly treated a false return from `wp_update_attachment_metadata()` as proof of persistence failure. WordPress 7.1 source documents that this function also returns false when the supplied metadata equals the existing value, and image sub-size generation itself persists metadata during generation. The probe was repaired to verify exact metadata readback instead of relying on that ambiguous return value.
- Corrected replacement run `34387997758` passed the complete existing 97-ability registry, existing media permission/transaction regressions, deletion contract, and same-path replacement contract.
- The corrected replacement runtime established: same attachment ID, same attachment URL/path, same parent, and same MIME are preserved; featured-image and direct content-URL references remain intact; replacement dimensions/primary hash change as expected; WordPress generates a changed derivative set but leaves at least one obsolete derivative requiring explicit old-only cleanup; exact primary/derivative bytes plus metadata can be restored; new-only derivative files can be removed during rollback; a deliberately corrupted post-replacement metadata state was successfully rolled back to the exact prior file hashes, metadata, references, and candidate state token.
- The same-path replacement fixture intentionally excludes `_wp_attachment_backup_sizes` and companion-file metadata (`original_image`, `source_image`, `animated_video`, `animated_video_poster`). Those complex attachment states remain outside the admitted replacement contract until separately execution-verified.
- State-token contract run `34388584307` passed and proved the existing generic media `state_token` is insufficient for destructive file replacement conflict control: a primary-file byte change remained invisible when file size and mtime were held constant, and a direct attachment-metadata change was also invisible. Both mutations were restored exactly after the probe.
- The direct repair line is to preserve the existing lightweight generic `state_token` contract for list/metadata operations and add a separate detailed-read lifecycle state token for destructive media lifecycle operations. That token must bind the generic state to exact attachment metadata, backup-size metadata, and hashes of the primary plus every metadata-owned derivative/companion/backup file. Keeping the lifecycle digest on exact detailed reads avoids turning bounded 100-item media listing into bulk file hashing while still providing exact replacement conflict control.
- The dedicated lifecycle service is an architectural separation, not a substitute path: ordinary inventory/metadata logic remains in `CMSA_Media`; file-heavy exact-state and replacement logic shares one source-owned service so the token and mutation cannot drift or be duplicated across the ability-registration layer.
- Lifecycle-token implementation checkpoint `1951a7c18eec4026dfbf98dc715f3a8c51e6883e` adds `CMSA_Media_Lifecycle`, loads it from the candidate bootstrap, and augments exact `get-media` output with `lifecycle_state_token`, `lifecycle_file_count`, and `lifecycle_complete` without adding file hashing to bounded list-media inventory.
- WordPress 7.1 run `34389611120` passed the complete 97-ability registry, existing media permission and transaction probes, native deletion/replacement discovery probes, and the dedicated lifecycle-token probe. The lifecycle probe proved the generic token remains blind to same-size/same-mtime primary-byte mutation, derivative-byte mutation, attachment metadata mutation, and backup-size metadata mutation while the new lifecycle token detects all four and returns to the identical original value after exact restoration. The verified ordinary-image fixture enumerated three owned files.

## Mutation set

1. Create this dedicated source branch and task record from verified workbench checkpoint `606ccb81f4fb7b02ce9bd2ed5bd5758c2b2804a7`. COMPLETE.
2. Inspect current media candidate architecture and existing media tests. COMPLETE.
3. Add disposable WordPress runtime diagnostics for native permanent attachment deletion and replacement-relevant file/metadata/reference behavior without admitting a new ability. COMPLETE.
4. Verify object-scoped permissions, attachment identity, primary/derived files, attachment metadata, featured-image references, parent state, deletion/replacement cleanup behavior, and exact lifecycle conflict state. COMPLETE for the ordinary-image replacement contract and native deletion semantics.
5. Define the narrowest safe lifecycle contract from execution evidence. COMPLETE for same-ID/same-path/same-MIME ordinary image replacement without backup/companion-file metadata. Permanent deletion remains unadmitted because direct content references and independent file-removal verification require a stricter protection contract.
6. Implement and execution-verify only the admitted guarded ordinary-image replacement operation using the lifecycle token, centralized existing upload validation, exact rollback snapshot, old-only derivative cleanup, protected identity/path/reference invariants, stale/no-change guards, and bounded auditing. IN PROGRESS.
7. Re-run all existing Chattanooga CMS Admin regressions and integrate into `workbench/mars` only after source/runtime acceptance and explicit integration authorization if required by the current target boundary. PENDING.

## Risk set

- WordPress permanent attachment deletion can remove the attachment post, original file, generated derivatives, metadata, and relationships; accepting partial cleanup could leave orphaned files or broken references.
- Existing posts/pages or other objects may reference an attachment as a featured image or by URL/content; deleting or replacing the file without an exact reference policy can break the site.
- Same-path replacement preserves attachment identity and URL in the verified ordinary-image fixture, but complex images with backup/original/source/video companion metadata are not yet covered and must fail closed until separately verified.
- WordPress replacement metadata generation can leave obsolete derivatives from the old dimensions; candidate replacement must enumerate and explicitly remove old-only owned files after successful generation/readback.
- File replacement can overwrite the primary file before final verification; exact prior primary/derivative bytes and metadata therefore require a recoverable checkpoint before mutation, with removal of new-only files and exact restoration on any failed acceptance test.
- The generic media state token is execution-verified blind to same-size/same-mtime primary-byte changes and attachment-metadata-only changes. It must not be reused as the sole destructive replacement guard.
- Computing complete file hashes on every media list item would materially expand read I/O; destructive lifecycle conflict state is therefore bound by the dedicated exact-item token rather than weakening exactness or imposing bulk hashing on ordinary inventory reads.
- Attachment capabilities are mapped/object-scoped; `upload_files` alone must not be assumed sufficient for destructive operations.
- A permanent delete is inherently destructive in production. Source/runtime testing may use disposable fixtures, but any future live use requires separate target-specific destructive authorization.

## Rollback point

- Branch base: `606ccb81f4fb7b02ce9bd2ed5bd5758c2b2804a7`.
- Current pre-replacement implementation checkpoint: `1951a7c18eec4026dfbf98dc715f3a8c51e6883e`.
- Pre-replacement runtime rollback is the exact attachment-owned file set plus file bytes/hashes/mtimes and exact attachment metadata captured before mutation; rollback removes new-only files and restores the prior owned set and metadata before readback verification.
- All runtime fixtures must be disposable and destroyed after verification.
- No production state is included in this source transaction.

## Acceptance tests

- [x] Exact native WordPress permanent-attachment deletion behavior is execution-verified, including primary file, generated derivatives, attachment metadata/post state, and relevant relationship effects.
- [x] Native permanent-delete permission/object scope is execution-verified for administrator, owner/non-owner scope where material, and anonymous access.
- [x] Same-ID/same-path ordinary-image replacement semantics and exact file/metadata rollback are execution-verified before a replacement ability is admitted.
- [x] A dedicated destructive lifecycle state token detects primary/owned-file hash changes and attachment/backup metadata changes that the generic token does not detect, and restores to the identical value after exact rollback.
- [ ] The admitted replacement ability requires exact prior lifecycle state, fails closed on stale or incomplete lifecycle state, rejects no-change payloads, and accepts only the verified same-MIME ordinary-image contract.
- [ ] The admitted replacement operation preserves attachment ID, attached-file path/URL, MIME, parent, featured-image relationships, and direct same-URL content references while removing obsolete old-only derivatives.
- [ ] Candidate replacement verification failure produces a proven exact file/metadata/lifecycle-token rollback with no new-only derivative residue.
- [ ] Existing media upload validation remains one centralized implementation and retains the 8 MiB, extension/type, payload MIME, and no-remote-fetch boundaries for both create and replacement inputs.
- [ ] Existing five media abilities retain their current validation, privacy, REST-isolation, and rollback behavior after replacement admission.
- [ ] Complete candidate regression suite passes with the intentionally increased registry/media ability count.
- [x] No production mutation or deployment occurs.

## Source position

- Source branch: `work/cmsa-media-lifecycle`.
- Observed implementation checkpoint before guarded replacement coding: `1951a7c18eec4026dfbf98dc715f3a8c51e6883e`.
- Production state: unchanged and not deployed; production remains independently verified at 82 exposed Chattanooga CMS Admin abilities.

## Production state

NOT_DEPLOYED

Production remains independently verified at 82 exposed abilities. This task is source/runtime engineering only.

## Result journal

- 2026-09-09: Opened from verified workbench checkpoint `606ccb81f4fb7b02ce9bd2ed5bd5758c2b2804a7`. Selected media lifecycle as the next concrete Layer B gap because list/get/create/metadata/featured-image support already exists while replacement/permanent deletion remain explicitly unimplemented. No lifecycle ability has been admitted yet; exact native semantics and reference/rollback behavior remain the current evidence gate.
- 2026-09-09: Run `34385984944` passed native permanent-delete contract discovery. The delete removes attachment DB/file state and clears featured-image relationships but leaves direct content URLs unchanged, so permanent deletion is not yet safe to expose as a generic ability. WordPress core also requires independent file-absence verification because attachment deletion does not surface the file-helper boolean.
- 2026-09-09: Added a same-path replacement diagnostic. First run `34387603787` failed because the probe treated `wp_update_attachment_metadata() === false` as a persistence failure. Direct WordPress 7.1 source inspection showed false also means unchanged metadata and sub-size generation persists metadata incrementally; corrected the probe to use exact readback instead of the ambiguous return value.
- 2026-09-09: Corrected run `34387997758` passed. Ordinary image replacement can preserve attachment identity, URL/path, MIME, parent, featured-image relationship, and direct content URL while changing primary bytes/dimensions. The diagnostic proved obsolete old derivatives require explicit cleanup and proved exact rollback of primary/derivative files, metadata, state token, and protected references after an injected verification fault. Complex backup/companion-image states remain fail-closed candidates pending separate evidence. No new media ability has been admitted yet.
- 2026-09-09: Run `34388584307` proved the current generic media token has two destructive-conflict blind spots: same-size/same-mtime primary file changes and attachment-metadata-only changes. The probe restored both exactly and left the 97-ability baseline green. Selected direct repair: add an exact-item lifecycle digest binding attachment metadata and all owned-file hashes; do not overload ordinary list reads with bulk hashing and do not admit replacement until this new conflict guard is execution-verified.
- 2026-09-09: Checkpoint `1951a7c18eec4026dfbf98dc715f3a8c51e6883e` implemented the dedicated lifecycle digest and exact `get-media` exposure. Run `34389611120` passed and independently proved the new lifecycle token detects primary, derivative, attachment-metadata, and backup-size changes missed by the generic token, then returns to its identical prior value after exact restoration. Guarded ordinary-image replacement implementation is now the active source-only step; permanent deletion remains unadmitted.
