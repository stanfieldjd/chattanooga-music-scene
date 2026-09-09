# Task: cmsa-woocommerce-products-2026-09-08

Status: SOURCE_ENGINEERING_IN_PROGRESS

## Objective

Extend the Chattanooga CMS Admin workbench candidate with a bounded WooCommerce product-catalog administration surface, beginning from the authoritative WooCommerce product model rather than treating products as generic WordPress posts.

## Position and selected line

- Workbench base and rollback point: `f9bacf3291bd382030c945521414ae25aa86967c`.
- Source branch: `work/cmsa-woocommerce-products`.
- Current source checkpoint: `f3fdd5e34fb884ec57fe6afc8f5c09d2a20ddba2`.
- Current workbench candidate: 91 registered abilities before the WooCommerce product slice; the source branch candidate registers 95 after adding four bounded WooCommerce product abilities.
- `workbench/labs/chattanooga-cms-admin/ROADMAP.md` explicitly identifies WooCommerce / marketplace product and listing inventory plus metadata as Layer E work.
- The current generic `CMSA_Content` service supports only `post` and `page`; WooCommerce `product` objects therefore require a WooCommerce-specific contract instead of an arbitrary custom-post-type expansion.
- The live maintenance inventory established that WooCommerce is an active Chattanooga dependency. Production is not part of this source transaction.
- Candidate lines considered:
  1. broaden the generic content service to arbitrary `product` post/meta operations;
  2. build a dedicated WooCommerce product adapter after runtime inspection of the exact product model and native permissions;
  3. begin with order/customer/financial administration.
- Line 2 is selected. Line 1 would weaken type boundaries and expose arbitrary product metadata. Line 3 crosses materially higher privacy and financial-risk boundaries before the lower-risk catalog contract is established.
- The first runtime probe rejected two initial assumptions instead of encoding them into the adapter: WooCommerce 11.0.1 does not provide `wc_get_product_statuses()`, and `edit_product` / `read_product` / `delete_product` are object-scoped mapped meta capabilities rather than global primitive capabilities. The corrected probe now distinguishes object meta capabilities from the plural primitive product capabilities.
- Source checkpoint `112702bea73cc4edcbf32f338eddc549a680e035` additionally execution-verified the native WooCommerce `WC_Product::delete( true )` cleanup path for a disposable newly created product.
- Source checkpoint `f3fdd5e34fb884ec57fe6afc8f5c09d2a20ddba2` added the four-ability typed product candidate. Workflow `34320883241` passed lint, the WordPress/WooCommerce model, 95-ability registry, product permissions/object scope, and public-REST isolation. Its transaction probe reached the final unrelated-order isolation assertion and failed there; that assertion is under direct diagnosis and the product slice is not accepted or integrated while it remains unresolved.

## Pre-execution control record

### OBJECTIVE

Establish, test, and then implement only the bounded WooCommerce product-catalog operations justified by real WooCommerce runtime evidence. The initial engineering gate is authoritative model/capability inspection; mutation abilities are added only after exact state, validation, readback, and rollback semantics are proven.

### TARGET_SET

- `workbench/labs/chattanooga-cms-admin/candidate/` only for WooCommerce-specific service/ability registration changes justified by the runtime probe.
- `workbench/labs/chattanooga-cms-admin/probes/` for disposable WooCommerce product model, permission, read, and transaction probes.
- `workbench/labs/chattanooga-cms-admin/fixtures/` for the exact expected-ability manifest if abilities are added.
- `.github/workflows/cmsa-woocommerce-lab.yml` for WordPress 7.1 + WooCommerce runtime verification.
- This task record.

### EXCLUSION_SET

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live WooCommerce products, orders, customers, coupons, taxes, shipping, payments, or marketplace transactions.
- No arbitrary custom-post-type or arbitrary postmeta endpoint.
- No arbitrary SQL, shell, filesystem, remote-command, or REST backdoor.
- No order/customer/financial data surface in this task.
- No product deletion, permanent destructive operation, or live publication in this task unless a later explicit user instruction changes that authorization boundary.
- No modification of WooCommerce plugin source.

