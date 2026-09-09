# Task: cmsa-marketplace-listings-2026-09-09

Status: WORKBENCH_INTEGRATED_VERIFIED

## Objective

Extend Chattanooga CMS Admin with a bounded marketplace-listing administration surface for the actual Chattanooga marketplace stack, beginning with exact AWP Classifieds listing-model inspection and admitting only the minimum non-sensitive read contract established by execution evidence before any listing mutation is designed or added.

## Target set

- Source branch `work/cmsa-marketplace-listings` only for implementation and pre-integration verification.
- `workbench/mars` only after the user explicitly authorized integration of the verified slice.
- `workbench/tasks/cmsa-marketplace-listings-2026-09-09.md` for the control/evidence record.
- `workbench/labs/chattanooga-cms-admin/probes/` for disposable AWP Classifieds runtime/model, read-contract, fixture-contract, and admitted-read validation.
- `.github/workflows/cmsa-marketplace-listings-lab.yml` for WordPress 7.1 + AWP Classifieds 4.4.8 + WooCommerce 11.0.1 runtime verification.
- Chattanooga CMS Admin candidate files only after the exact listing read model, permissions, identifiers, state boundaries, and safe native APIs are execution-verified.
- Exact expected-ability fixture only after a bounded read ability is admitted.
- The exact current `main` Chattanooga Music Marketplace 0.1.1 source may exist on this task branch and workbench byte-for-byte for coexistence testing; its presentation implementation is not an edit target.

## Exclusion set

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live classified listing, live WooCommerce product/order/customer, payment, checkout, or marketplace transaction mutation.
- No modification of AWP Classifieds, WooCommerce, CMS Market Checkout Bridge, or Chattanooga Music Marketplace presentation behavior.
- No guessed direct SQL listing mutations, generic postmeta/CPT backdoor, arbitrary database endpoint, shell endpoint, remote-command endpoint, or public REST relaxation.
- No listing creation/update/delete/publication ability until a separate mutation contract is justified by runtime evidence and explicitly admitted to a later task.
- No checkout, payment, order, customer, seller payout, tax, shipping, coupon, or other financial administration surface in this initial slice.
- No output of listing access/edit keys, seller/contact email, seller/contact phone, seller/contact name, IP address, payment email, payment status/term, raw user/member records, payment rows, or arbitrary listing metadata.
- No customer-facing `WooCommerce`, supplier, or equivalent commerce-engine label may be introduced into Marketplace presentation by this workstream.
- No assumption that CMS Market Checkout Bridge internals are known. Its live activation/version may establish deployment context only; source/API behavior remains UNKNOWN until authoritative source or runtime evidence is available.

## Evidence

