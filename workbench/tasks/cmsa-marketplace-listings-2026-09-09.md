# Task: cmsa-marketplace-listings-2026-09-09

Status: READ_CONTRACT_DESIGN

## Objective

Extend Chattanooga CMS Admin with a bounded marketplace-listing administration surface for the actual Chattanooga marketplace stack, beginning with exact AWP Classifieds listing-model inspection and admitting only the minimum non-sensitive read contract established by execution evidence before any listing mutation is designed or added.

## Target set

- Source branch `work/cmsa-marketplace-listings` only.
- `workbench/tasks/cmsa-marketplace-listings-2026-09-09.md` for the control/evidence record.
- `workbench/labs/chattanooga-cms-admin/probes/` for disposable AWP Classifieds runtime/model and read-contract inspection.
- `.github/workflows/cmsa-marketplace-listings-lab.yml` for WordPress 7.1 + AWP Classifieds 4.4.8 + WooCommerce 11.0.1 runtime verification.
- Chattanooga CMS Admin candidate files only after the exact listing read model, permissions, identifiers, state boundaries, and safe native APIs are execution-verified.
- Exact expected-ability fixture only after a bounded read ability is admitted.

## Exclusion set

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live classified listing, live WooCommerce product/order/customer, payment, checkout, or marketplace transaction mutation.
- No modification of AWP Classifieds, WooCommerce, or CMS Market Checkout Bridge source.
- No guessed direct SQL listing mutations, generic postmeta/CPT backdoor, arbitrary database endpoint, shell endpoint, remote-command endpoint, or public REST relaxation.
- No listing creation/update/delete/publication ability until a separate mutation contract is justified by runtime evidence and explicitly admitted to this task.
- No checkout, payment, order, customer, seller payout, tax, shipping, coupon, or other financial administration surface in this initial slice.
- No output of listing access/edit keys, seller/contact email, seller/contact phone, seller/contact name, IP address, payment email, payment status/term, raw user/member records, payment rows, or arbitrary listing metadata.
- No assumption that CMS Market Checkout Bridge internals are known. Its live activation/version may establish deployment context only; source/API behavior remains UNKNOWN until authoritative source or runtime evidence is available.

## Evidence

- Verified workbench rollback/base commit `42bb803e3cbb0a22ea06a44584c617382d9ae7f4` has the reconciled 95-ability Chattanooga CMS Admin candidate and passed Mars Workbench Integrity run `34371917853`.
- Current production read-only plugin inventory reports AWP Classifieds `4.4.8`, WooCommerce `11.0.1`, and CMS Market Checkout Bridge `0.1.1` active alongside Chattanooga CMS Admin `0.1.0`.
- The workbench roadmap identifies WooCommerce / marketplace product and listing inventory plus marketplace bridge/custom-plugin state as a concrete Chattanooga capability layer.
- WooCommerce product administration is already separately typed and execution-verified at 95 workbench abilities; marketplace listings therefore must not be collapsed into generic WooCommerce products or generic WordPress posts.
- WordPress.org identifies AWP Classifieds 4.4.8 as the current installed-version match and documents classified listing inventory plus its own payment/listing lifecycle. The upstream Strategy11 repository is public; third-party source remains an immutable dependency for this workstream.
- `site-plugins/cms-market-checkout-bridge` is not present at the checked `workbench/mars` repository path. Bridge internals are therefore not treated as known or reconstructed from memory.
- Runtime workflow `34372812403`, job `102538018793`, passed on WordPress 7.1 + PHP 8.2 + MySQL 8 + AWP Classifieds 4.4.8 + WooCommerce 11.0.1 at branch checkpoint `78dba0d0c023be312d1f17476d0de4df6288667d`.
- The runtime established `AWPCP_LISTING_POST_TYPE=awpcp_listing`, with registered post type `awpcp_listing`, public=true, map_meta_cap=true, capability type `awpcp_classified_ad`.
- The runtime established hierarchical taxonomy `awpcp_listing_category` attached to `awpcp_listing`; term management/edit/delete uses `manage_categories` and relationship assignment uses `edit_posts`.
- The runtime administrator has `edit_awpcp_classified_ads`, `edit_others_awpcp_classified_ads`, and `manage_awpcp`.
- AWP 4.4.8 exposes `AWPCP_ListingsCollection` with bounded listing collection/get methods, `AWPCP_ListingAuthorization` with current-user listing edit/manage checks, `AWPCP_ListingRenderer` with explicit listing getters, and `AWPCP_ListingsAPI` with native lifecycle/mutation methods. Mutation method existence is evidence only and is not admission to this task.
- Fresh AWP 4.4.8 created six plugin-owned auxiliary tables: `wp_awpcp_ad_regions`, `wp_awpcp_adfees`, `wp_awpcp_admeta`, `wp_awpcp_credit_plans`, `wp_awpcp_payments`, and `wp_awpcp_tasks`. No actual `wp_awpcp_ads` table exists in the fresh runtime even though legacy constant `AWPCP_TABLE_ADS` remains defined. The current listing identity therefore must not be implemented through legacy `wp_awpcp_ads` direct SQL.
- `AWPCP_ListingRenderer` exposes both useful public-state getters and private/sensitive getters. Explicitly observed sensitive getters include access key, contact email/name/phone, IP address, payment email/status/term, and user. This materially requires a strict output allowlist rather than serializing the renderer/listing object wholesale.
- AWP payment storage separately contains `payer_email` and transaction/payment state. Payment/customer data remains outside this task.
- First runtime gate exact terminal result: `awpcp-marketplace-model-cli: PASS version=4.4.8 tables=6 functions=250 classes=300 model=discovered-not-yet-admitted`.