### EVIDENCE

- Current workbench head `f9bacf3291bd382030c945521414ae25aa86967c` passed Mars Workbench Integrity run `34276860121`.
- The integrated Chattanooga CMS Admin candidate contains 91 abilities after the verified media and Weekend Feature slices.
- `CMSA_Content::type_config()` explicitly supports only `post` and `page` and rejects other post types.
- The project roadmap explicitly plans WooCommerce product/listing inventory and metadata as typed Layer E abilities.
- The same-day live maintenance inventory verified WooCommerce as an active Chattanooga plugin, but live state will not be mutated or treated as proof of the exact runtime product API contract.
- Corrected WooCommerce runtime run `34277599322` passed on WordPress 7.1, PHP 8.2, MySQL 8, and WooCommerce 11.0.1. It verified `WC_Product`, `WC_Product_Simple`, `WC_Product_Query`, `WC_Data_Store`, `wc_get_product()`, `wc_get_products()`, product type/taxonomy registration, the native product getter/setter surface required by the planned adapter, and actual product data store `WC_Product_Data_Store_CPT`.
- The same run verified product `map_meta_cap=true`, object-scoped meta capabilities `edit_product`, `read_product`, and `delete_product`, and administrator primitive capabilities including `edit_products`, `edit_others_products`, `publish_products`, `read_private_products`, `edit_private_products`, and `edit_published_products`.
- Product taxonomies `product_cat`, `product_tag`, `product_type`, and `product_visibility` were execution-verified as registered on `product`; `product_cat` / `product_tag` use product-term capability families.
- Workflow `34320181025` passed after extending the model probe to prove native disposable product cleanup through `WC_Product::delete( true )` with absence readback.
- Workflow `34320883241` passed candidate lint, runtime model, candidate activation, the complete 95-ability registry, product permission/object-scope tests, and REST isolation. The transaction probe then failed only at `order fixture changed during product transactions`; the exact before/after order-state difference has not yet been established, so the isolation result remains unresolved rather than being waived.

### MUTATION_SET

1. Add a disposable WooCommerce runtime workflow pinned to WooCommerce 11.0.1, matching the currently observed Chattanooga dependency version for this source contract.
2. Add a read-only runtime model probe that records WooCommerce version, product classes/helpers, product post type/taxonomies, native product capabilities, product CRUD object methods needed for bounded administration, and stable fields suitable for a typed contract.
3. Add a dedicated WooCommerce product service and four initial abilities: bounded list, bounded exact get, simple-product draft creation, and exact-state simple-product update. Product publication and product deletion remain outside this task.
4. Add permission, exact-state, validation, readback, rollback, and unrelated-state-isolation probes for every admitted mutation.
5. Diagnose the unrelated-order isolation failure without weakening or removing the isolation assertion; repair the probe or candidate only after the exact failure mechanism is established.
6. Run the existing CMS Admin regression workflows before any integration into `workbench/mars`.

### RISK_SET

- Treating WooCommerce products as generic posts can bypass WooCommerce data stores, validation, lookup-table synchronization, stock semantics, or extension hooks.
- Product prices, inventory, SKU, tax status, visibility, and publication state can have commerce consequences; no mutation is admitted until the native model and rollback behavior are execution-verified.
- Product data extensions may store additional private or plugin-specific state; the service must return only an explicit allowlist rather than arbitrary metadata.
- WooCommerce version drift can invalidate an adapter contract; the adapter will fail closed outside the execution-verified WooCommerce 11.0.1 contract until another version is separately tested.
- Product mutations can affect externally visible storefront state even without touching orders; this source task tests only disposable fixtures and does not authorize live execution.
- Variable/grouped/external product mutation semantics differ from simple products. The first mutation surface is therefore restricted to `WC_Product_Simple`, while bounded reads may inspect other native product types.
- A test-fixture state mismatch must not be mistaken for evidence that the candidate mutated an order. Conversely, the isolation assertion must not be relaxed until the mismatch is explained by verified evidence.