- Verified workbench rollback/base commit `42bb803e3cbb0a22ea06a44584c617382d9ae7f4` has the reconciled 95-ability Chattanooga CMS Admin candidate and passed Mars Workbench Integrity run `34371917853`.
- Current production read-only plugin inventory reports AWP Classifieds `4.4.8`, WooCommerce `11.0.1`, and CMS Market Checkout Bridge `0.1.1` active alongside Chattanooga CMS Admin `0.1.0`.
- The workbench roadmap identifies WooCommerce / marketplace product and listing inventory plus marketplace bridge/custom-plugin state as a concrete Chattanooga capability layer.
- WooCommerce product administration is already separately typed and execution-verified at 95 workbench abilities; marketplace listings therefore must not be collapsed into generic WooCommerce products or generic WordPress posts.
- WordPress.org identifies AWP Classifieds 4.4.8 as the current installed-version match and documents classified listing inventory plus its own payment/listing lifecycle. The upstream Strategy11 repository is public; third-party source remains an immutable dependency for this workstream.
- `site-plugins/cms-market-checkout-bridge` is not present at the checked `workbench/mars` repository path. Bridge internals are therefore not treated as known or reconstructed from memory.
- Runtime workflow `34372812403`, job `102538018793`, passed on WordPress 7.1 + PHP 8.2 + MySQL 8 + AWP Classifieds 4.4.8 + WooCommerce 11.0.1 at branch checkpoint `78dba0d0c023be312d1f17476d0de4df6288667d`.
- The first runtime established `AWPCP_LISTING_POST_TYPE=awpcp_listing`, with registered post type `awpcp_listing`, public=true, map_meta_cap=true, capability type `awpcp_classified_ad`.
- The runtime established hierarchical taxonomy `awpcp_listing_category` attached to `awpcp_listing`; term management/edit/delete uses `manage_categories` and relationship assignment uses `edit_posts`.
- The runtime administrator has `edit_awpcp_classified_ads`, `edit_others_awpcp_classified_ads`, and `manage_awpcp`.
- AWP 4.4.8 exposes `AWPCP_ListingsCollection` with bounded listing collection/get methods, `AWPCP_ListingAuthorization` with current-user listing edit/manage checks, `AWPCP_ListingRenderer` with explicit listing getters, and `AWPCP_ListingsAPI` with native lifecycle/mutation methods. Mutation method existence is evidence only and is not admission to this task.
- Fresh AWP 4.4.8 created six plugin-owned auxiliary tables: `wp_awpcp_ad_regions`, `wp_awpcp_adfees`, `wp_awpcp_admeta`, `wp_awpcp_credit_plans`, `wp_awpcp_payments`, and `wp_awpcp_tasks`. No actual `wp_awpcp_ads` table exists in the fresh runtime even though legacy constant `AWPCP_TABLE_ADS` remains defined. The current listing identity therefore must not be implemented through legacy `wp_awpcp_ads` direct SQL.
- `AWPCP_ListingRenderer` exposes both useful public-state getters and private/sensitive getters. Explicitly observed sensitive getters include access key, contact email/name/phone, IP address, payment email/status/term, and user. This materially requires a strict output allowlist rather than serializing the renderer/listing object wholesale.
- AWP payment storage separately contains `payer_email` and transaction/payment state. Payment/customer data remains outside this task.
- First runtime gate exact terminal result: `awpcp-marketplace-model-cli: PASS version=4.4.8 tables=6 functions=250 classes=300 model=discovered-not-yet-admitted`.
- Focused read-contract checkpoint `77a739528ecc5d790ac0b3fd1003364941048e1f` passed workflow `34375072418`, job `102545690078` on the same exact WordPress/AWP/WooCommerce version contract.
- The focused runtime resolved zero-argument factories `awpcp_listings_collection()`, `awpcp_listings_api()`, `awpcp_listing_authorization()`, `awpcp_listing_renderer()`, and `awpcp_query()` to `AWPCP_ListingsCollection`, `AWPCP_ListingsAPI`, `AWPCP_ListingAuthorization`, `AWPCP_ListingRenderer`, and `AWPCP_Query`; `awpcp_listings_query()` does not exist.
- `AWPCP_ListingsCollection::get($listing_id)`, `find_all_by_id($identifiers)`, `find_listings($query = array())`, `find_enabled_listings($query_vars = array())`, and `find_valid_listings($query_vars = array())` are exact reflected 4.4.8 methods.
- The exact post-type capability map includes object capabilities `edit_awpcp_classified_ad`, `read_awpcp_classified_ad`, and `delete_awpcp_classified_ad`; collection-level edit/read-private/publish/delete/create mappings resolve to `edit_others_awpcp_classified_ads` for this plugin version.
- `AWPCP_ListingAuthorization` exposes exact object methods `is_current_user_allowed_to_edit_listing($listing)` and `is_current_user_allowed_to_manage_listing($listing)`, plus `is_current_user_allowed_to_submit_listing()`.
- Safe renderer candidates verified by reflection include title, price, start/end dates, views, website/view URL, public/disabled/expired/featured/flagged/pending/verified/review state, and category IDs. Sensitive getters remain explicitly excluded.
- `AWPCP_ListingRenderer::is_expired()` can perform lifecycle mutation when it detects a non-disabled expired listing, while `has_expired()` is the read-only predicate. Any admitted read surface must use `has_expired()` and must not call the mutating `is_expired()` path.
- Focused probe exact terminal result: `awpcp-marketplace-read-contract-cli: PASS helpers=reflected services=resolved signatures=verified fixture=not-created`.
- Upstream AWP Classifieds tag `v4.4.8` resolves to commit `4088336b0ca5f85a880b26b08e378c6f20c066dc`. Exact source inspection confirmed `AWPCP_ListingsAPI::create_listing()` routes through `wp_insert_post` plus AWP-owned metadata/term lifecycle, `AWPCP_ListingsCollection::get()` resolves the current listing through `get_post()` and enforces `awpcp_listing`, collection queries route through native `WP_Query`, and `AWPCP_ListingAuthorization` distinguishes moderator/admin authority from owner-scoped access.
- AWP 4.4.8 itself uses the bounded inventory status family `publish`, `draft`, `pending`, `private`, `future`, `trash`, `disabled`, with upgrade paths also recognizing `auto-draft`; that exact family is the evidence basis for an admitted list filter rather than a guessed generic status set.
- Fixture-contract checkpoint `ad669fb64ebcaa0dad5b049e57681cd6ad25a763` passed workflow `34376249894`, job `102549677652`, including syntax, exact-version installation, model discovery, reflection discovery, disposable fixture execution, cleanup, and environment teardown.
- The native AWP 4.4.8 fixture creation returned a `WP_Post` with a positive WordPress post ID, post type `awpcp_listing`, default status `disabled`, and the exact fixture owner as `post_author`. The stable listing identifier for the verified read contract is therefore the WordPress post ID.
- `AWPCP_ListingsCollection::get($id)` returned the same `WP_Post`. A bounded `find_listings()` query constrained by `awpcp_listing`, exact status, `post__in`, and `posts_per_page=1` returned exactly one matching `WP_Post`; `get_last_query()` confirmed the same bounded `WP_Query` contract.
- Runtime authorization was execution-verified on the real fixture. Administrator: plugin edit/manage=true, `manage_awpcp=true`, object read/edit=true. Owner subscriber: plugin edit/manage=true, `manage_awpcp=false`, object read=true, object edit=false. Non-owner subscriber and anonymous: plugin edit/manage=false, `manage_awpcp=false`, object read/edit=false.
- The fixture renderer sample used only title, views, public/disabled/expired/featured state, and category IDs. No contact, access-key, user, IP, or payment values were emitted.
- Fixture cleanup was execution-verified: native AWP deletion returned true, the listing post was absent afterward, collection get rejected the deleted ID, both disposable users were deleted, and the disposable WordPress/MySQL environment was then destroyed.
- Fixture probe exact terminal result: `awpcp-marketplace-fixture-contract-cli: PASS id=wp_post_id collection=wp_post bounded_query=verified authorization=observed cleanup=verified`.
- Production-source `main` advanced to `f1c4c128f29215698a2408880a010e49faccac58` with the source-owned Chattanooga Music Marketplace 0.1.1 plugin. Its implementation interleaves catalog-visible products into the existing AWP listing stream, preserves Marketplace search/category/price/location semantics, and deliberately avoids a separate customer-facing commerce-engine label.
- Compare evidence from the task branch to current `main` showed the new production-source delta consists of exactly six Marketplace files: its workflow, CSS, plugin bootstrap, unified Marketplace class, readme, and tests.
- Merge checkpoint `1b79f3e1036b666c50c93ce6158183a71d90f6b7` has parents `430197605ff856c454f4dd2c0981d02c03e32ff7` and current `main` `f1c4c128f29215698a2408880a010e49faccac58`. The Marketplace bootstrap blob on the task branch exactly matches `main` (`6a8d23bfa8192bd04001df4b4319604311a61d07`), establishing branch coexistence against the authoritative Marketplace source without editing its bytes.
- Candidate checkpoint `8111e184a3a051ebe8ab78d750f02b283787729a` adds exactly two bounded read abilities, `list-marketplace-listings` and `get-marketplace-listing`, through the AWP 4.4.8 native collection/renderer/authorization contract. The candidate registry is 97 abilities. The adapter uses the non-mutating `has_expired()` predicate and does not expose seller/contact/access-key/IP/payment/arbitrary-metadata fields or listing mutation.
- Full regression gate commit `30275636e5934c859ec54b8f17cab3d778258e45` and final source-record checkpoint `8a171a1d0331d3e1e659c06d13ac7733cc4d1102` passed full pre-integration workflow run `34383245023`.
- User authorized workbench integration; PR #11 merged the verified source slice into `workbench/mars` at `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3`, preserving pre-integration workbench commit `42bb803e3cbb0a22ea06a44584c617382d9ae7f4` as the first parent.
- Post-integration validation on merge commit `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3` passed Events Manager `34384047941`, Content Layer `34384047961`, WooCommerce Product `34384047891`, Mars Workbench Integrity `34384047944`, CMS Admin Workbench `34384047900`, and Chattanooga Music Marketplace `34384047916`.