## Mutation set

1. Create this dedicated source branch and task record from the verified 95-ability workbench position. COMPLETE.
2. Add a disposable WordPress 7.1 runtime workflow pinned to AWP Classifieds 4.4.8 and WooCommerce 11.0.1. COMPLETE.
3. Add a read-only runtime probe that records the exact AWP version, listing storage/model APIs, identifiers, relevant registered post types/taxonomies/tables, callable listing helpers/classes, and native permissions/capabilities without changing third-party source. COMPLETE.
4. Add a focused read-contract probe that records exact factory/helper availability and reflected signatures for the listing collection, renderer, authorization and API objects; create only disposable fixture state if required to establish the stable listing identifier/return object and clean it through the plugin-owned lifecycle before the CI environment is discarded.
5. Use the focused runtime result to define the minimum listing list/get field allowlist and permission contract without direct SQL mutation, raw postmeta access, or unsupported internals.
6. Only after that evidence passes, add the minimum typed read abilities justified by the verified contract, with explicit output allowlists and no customer/payment/contact leakage.
7. Verify exact 4.4.8 dependency fail-closed behavior, permissions/object scope, registry uniqueness, public-REST isolation, and bounded list/get behavior using disposable fixtures.
8. Run all existing Chattanooga CMS Admin regression gates before any workbench integration.

## Risk set

- AWP Classifieds uses a native `awpcp_listing` CPT plus plugin-owned metadata/region/payment support. Treating the listing as an ordinary generic post can omit AWP lifecycle semantics or private metadata boundaries even though its stable WordPress object identity is post-backed.
- Listing objects include seller/contact/payment/private moderation data. Read output must be field-allowlisted and must not expose credentials, access/edit tokens, contact identity, IP addresses, payment state, or unrelated member metadata.
- Legacy table constants remain defined even when their historical table is absent. Using `AWPCP_TABLE_ADS` as current storage would encode stale internals and is prohibited by the verified runtime state.
- CMS Market Checkout Bridge may maintain additional relationship state not represented by AWP or WooCommerce alone. That state must remain UNKNOWN until its exact contract is recovered; no inferred bridge behavior may be encoded.
- AWP version drift can invalidate a typed adapter. The initial contract is pinned to the exact live version 4.4.8.
- AWP exposes legacy globals/functions and a broad native mutation API. Discovery does not authorize every callable API; the first admitted candidate surface remains read-only and must use the narrowest execution-verified contract.

## Rollback point

- Branch/base commit: `42bb803e3cbb0a22ea06a44584c617382d9ae7f4`.
- Runtime-contract checkpoint before this record update: `78dba0d0c023be312d1f17476d0de4df6288667d`.
- Restoration path: return the task branch to the verified base/checkpoint. No production state is included in this transaction.
- Any disposable listing fixture used in CI must be removed through an execution-verified AWP/WordPress lifecycle path before the runtime job is considered passing; the entire disposable WordPress instance is additionally destroyed after the job.

## Acceptance tests

- [x] Disposable WordPress 7.1 activates exact AWP Classifieds 4.4.8 and WooCommerce 11.0.1 without modifying either dependency.
- [x] Probe identifies the current listing as post-backed `awpcp_listing`, establishes `map_meta_cap=true`, and proves the legacy `wp_awpcp_ads` table is absent in the fresh 4.4.8 runtime.
- [x] Probe identifies the relevant AWP listing collection/API/renderer/authorization object families and administrator listing capabilities without assuming a generic WordPress content contract.
- [x] Probe identifies the hierarchical `awpcp_listing_category` relationship and its capability families.
- [x] Candidate design does not depend on unavailable CMS Market Checkout Bridge internals.
- [ ] Focused probe establishes exact helper/factory signatures and the stable listing identifier/return-object behavior needed for list/get.
- [ ] Focused probe establishes the narrow native permission/object-scope test line for list/get.
- [ ] Any admitted list/get ability returns only an explicit non-sensitive field allowlist and fails closed when AWP 4.4.8 is unavailable or incompatible.
- [x] Existing 95-ability candidate and all prior functionality remained unchanged through the first runtime contract gate.
- [ ] Existing 95-ability regressions remain green after any candidate read abilities are added.
- [x] No production mutation or deployment occurs.

## Source position

- Repository: `stanfieldjd/chattanooga-music-scene`
- Branch: `work/cmsa-marketplace-listings`
- Observed runtime-contract checkpoint: `78dba0d0c023be312d1f17476d0de4df6288667d`

## Production state

NOT_DEPLOYED

Production was inspected read-only only to establish the active dependency versions. No live listing, product, order, customer, payment, plugin, theme, setting, or source state was changed.

## Result journal

- 2026-09-09: Opened the marketplace-listing runtime-contract task from the verified 95-ability workbench position. Selected exact AWP Classifieds model inspection before any listing adapter implementation; bridge internals remain UNKNOWN because authoritative bridge source is not present at the checked workbench repository path.
- 2026-09-09: Runtime run `34372812403` passed exact AWP 4.4.8 model discovery. The listing is registered as post-backed `awpcp_listing` with mapped meta capabilities and `awpcp_listing_category`; current AWP listing collection/renderer/authorization/API classes are loaded. The legacy `AWPCP_TABLE_ADS` constant remains but no `wp_awpcp_ads` table exists in a fresh 4.4.8 install. Renderer/payment surfaces expose sensitive contact/payment fields, so the candidate read contract must be an explicit non-sensitive allowlist rather than generic object/meta serialization. The task advances to focused list/get contract design; no candidate ability or production state has changed.
