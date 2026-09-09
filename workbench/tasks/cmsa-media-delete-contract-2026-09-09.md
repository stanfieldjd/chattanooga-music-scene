# Task: cmsa-media-delete-contract-2026-09-09

Status: COMPLETE — DELETE_NOT_ADMITTED

## Objective

Determine whether Chattanooga CMS Admin can expose a bounded permanent media-deletion ability without silently breaking WordPress references or weakening the candidate's existing privacy, object-scope, exact-state, filesystem-verification, and no-arbitrary-storage boundaries.

## Target set

- Source branch `work/cmsa-media-delete-contract` from verified workbench checkpoint `165b8bad09942b2f644762566327ccb575525d1d`.
- Disposable WordPress 7.1 reference diagnostics only during contract discovery.
- `workbench/labs/chattanooga-cms-admin/probes/media-delete-reference-contract-cli.php`.
- `.github/workflows/cmsa-media-delete-contract-lab.yml`.
- Candidate source only if execution evidence establishes a complete bounded delete contract. No candidate source or delete ability was admitted because the contract did not pass that gate.

## Exclusion set

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live media/content/file mutation, or deployment.
- No generic SQL, arbitrary postmeta/options enumeration, arbitrary filesystem endpoint, remote-command endpoint, or remote URL fetch.
- No assumption that WordPress attachment deletion rewrites or removes every consumer reference.
- No silent clearing of known consumer relationships accepted as a safe generic delete contract.
- No media delete ability without complete bounded reference protection and independent file-removal verification.
- No changes to media replacement, upload, content, Events Manager, Weekend Feature, WooCommerce, Marketplace, navigation, member, maintenance, or unrelated source behavior.

## Evidence

- Verified `workbench/mars` checkpoint `165b8bad09942b2f644762566327ccb575525d1d` contains 98 Chattanooga CMS Admin abilities including six execution-verified media abilities; production remains separately verified at 82 exposed abilities.
- Prior WordPress 7.1 deletion run `34385984944` proved permanent attachment deletion removes the attachment post, primary file, generated derivatives, and attachment metadata; clears featured-image relationships; preserves the parent/consumer post; and leaves a direct media URL in post content unchanged and therefore broken.
- WordPress core deletion invokes attachment-file cleanup but its public attachment-delete return does not independently prove every owned file was removed; a guarded contract must verify absence itself.
- The existing candidate intentionally does not expose arbitrary postmeta, options, SQL, filesystem, or generic storage inspection/mutation surfaces.
- Discovery branch checkpoint `37eaedbd4b647b19252d19e15efb5e923ee7d48b` added only a disposable reference-contract probe and workflow; the candidate registry/source remained unchanged.
- WordPress 7.1 run `34399559717` passed the unchanged 98-ability registry and the new reference-contract probe. Native permanent deletion independently removed the attachment post and every enumerated primary/derivative file and cleared the featured-image relationship while preserving the parent and all consumer posts.
- The same run proved native deletion leaves six durable reference forms unchanged: direct attachment URL in post content, core `[gallery ids="…"]` attachment-ID content, custom postmeta attachment ID, custom postmeta attachment URL, option attachment ID, and option attachment URL.
- Exact runtime output: `media-delete-reference-contract-cli: PASS attachment=absent files=absent featured=cleared parent=preserved unmanaged=post-content-url,gallery-id,postmeta-id,postmeta-url,option-id,option-url consumers=preserved reference-graph=not-native-complete`.
- Therefore WordPress core does not provide a complete native reverse-reference cleanup contract for permanent attachment deletion. A generic candidate delete guard would have to scan or understand arbitrary content, postmeta, options, plugin/theme storage, and potentially externalized plugin data to claim completeness.
- Such a generic scan would violate the candidate's existing bounded privacy/generic-storage exclusions and still would not establish exhaustive coverage for plugin/theme-specific storage. Adding a partial scan would be a workaround rather than a direct complete contract.

## Planned mutation set

1. Create this dedicated branch and control record from the verified 98-ability workbench checkpoint. COMPLETE.
2. Add a disposable WordPress diagnostic establishing multiple real consumer-reference forms before native permanent deletion: featured image, direct attachment URL in post content, core gallery-shortcode attachment ID, custom postmeta ID/URL, and option ID/URL. COMPLETE.
3. Permanently delete only the disposable attachment using native WordPress and verify exact attachment/file removal plus the state of every consumer reference and unrelated parent/consumer object. COMPLETE in run `34399559717`.
4. Use the execution result to decide whether a complete bounded delete protection policy exists inside the current candidate boundaries. COMPLETE — it does not.
5. Admit no source ability because generic deletion would require arbitrary storage scanning and still could silently leave unresolved references. COMPLETE — candidate remains at 98 abilities; no delete ability was added.

## Decision

Permanent media deletion is **not admitted** to Chattanooga CMS Admin under the current architecture. This is an evidence-based product boundary, not a postponed implementation shortcut. The direct safe architecture is to keep deletion absent unless a future concrete ownership/reference model can provide an exhaustive bounded reverse-reference contract without arbitrary storage inspection.

The existing guarded media replacement operation remains the supported destructive file-lifecycle operation because it preserves attachment identity and URL/path and therefore does not invalidate the reference forms proven above.

## Risks

- A generic delete ability would remove files while leaving durable references in content, metadata, options, plugin data, or theme data.
- Scanning arbitrary postmeta/options/plugin tables to approximate a reverse-reference graph would violate existing bounded privacy and generic-storage exclusions and still may not be exhaustive.
- Treating WordPress's attachment-post deletion result as complete file-removal proof can miss file cleanup failures; the diagnostic independently checked every enumerated owned file.
- A production permanent delete would be inherently irreversible and A5 even if a different future source architecture were ever admitted.

## Rollback point

- Branch base and source rollback point: `165b8bad09942b2f644762566327ccb575525d1d`.
- All destructive execution occurred only against disposable WordPress fixtures that were discarded after the run.
- No production rollback is needed because production was excluded.

## Acceptance tests

- [x] Native deletion independently verifies attachment-post absence and absence of every previously enumerated attachment-owned file.
- [x] Featured-image behavior is verified exactly rather than inferred.
- [x] Direct URL and core gallery-ID content reference behavior is verified exactly.
- [x] Arbitrary postmeta ID/URL and option ID/URL reference behavior is verified exactly.
- [x] Parent and consumer objects remain present and their unrelated state remains unchanged.
- [x] A bounded delete-contract decision is made from execution evidence without adding an incomplete reference scan or workaround.
- [x] Complete 98-ability registry remains unchanged during discovery.
- [x] Production remains unchanged.

## Production state

NOT_DEPLOYED

## Result journal

- 2026-09-09: Opened from verified 98-ability workbench checkpoint `165b8bad09942b2f644762566327ccb575525d1d` with production and candidate mutation excluded until a complete delete contract could be proved.
- 2026-09-09: Run `34399559717` passed. Native deletion removed the attachment and all enumerated files and cleared the featured image, but direct URL content, core gallery-ID content, custom postmeta ID/URL, and option ID/URL references all persisted exactly while their consumer objects remained present.
- 2026-09-09: Decision closed: no permanent media-delete ability will be added under the current bounded architecture. A partial generic reference scan would violate existing exclusions and still fail to prove completeness. Candidate registry remains 98; production remains 82 and unchanged.