## Mutation set

1. Create this dedicated source branch and task record from the verified 95-ability workbench position. COMPLETE.
2. Add a disposable WordPress 7.1 runtime workflow pinned to AWP Classifieds 4.4.8 and WooCommerce 11.0.1. COMPLETE.
3. Add a read-only runtime probe that records the exact AWP version, listing storage/model APIs, identifiers, relevant registered post types/taxonomies/tables, callable listing helpers/classes, and native permissions/capabilities without changing third-party source. COMPLETE.
4. Add a focused read-contract probe that records exact factory/helper availability and reflected signatures for the listing collection, renderer, authorization and API objects. COMPLETE.
5. Inspect the exact AWP 4.4.8 native listing-creation contract, then add a disposable fixture probe that creates only runtime test state, establishes stable identifier/return-object/query/permission behavior, removes the fixture through the plugin-owned lifecycle, and verifies absence before the job passes. COMPLETE.
6. Reconcile the exact current `main` Chattanooga Music Marketplace source into this task branch without modifying the Marketplace implementation, so coexistence validation uses the actual source-owned presentation layer. COMPLETE.
7. Inspect the existing 95-ability candidate architecture plus the authoritative Marketplace presentation contract, then define the minimum Marketplace listing list/get field allowlist and administrator permission contract without direct SQL mutation, raw postmeta access, unsupported internals, commerce-engine labels, or bridge assumptions. COMPLETE.
8. Add only the minimum typed read abilities justified by the verified contract, with explicit output allowlists and no customer/payment/contact leakage. COMPLETE.
9. Verify exact 4.4.8 dependency fail-closed behavior, version mismatch behavior, administrator-only ability permission scope, object scope, registry uniqueness, public-REST isolation, bounded list/get behavior, and coexistence with Chattanooga Music Marketplace 0.1.1 using disposable fixtures. COMPLETE.
10. Run all existing Chattanooga CMS Admin regression gates before any workbench integration. COMPLETE.
11. Integrate the execution-verified 97-ability candidate into `workbench/mars` only after explicit user authorization. COMPLETE.
12. Re-run all triggered post-integration workbench regressions on the exact merge commit. COMPLETE.

