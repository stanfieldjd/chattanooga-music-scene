# Task: cmsa-marketplace-listings-2026-09-09

Status: RUNTIME_CONTRACT_DISCOVERY

## Objective

Extend Chattanooga CMS Admin with a bounded marketplace-listing administration surface for the actual Chattanooga marketplace stack, beginning with exact read-only AWP Classifieds listing-model inspection before any listing mutation is designed or added.

## Target set

- Source branch `work/cmsa-marketplace-listings` only.
- `workbench/tasks/cmsa-marketplace-listings-2026-09-09.md` for the control/evidence record.
- `workbench/labs/chattanooga-cms-admin/probes/` for disposable AWP Classifieds runtime/model inspection.
- A dedicated GitHub Actions workflow for WordPress 7.1 + AWP Classifieds 4.4.8 + WooCommerce 11.0.1 runtime discovery.
- Chattanooga CMS Admin candidate files only after the exact listing model, permissions, identifiers, state boundaries, and safe native APIs are execution-verified.

## Exclusion set

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live classified listing, live WooCommerce product/order/customer, payment, checkout, or marketplace transaction mutation.
- No modification of AWP Classifieds, WooCommerce, or CMS Market Checkout Bridge source.
- No guessed direct SQL listing mutations, generic postmeta/CPT backdoor, arbitrary database endpoint, shell endpoint, remote-command endpoint, or public REST relaxation.
- No listing creation/update/delete/publication ability until a separate mutation contract is justified by runtime evidence and explicitly admitted to this task.
- No checkout, payment, order, customer, seller payout, tax, shipping, coupon, or other financial administration surface in this initial slice.
- No assumption that CMS Market Checkout Bridge internals are known. Its live activation/version may establish deployment context only; source/API behavior remains UNKNOWN until authoritative source or runtime evidence is available.

## Evidence

- Verified workbench rollback/base commit `42bb803e3cbb0a22ea06a44584c617382d9ae7f4` has the reconciled 95-ability Chattanooga CMS Admin candidate and passed Mars Workbench Integrity run `34371917853`.
- Current production read-only plugin inventory reports AWP Classifieds `4.4.8`, WooCommerce `11.0.1`, and CMS Market Checkout Bridge `0.1.1` active alongside Chattanooga CMS Admin `0.1.0`.
- The workbench roadmap identifies WooCommerce / marketplace product and listing inventory plus marketplace bridge/custom-plugin state as a concrete Chattanooga capability layer.
- WooCommerce product administration is already separately typed and execution-verified at 95 workbench abilities; marketplace listings therefore must not be collapsed into generic WooCommerce products or generic WordPress posts.
- WordPress.org identifies AWP Classifieds 4.4.8 as the current installed-version match and documents classified listing inventory plus its own payment/listing lifecycle. The upstream Strategy11 repository is public; third-party source remains an immutable dependency for this workstream.
- `site-plugins/cms-market-checkout-bridge` is not present at the checked `workbench/mars` repository path. Bridge internals are therefore not treated as known or reconstructed from memory.

## Mutation set

1. Create this dedicated source branch and task record from the verified 95-ability workbench position.
2. Add a disposable WordPress 7.1 runtime workflow pinned to AWP Classifieds 4.4.8 and WooCommerce 11.0.1.
3. Add a read-only runtime probe that records the exact AWP version, listing storage/model APIs, identifiers, relevant registered post types/taxonomies/tables, callable listing helpers/classes, and native permissions/capabilities without changing third-party source.
4. Use the runtime result to decide whether a bounded listing list/get contract can be implemented without direct SQL mutation or unsupported internals.
5. Only after that evidence passes, add the minimum typed read abilities justified by the verified contract, with explicit output allowlists and no customer/payment leakage.
6. Run existing Chattanooga CMS Admin regression gates before any workbench integration.

## Risk set

- AWP Classifieds may store listings in plugin-owned tables rather than native WordPress posts; treating listings as generic posts can corrupt or omit authoritative listing state.
- Listing objects may include seller email/contact/payment/private moderation data. Read output must be field-allowlisted and must not expose credentials, payment tokens, private transaction state, or unrelated member metadata.
- CMS Market Checkout Bridge may maintain additional relationship state not represented by AWP or WooCommerce alone. That state must remain UNKNOWN until its exact contract is recovered; no inferred bridge behavior may be encoded.
- AWP version drift can invalidate a typed adapter. The initial contract is pinned to the exact live version 4.4.8.
- AWP may expose legacy globals/functions with weak mutation boundaries. Discovery does not authorize using every callable API; candidate operations must still use the narrowest evidenced native contract with exact-state/readback controls where mutation is later admitted.

## Rollback point

- Branch/base commit: `42bb803e3cbb0a22ea06a44584c617382d9ae7f4`.
- Restoration path: move/delete the source-only task branch and return to the unchanged verified `workbench/mars` base. No production state is included in this transaction.

## Acceptance tests

- [ ] Disposable WordPress 7.1 activates exact AWP Classifieds 4.4.8 and WooCommerce 11.0.1 without modifying either dependency.
- [ ] Probe execution identifies the authoritative AWP listing storage/model and stable listing identifier used by version 4.4.8.
- [ ] Probe execution identifies native read/edit/delete capability semantics and object-scope behavior rather than assuming administrator/global permissions.
- [ ] Probe identifies whether listings are native post objects, custom-table records, or a hybrid and records relevant registered taxonomies/relationships.
- [ ] Candidate design does not depend on unavailable CMS Market Checkout Bridge internals.
- [ ] Any admitted list/get ability returns only an explicit non-sensitive field allowlist and fails closed when AWP 4.4.8 is unavailable or incompatible.
- [ ] Existing 95-ability candidate and all prior functionality remain unchanged until the runtime contract gate passes.
- [ ] No production mutation or deployment occurs.

## Source position

- Repository: `stanfieldjd/chattanooga-music-scene`
- Branch: `work/cmsa-marketplace-listings`
- Observed commit: `42bb803e3cbb0a22ea06a44584c617382d9ae7f4`

## Production state

NOT_DEPLOYED

Production was inspected read-only only to establish the active dependency versions. No live listing, product, order, customer, payment, plugin, theme, setting, or source state was changed.

## Result journal

- 2026-09-09: Opened the marketplace-listing runtime-contract task from the verified 95-ability workbench position. Selected exact AWP Classifieds model inspection before any listing adapter implementation; bridge internals remain UNKNOWN because authoritative bridge source is not present at the checked workbench repository path.
