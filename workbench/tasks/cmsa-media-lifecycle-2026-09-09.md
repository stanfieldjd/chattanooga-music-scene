# Task: cmsa-media-lifecycle-2026-09-09

Status: CONTRACT_DISCOVERY

## Objective

Extend Chattanooga CMS Admin from its verified 97-ability workbench position with the remaining bounded WordPress media lifecycle operations only after their exact native deletion/replacement semantics, permission model, reference effects, rollback requirements, and file/metadata behavior are execution-verified in disposable WordPress.

## Target set

- Source branch `work/cmsa-media-lifecycle` only.
- `workbench/tasks/cmsa-media-lifecycle-2026-09-09.md` for the control/evidence record.
- `workbench/labs/chattanooga-cms-admin/candidate/includes/class-cmsa-media.php` and `class-cmsa-media-abilities.php` only after runtime evidence admits a concrete operation.
- Existing candidate bootstrap/registry fixtures only if required to register an admitted media ability.
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

## Mutation set

1. Create this dedicated source branch and task record from verified workbench checkpoint `606ccb81f4fb7b02ce9bd2ed5bd5758c2b2804a7`. COMPLETE.
2. Inspect current media candidate architecture and existing media tests. IN PROGRESS.
3. Add disposable WordPress runtime diagnostics for native permanent attachment deletion and replacement-relevant file/metadata/reference behavior without admitting a new ability. PENDING.
4. Verify object-scoped permissions, attachment identity, primary/derived files, attachment metadata, featured-image references, parent state, and deletion/replacement cleanup behavior. PENDING.
5. Define the narrowest safe lifecycle contract from execution evidence. PENDING.
6. Add only operations justified by that contract, with exact-state guards, explicit confirmation for permanent deletion, reference protection, verification, rollback where technically valid, and bounded auditing. PENDING.
7. Re-run all existing Chattanooga CMS Admin regressions and integrate into `workbench/mars` only after source/runtime acceptance and explicit integration authorization if required by the current target boundary. PENDING.

## Risk set

- WordPress permanent attachment deletion can remove the attachment post, original file, generated derivatives, metadata, and relationships; accepting partial cleanup could leave orphaned files or broken references.
- Existing posts/pages or other objects may reference an attachment as a featured image or by URL/content; deleting or replacing the file without an exact reference policy can break the site.
- Replacement may preserve attachment identity while changing file path, MIME type, derivative metadata, or URL, or may require a new attachment identity. That behavior is UNKNOWN until verified.
- File replacement can create orphaned originals/derivatives if cleanup fails or overwrite an existing file before a recoverable checkpoint exists.
- Attachment capabilities are mapped/object-scoped; `upload_files` alone must not be assumed sufficient for destructive operations.
- A permanent delete is inherently destructive in production. Source/runtime testing may use disposable fixtures, but any future live use requires separate target-specific destructive authorization.

## Rollback point

- Branch base: `606ccb81f4fb7b02ce9bd2ed5bd5758c2b2804a7`.
- All runtime fixtures must be disposable and destroyed after verification.
- No production state is included in this source transaction.

## Acceptance tests

- [ ] Exact native WordPress permanent-attachment deletion behavior is execution-verified, including primary file, generated derivatives, attachment metadata/post state, and relevant relationship effects.
- [ ] Exact native permission/object-scope requirements are execution-verified for administrator, owner-scoped/non-owner users where material, and anonymous access.
- [ ] Replacement semantics are execution-verified before any replacement ability is admitted.
- [ ] Any admitted destructive operation requires exact prior state plus explicit permanent-delete confirmation and fails closed on stale state.
- [ ] Any admitted operation protects or explicitly governs known references rather than silently breaking them.
- [ ] Verification failure produces a proven rollback or the operation is not admitted.
- [ ] Existing five media abilities retain their current validation, privacy, REST-isolation, and rollback behavior.
- [ ] Complete 97-ability regression baseline remains green until an admitted ability intentionally changes the registry count.
- [ ] No production mutation or deployment occurs.

## Production state

NOT_DEPLOYED

Production remains independently verified at 82 exposed abilities. This task is source/runtime engineering only.

## Result journal

- 2026-09-09: Opened from verified workbench checkpoint `606ccb81f4fb7b02ce9bd2ed5bd5758c2b2804a7`. Selected media lifecycle as the next concrete Layer B gap because list/get/create/metadata/featured-image support already exists while replacement/permanent deletion remain explicitly unimplemented. No lifecycle ability has been admitted yet; exact native semantics and reference/rollback behavior remain the current evidence gate.