## Risk set

- AWP Classifieds uses a native `awpcp_listing` CPT plus plugin-owned metadata/region/payment support. Treating the listing as an ordinary generic post can omit AWP lifecycle semantics or private metadata boundaries even though its stable WordPress object identity is post-backed.
- Listing objects include seller/contact/payment/private moderation data. Read output must be field-allowlisted and must not expose credentials, access/edit tokens, contact identity, IP addresses, payment state, or unrelated member metadata.
- Legacy table constants remain defined even when their historical table is absent. Using `AWPCP_TABLE_ADS` as current storage would encode stale internals and is prohibited by the verified runtime state.
- CMS Market Checkout Bridge may maintain additional relationship state not represented by AWP or WooCommerce alone. That state must remain UNKNOWN until its exact contract is recovered; no inferred bridge behavior may be encoded.
- AWP version drift can invalidate a typed adapter. The initial contract is pinned to the exact live version 4.4.8.
- AWP exposes legacy globals/functions and a broad native mutation API. Discovery does not authorize every callable API; the admitted candidate surface remains read-only and uses the narrowest execution-verified contract.
- The verified owner-vs-nonowner behavior does not by itself require Chattanooga CMS Admin to expose owner-level marketplace inventory. Permission scope follows the existing candidate architecture and concrete administrative use rather than broadening access by capability coincidence.
- The source-owned Chattanooga Music Marketplace plugin is a direct coexistence dependency for this workstream. CMS Admin administers the underlying listing inventory without replacing its presentation logic, duplicating its WooCommerce interleaving, changing its filter semantics, or adding customer-facing engine labels.