### ROLLBACK_POINT

- Branch base `f9bacf3291bd382030c945521414ae25aa86967c` is the task rollback point; pre-Woo implementation checkpoint `112702bea73cc4edcbf32f338eddc549a680e035` remains a narrower rollback point for the product candidate itself.
- No production state is included in this transaction.
- Disposable WooCommerce fixtures must be created and removed inside the CI runtime; any admitted mutation must prove exact semantic rollback before acceptance.
- Draft creation verification failure may remove only the newly created product through WooCommerce's native product object delete path; update verification failure must restore the exact pre-mutation allowlisted product semantic state through WooCommerce setters/data store and verify the original state token returns.

### ACCEPTANCE_TESTS

- [x] Real WordPress 7.1 + WooCommerce runtime establishes the exact WooCommerce version and product object/data-store contract used by the adapter.
- [x] Product read permissions are verified against native WooCommerce/WordPress capabilities; anonymous and unauthorized users are denied.
- [x] Added product abilities remain `show_in_rest=false`, MCP-visible under the existing candidate architecture, and appear exactly once in the expected registry manifest.
- [ ] Product list/get output is bounded to an explicit allowlist and does not expose arbitrary postmeta, order/customer data, credentials, or unrelated extension state.
- [ ] Every admitted product mutation requires exact prior state, rejects stale/no-change state, uses WooCommerce's native product object/data store rather than generic postmeta writes, verifies readback, and proves exact semantic rollback after injected verification failure.
- [ ] Unrelated products, WordPress posts, orders/customer fixtures, media, events, and existing candidate abilities remain unchanged by product transaction probes.
- [ ] PHP 7.4/8.2 and all existing WordPress 7.1, content, Events Manager, Weekend Feature, maintenance, and workbench-integrity regression gates remain green.
- [x] No production mutation or deployment occurs.

## Production state

NOT_DEPLOYED

This task is source/workbench engineering only. Any later production deployment or product mutation is a separate authorization and verification transaction.

## Result journal

- 2026-09-08: Created source branch `work/cmsa-woocommerce-products` from verified workbench head `f9bacf3291bd382030c945521414ae25aa86967c`. Selected a dedicated WooCommerce product adapter rather than arbitrary generic CPT/postmeta expansion or higher-risk order/customer operations.
- 2026-09-08: Added the disposable WordPress 7.1 / WooCommerce 11.0.1 product-model gate. Initial run `34277435253` failed because the probe incorrectly assumed a nonexistent `wc_get_product_statuses()` helper and treated object meta capabilities as global primitive caps. Corrected the probe at source checkpoint `493cca7a2b888c5d8f332ac1840137cc39c1f1ab`; run `34277599322` passed and established `WC_Product_Data_Store_CPT`, object-scoped meta caps, primitive product capability families, registered product taxonomies, and the native getter/setter contract required for the next implementation step.
- 2026-09-09: Extended the runtime contract at `112702bea73cc4edcbf32f338eddc549a680e035`; run `34320181025` passed native product creation/reload/`delete( true )`/absence verification for candidate rollback cleanup.
- 2026-09-09: Implemented four bounded WooCommerce product abilities at `f3fdd5e34fb884ec57fe6afc8f5c09d2a20ddba2`. Run `34320883241` passed lint, model, candidate activation, registry count 95, product permission/object scope, and REST isolation. The final transaction probe failed at the unrelated-order isolation comparison. The failure is retained as an unresolved validation result; the next source action is diagnostic evidence collection, not removal or relaxation of the order-isolation acceptance condition.
