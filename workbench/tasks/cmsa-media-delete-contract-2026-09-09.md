# Task: cmsa-media-delete-contract-2026-09-09

Status: CONTRACT_DISCOVERY

## Objective

Determine whether Chattanooga CMS Admin can expose a bounded permanent media-deletion ability without silently breaking WordPress references or weakening the candidate's existing privacy, object-scope, exact-state, filesystem-verification, and no-arbitrary-storage boundaries.

## Target set

- Source branch `work/cmsa-media-delete-contract` from verified workbench checkpoint `165b8bad09942b2f644762566327ccb575525d1d`.
- Disposable WordPress 7.1 reference diagnostics only during contract discovery.
- `workbench/labs/chattanooga-cms-admin/probes/media-delete-reference-contract-cli.php`.
- `.github/workflows/cmsa-media-delete-contract-lab.yml`.
- Candidate source only if later execution evidence establishes a complete bounded delete contract; no delete ability is admitted by the discovery increment.

## Exclusion set

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live media/content/file mutation, or deployment.
- No generic SQL, arbitrary postmeta/options enumeration, arbitrary filesystem endpoint, remote-command endpoint, or remote URL fetch.
- No assumption that WordPress attachment deletion rewrites or removes every consumer reference.
- No silent clearing of known consumer relationships accepted as a safe generic delete contract.
- No media delete ability until direct references, relationship effects, complete owned-file absence, permission scope, and irreversibility are execution-verified.
- No changes to media replacement, upload, content, Events Manager, Weekend Feature, WooCommerce, Marketplace, navigation, member, maintenance, or unrelated source behavior.

## Evidence

- Current verified `workbench/mars` checkpoint `165b8bad09942b2f644762566327ccb575525d1d` contains 98 Chattanooga CMS Admin abilities including six execution-verified media abilities; production remains separately verified at 82 exposed abilities.
- Prior WordPress 7.1 deletion run `34385984944` proved permanent attachment deletion removes the attachment post, primary file, generated derivatives, and attachment metadata; clears featured-image relationships; preserves the parent/consumer post; and leaves a direct media URL in post content unchanged and therefore broken.
- WordPress core deletion invokes attachment-file cleanup but its public attachment-delete return does not independently prove every owned file was removed; a guarded contract must verify absence itself.
- The existing candidate intentionally does not expose arbitrary postmeta, options, SQL, filesystem, or generic storage inspection/mutation surfaces.
- The unresolved question is whether a bounded reference-protection contract can be complete enough to justify permanent deletion without crossing those boundaries.

## Planned mutation set

1. Create this dedicated branch and control record from the verified 98-ability workbench checkpoint. COMPLETE.
2. Add a disposable WordPress diagnostic that establishes multiple real consumer-reference forms before native permanent deletion: featured image, direct attachment URL in post content, core gallery-shortcode attachment ID, custom postmeta ID/URL, and option ID/URL. IN PROGRESS.
3. Permanently delete only the disposable attachment using native WordPress and verify exact attachment/file removal plus the state of every consumer reference and unrelated parent/consumer object. PENDING.
4. Use the execution result to decide whether a complete bounded delete protection policy exists inside the current candidate boundaries. PENDING.
5. Admit no source ability if evidence shows generic deletion would require arbitrary storage scanning or could still silently leave unresolved references. PENDING.

## Risks

- A generic delete ability may remove files while leaving durable references in content, metadata, options, plugin data, or theme data.
- Scanning arbitrary postmeta/options/plugin tables to approximate a reverse-reference graph would violate existing bounded privacy and generic-storage exclusions and still may not be exhaustive.
- Treating WordPress's attachment-post deletion result as complete file-removal proof can miss file cleanup failures.
- A production permanent delete is inherently irreversible without a separately designed backup/restore transaction and would be A5 even if a source ability were eventually admitted.

## Rollback point

- Branch base and source rollback point: `165b8bad09942b2f644762566327ccb575525d1d`.
- All destructive execution occurs only against disposable WordPress fixtures that are discarded after each run.
- No production rollback is needed because production is excluded.

## Acceptance tests

- [ ] Native deletion independently verifies attachment-post absence and absence of every previously enumerated attachment-owned file.
- [ ] Featured-image behavior is verified exactly rather than inferred.
- [ ] Direct URL and core gallery-ID content reference behavior is verified exactly.
- [ ] Arbitrary postmeta ID/URL and option ID/URL reference behavior is verified exactly.
- [ ] Parent and consumer objects remain present and their unrelated state remains unchanged.
- [ ] A bounded delete-contract decision is made from execution evidence without adding an incomplete reference scan or workaround.
- [ ] Complete 98-ability registry remains unchanged during discovery.
- [x] Production remains unchanged.

## Production state

NOT_DEPLOYED