## Rollback point

- Branch/base commit: `42bb803e3cbb0a22ea06a44584c617382d9ae7f4`.
- First runtime-contract checkpoint: `78dba0d0c023be312d1f17476d0de4df6288667d`.
- Focused read-contract checkpoint: `77a739528ecc5d790ac0b3fd1003364941048e1f`.
- Fixture-contract checkpoint: `ad669fb64ebcaa0dad5b049e57681cd6ad25a763`.
- Pre-Marketplace-reconciliation checkpoint: `430197605ff856c454f4dd2c0981d02c03e32ff7`.
- Marketplace-source reconciliation checkpoint: `1b79f3e1036b666c50c93ce6158183a71d90f6b7`.
- Marketplace listing read-ability checkpoint: `8111e184a3a051ebe8ab78d750f02b283787729a`.
- Full-regression gate checkpoint: `30275636e5934c859ec54b8f17cab3d778258e45`.
- Final source-record checkpoint: `8a171a1d0331d3e1e659c06d13ac7733cc4d1102`.
- Workbench integration checkpoint: `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3`.
- Restoration path: return the task branch to the relevant verified source checkpoint or return `workbench/mars` to first parent `42bb803e3cbb0a22ea06a44584c617382d9ae7f4`. No production state is included in this transaction.
- The disposable fixture path deletes the listing through AWP's native lifecycle, verifies post absence and collection rejection, deletes fixture users, and then destroys the disposable WordPress/MySQL environment.

## Acceptance tests

- [x] Disposable WordPress 7.1 activates exact AWP Classifieds 4.4.8 and WooCommerce 11.0.1 without modifying either dependency.
- [x] Probe identifies the current listing as post-backed `awpcp_listing`, establishes `map_meta_cap=true`, and proves the legacy `wp_awpcp_ads` table is absent in the fresh 4.4.8 runtime.
- [x] Probe identifies the relevant AWP listing collection/API/renderer/authorization object families and administrator listing capabilities without assuming a generic WordPress content contract.
- [x] Probe identifies the hierarchical `awpcp_listing_category` relationship and its capability families.
- [x] Candidate design does not depend on unavailable CMS Market Checkout Bridge internals.
- [x] Focused probe establishes exact helper/factory and key method signatures for the 4.4.8 collection, renderer, authorization, and API objects.
- [x] Disposable fixture establishes the stable listing identifier, actual collection return-object behavior, and bounded collection-query semantics needed for list/get.
- [x] Disposable fixture establishes native administrator/owner/nonowner/anonymous object-scope behavior and proves cleanup/absence through the plugin-owned lifecycle.
- [x] Task branch contains the exact current Chattanooga Music Marketplace 0.1.1 source from `main` without implementation edits.
- [x] Admitted list/get abilities return only an explicit non-sensitive field allowlist and fail closed when AWP 4.4.8 is unavailable or incompatible.
- [x] Marketplace coexistence tests remain green and no customer-facing WooCommerce/supplier label is introduced.
- [x] Existing 95-ability candidate and all prior functionality remained unchanged through the runtime-contract and source-reconciliation gates.
- [x] Existing 95-ability regressions remain green after the candidate read abilities are added.
- [x] Exact post-integration workbench regressions pass on the 97-ability merge checkpoint.
- [x] No production mutation or deployment occurs.

## Source position

- Repository: `stanfieldjd/chattanooga-music-scene`
- Source branch: `work/cmsa-marketplace-listings`
- Workbench integration branch: `workbench/mars`
- Verified fixture-contract checkpoint: `ad669fb64ebcaa0dad5b049e57681cd6ad25a763`.
- Marketplace-source reconciliation checkpoint: `1b79f3e1036b666c50c93ce6158183a71d90f6b7`.
- Marketplace listing read-ability checkpoint: `8111e184a3a051ebe8ab78d750f02b283787729a`.
- Full-regression gate checkpoint: `30275636e5934c859ec54b8f17cab3d778258e45`.
- Final source-record checkpoint: `8a171a1d0331d3e1e659c06d13ac7733cc4d1102`.
- Workbench integration checkpoint: `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3`.

## Production state

NOT_DEPLOYED

Production was inspected read-only only to establish the active dependency versions. No live listing, product, order, customer, payment, plugin, theme, setting, or source state was changed.

## Result journal

- 2026-09-09: Opened the marketplace-listing runtime-contract task from the verified 95-ability workbench position. Selected exact AWP Classifieds model inspection before any listing adapter implementation; bridge internals remain UNKNOWN because authoritative bridge source is not present at the checked workbench repository path.
- 2026-09-09: Runtime run `34372812403` passed exact AWP 4.4.8 model discovery. The listing is registered as post-backed `awpcp_listing` with mapped meta capabilities and `awpcp_listing_category`; current AWP listing collection/renderer/authorization/API classes are loaded. The legacy `AWPCP_TABLE_ADS` constant remains but no `wp_awpcp_ads` table exists in a fresh 4.4.8 install. Renderer/payment surfaces expose sensitive contact/payment fields, so the candidate read contract must be an explicit non-sensitive allowlist rather than generic object/meta serialization. The task advances to focused list/get contract design; no candidate ability or production state has changed.
- 2026-09-09: Focused workflow `34375072418`, job `102545690078`, passed at checkpoint `77a739528ecc5d790ac0b3fd1003364941048e1f`. Exact AWP 4.4.8 factories and collection/authorization/renderer/API signatures were execution-verified, including the native object-capability map and explicit sensitive getter boundary. The probe created no fixture, so stable ID, actual collection return type/query behavior, and object-scope authorization remained unresolved at that checkpoint.
- 2026-09-09: Exact upstream `v4.4.8` source and disposable runtime fixture closed the remaining read-contract unknowns. Workflow `34376249894`, job `102549677652`, passed at checkpoint `ad669fb64ebcaa0dad5b049e57681cd6ad25a763`: AWP native create returned post-backed listing identity, collection get/list returned bounded `WP_Post` objects, administrator/owner/nonowner/anonymous authorization behavior was observed, and native deletion plus user/environment cleanup was verified. No candidate ability code or production state changed. The task advanced to existing-candidate architecture inspection before the minimum read abilities are implemented.
- 2026-09-09: Current `main` was re-inspected after the architecture changed and found to contain Chattanooga Music Marketplace 0.1.1 as the source-owned unified presentation layer. Compare evidence showed exactly six Marketplace-source files on `main` beyond the workbench merge base. Merge checkpoint `1b79f3e1036b666c50c93ce6158183a71d90f6b7` reconciled those exact blobs into the task branch with both the prior task head and `main` as parents; the Marketplace bootstrap blob matches `main` exactly. This preserves the authoritative unified Marketplace implementation while the CMS Admin workstream continues only on the underlying listing administration surface.
- 2026-09-09: Checkpoint `8111e184a3a051ebe8ab78d750f02b283787729a` implemented only the two verified read abilities and the exact 97-ability registry fixture. Marketplace run `34380951164` passed PHP 7.4/8.2 source boundaries, the unified Marketplace presentation regression, WordPress 7.1 + AWP 4.4.8 candidate runtime, exact registry/read execution, AWP-absence fail-closed behavior, and AWP 4.4.7 version-mismatch rejection.
- 2026-09-09: Regression gate commit `30275636e5934c859ec54b8f17cab3d778258e45` extended the task-owned Marketplace workflow only to execute the pre-existing CMS Admin regression probes against the 97-ability candidate before integration. Run `34382766226` passed every job, including complete PHP 7.4/8.2 source labs, workbench integrity, Marketplace 4.4.8 and mismatch gates, content/member/navigation/media, Events Manager/Weekend Feature, WooCommerce products, and maintenance/backup/update rollback coverage.
- 2026-09-09: Final source-record checkpoint `8a171a1d0331d3e1e659c06d13ac7733cc4d1102` passed run `34383245023`. After explicit user authorization, PR #11 integrated the slice into `workbench/mars` at merge commit `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3`. All six triggered post-integration workflows passed: Events Manager `34384047941`, Content Layer `34384047961`, WooCommerce Product `34384047891`, Mars integrity `34384047944`, CMS Admin Workbench `34384047900`, and Chattanooga Music Marketplace `34384047916`. The workbench candidate is now 97 abilities; production remains independently verified at 82 and was not mutated.