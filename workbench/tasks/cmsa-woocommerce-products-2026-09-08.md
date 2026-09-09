# Task: cmsa-woocommerce-products-2026-09-08

Status: SOURCE_ACCEPTED_READY_FOR_WORKBENCH_INTEGRATION

## Objective

Extend the Chattanooga CMS Admin workbench candidate with a bounded WooCommerce product-catalog administration surface, beginning from the authoritative WooCommerce product model rather than treating products as generic WordPress posts.

## Position and selected line

- Workbench base and rollback point: `f9bacf3291bd382030c945521414ae25aa86967c`.
- Source branch: `work/cmsa-woocommerce-products`.
- Accepted source checkpoint before this task-record-only state update: `12b06e77e32e0988242a79d5ea5586b247355478`.
- Current workbench candidate: 91 registered abilities before the WooCommerce product slice; the accepted source branch candidate registers 95 after adding four bounded WooCommerce product abilities.
- `workbench/labs/chattanooga-cms-admin/ROADMAP.md` explicitly identifies WooCommerce / marketplace product and listing inventory plus metadata as Layer E work.
- The current generic `CMSA_Content` service supports only `post` and `page`; WooCommerce `product` objects therefore require a WooCommerce-specific contract instead of an arbitrary custom-post-type expansion.
- The live maintenance inventory established that WooCommerce is an active Chattanooga dependency. Production is not part of this source transaction.
- Candidate lines considered:
  1. broaden the generic content service to arbitrary `product` post/meta operations;
  2. build a dedicated WooCommerce product adapter after runtime inspection of the exact product model and native permissions;
  3. begin with order/customer/financial administration.
- Line 2 is selected. Line 1 would weaken type boundaries and expose arbitrary product metadata. Line 3 crosses materially higher privacy and financial-risk boundaries before the lower-risk catalog contract is established.
- The first runtime probe rejected two initial assumptions instead of encoding them into the adapter: WooCommerce 11.0.1 does not provide `wc_get_product_statuses()`, and `edit_product` / `read_product` / `delete_product` are object-scoped mapped meta capabilities rather than global primitive capabilities. The corrected probe distinguishes object meta capabilities from the plural primitive product capabilities.
- Source checkpoint `112702bea73cc4edcbf32f338eddc549a680e035` execution-verified the native WooCommerce `WC_Product::delete( true )` cleanup path for a disposable newly created product.
- Source checkpoint `f3fdd5e34fb884ec57fe6afc8f5c09d2a20ddba2` added the four-ability typed product candidate. Workflow `34320883241` passed lint, the WordPress/WooCommerce model, 95-ability registry, product permissions/object scope, and public-REST isolation, then failed the unrelated-order isolation assertion.
- Direct diagnostic evidence established that failure was a probe-baseline defect rather than an order mutation: immediately after `wc_create_order()`, WooCommerce represented the zero total as string `"0"` in the returned in-memory object, while reloading the same persisted order represented the same zero total as canonical string `"0.00"`. Identity, status, customer ID and item count were unchanged. The isolation probe now takes its before-state from a persisted reload and continues to compare that persisted semantic state against a later persisted reload; the order-isolation assertion was not weakened or removed.
- Workflow `34321646228` at checkpoint `323503b117c82335e75f1822abba7f6e696605de` passed the diagnostic gate and full WooCommerce product transaction probe, including create/get/list/update, exact-state conflict handling, injected-failure rollback and unrelated product/post/event/media/customer/order isolation.
- Existing regression workflows were then run unchanged against the source branch by adding branch-only trigger scaffolding. `Mars Workbench Integrity` run `34363584118`, `CMS Admin Content Layer Lab` run `34363650727`, `CMS Admin Events Manager Lab` run `34363690700`, and `CMS Admin Workbench Lab` run `34363759870` all passed. The Workbench Lab passed PHP 7.4, PHP 8.2, and the complete WordPress 7.1 maintenance/runtime job. The Events Manager run also passed the Weekend Feature transaction regression.
- The temporary branch-only trigger additions were removed after those runs. A compare from the workbench base to the accepted branch shows no residual changes to the existing integrity/content/events/workbench workflow files; only the intended WooCommerce source, probes, fixture, WooCommerce workflow, lab boundary hook, and task record remain in the final diff.

## Pre-execution control record

### OBJECTIVE

Establish, test, and implement only the bounded WooCommerce product-catalog operations justified by real WooCommerce runtime evidence. Mutation abilities are admitted only after exact state, validation, readback, rollback and unrelated-state isolation are execution-verified.

### TARGET_SET

- `workbench/labs/chattanooga-cms-admin/candidate/` only for WooCommerce-specific service/ability registration changes justified by runtime evidence.
- `workbench/labs/chattanooga-cms-admin/probes/` for disposable WooCommerce product model, permission, read and transaction probes.
- `workbench/labs/chattanooga-cms-admin/fixtures/` for the exact expected-ability manifest.
- `.github/workflows/cmsa-woocommerce-lab.yml` for WordPress 7.1 + WooCommerce runtime verification.
- Existing CMS Admin regression workflow trigger files may receive branch-only temporary test scaffolding solely to execute their unchanged jobs against this source branch; that scaffolding must be removed after the runs and is not part of the product candidate.
- This task record plus protocol-mandated workbench state files after a material verified result.

### EXCLUSION_SET

- No production WordPress, DreamHost, `main`, `feature/chattanooga-cms-admin`, miniOrange policy, live WooCommerce products, orders, customers, coupons, taxes, shipping, payments, or marketplace transactions.
- No arbitrary custom-post-type or arbitrary postmeta endpoint.
- No arbitrary SQL, shell, filesystem, remote-command, or REST backdoor.
- No order/customer/financial data surface in this task.
- No product deletion, permanent destructive operation, or live publication in this task.
- No modification of WooCommerce plugin source.

### EVIDENCE

- Workbench base `f9bacf3291bd382030c945521414ae25aa86967c` passed Mars Workbench Integrity run `34276860121`.
- The integrated pre-task Chattanooga CMS Admin candidate contains 91 abilities after the verified media and Weekend Feature slices.
- `CMSA_Content::type_config()` supports only `post` and `page` and rejects other post types.
- Corrected WooCommerce runtime run `34277599322` passed on WordPress 7.1, PHP 8.2, MySQL 8, and WooCommerce 11.0.1. It verified `WC_Product`, `WC_Product_Simple`, `WC_Product_Query`, `WC_Data_Store`, `wc_get_product()`, `wc_get_products()`, product type/taxonomy registration, the native getter/setter surface, and actual product data store `WC_Product_Data_Store_CPT`.
- The same runtime verified product `map_meta_cap=true`, object-scoped meta capabilities `edit_product`, `read_product`, and `delete_product`, and administrator primitive product capabilities.
- Product taxonomies `product_cat`, `product_tag`, `product_type`, and `product_visibility` were execution-verified as registered on `product`; `product_cat` / `product_tag` use product-term capability families.
- Workflow `34320181025` proved native disposable product cleanup through `WC_Product::delete( true )` with absence readback.
- Workflow `34321646228` proved the 95-ability registry, product permission/object-scope gates, REST isolation, canonical persisted-order isolation baseline, full product transactions and rollback.
- The order-baseline diagnostic showed `{"total":"0"}` in the immediate creation object versus `{"total":"0.00"}` after persisted reload before any product operation, establishing the exact cause of the earlier false isolation failure.
- Regression runs `34363584118`, `34363650727`, `34363690700`, and `34363759870` all completed successfully against the accepted product candidate. PHP 7.4, PHP 8.2, WordPress 7.1 maintenance/runtime, content, Events Manager, Weekend Feature and workbench integrity gates are green.
- Final compare against the workbench base confirms temporary regression-trigger scaffolding was removed and does not remain in the accepted tree.

### MUTATION_SET

1. Add a disposable WooCommerce runtime workflow pinned to WooCommerce 11.0.1.
2. Add a read-only runtime model probe for the exact product model/capability/data-store contract.
3. Add a dedicated WooCommerce product service and four abilities: bounded list, bounded exact get, simple-product draft creation, and exact-state simple-product update. Product publication and deletion remain outside this task.
4. Add permission, exact-state, validation, readback, rollback and unrelated-state-isolation probes.
5. Diagnose and correct the unrelated-order isolation baseline without weakening or removing the isolation assertion.
6. Run the existing CMS Admin regression workflows before integration into `workbench/mars`.
7. Remove branch-only CI trigger scaffolding after validation.
8. Integrate the accepted final tree into `workbench/mars` as a source-only workbench change, then run the workbench-triggered validation gates again and update protocol state.

### RISK_SET

- Treating WooCommerce products as generic posts can bypass WooCommerce data stores, validation, lookup-table synchronization, stock semantics, or extension hooks.
- Product prices, inventory, SKU, tax status, visibility, and publication state can have commerce consequences; no live mutation is authorized by this source task.
- Product extensions may store additional private or plugin-specific state; the service returns only an explicit allowlist and does not expose arbitrary metadata.
- WooCommerce version drift can invalidate an adapter contract; the adapter fails closed outside the execution-verified WooCommerce 11.0.1 contract until another version is separately tested.
- Variable/grouped/external product mutation semantics differ from simple products. Mutation remains restricted to `WC_Product_Simple`, while bounded reads may inspect other native product types.

### ROLLBACK_POINT

- Workbench base `f9bacf3291bd382030c945521414ae25aa86967c` is the task rollback point; pre-Woo implementation checkpoint `112702bea73cc4edcbf32f338eddc549a680e035` is the narrower product-candidate rollback point.
- No production state is included in this transaction.
- Disposable WooCommerce fixtures are created and removed inside CI.
- Draft creation verification failure removes only the newly created product through WooCommerce's native product object delete path; update verification failure restores the exact pre-mutation allowlisted semantic state and verifies the original state token returns.

### ACCEPTANCE_TESTS

- [x] Real WordPress 7.1 + WooCommerce runtime establishes the exact WooCommerce version and product object/data-store contract used by the adapter.
- [x] Product read permissions are verified against native WooCommerce/WordPress capabilities; anonymous and unauthorized users are denied.
- [x] Added product abilities remain `show_in_rest=false`, MCP-visible under the existing candidate architecture, and appear exactly once in the expected registry manifest.
- [x] Product list/get output is bounded to an explicit allowlist and does not expose arbitrary postmeta, order/customer data, credentials, or unrelated extension state.
- [x] Every admitted product mutation requires exact prior state, rejects stale/no-change state, uses WooCommerce's native product object/data store rather than generic postmeta writes, verifies readback, and proves exact semantic rollback after injected verification failure.
- [x] Unrelated products, WordPress posts, orders/customer fixtures, media, events, and existing candidate abilities remain unchanged by product transaction probes.
- [x] PHP 7.4/8.2 and all existing WordPress 7.1, content, Events Manager, Weekend Feature, maintenance, and workbench-integrity regression gates remain green.
- [x] Temporary regression-trigger scaffolding is absent from the accepted final tree.
- [x] No production mutation or deployment occurs.

## Production state

NOT_DEPLOYED

This task is source/workbench engineering only. Any later production deployment or live product mutation is a separate authorization and verification transaction.

## Result journal

- 2026-09-08: Created source branch `work/cmsa-woocommerce-products` from verified workbench head `f9bacf3291bd382030c945521414ae25aa86967c`. Selected a dedicated WooCommerce product adapter rather than arbitrary generic CPT/postmeta expansion or higher-risk order/customer operations.
- 2026-09-08: Added the disposable WordPress 7.1 / WooCommerce 11.0.1 product-model gate. Initial run `34277435253` failed because the probe incorrectly assumed a nonexistent `wc_get_product_statuses()` helper and treated object meta capabilities as global primitive caps. Corrected the probe at `493cca7a2b888c5d8f332ac1840137cc39c1f1ab`; run `34277599322` passed and established the authoritative model.
- 2026-09-09: Extended the runtime contract at `112702bea73cc4edcbf32f338eddc549a680e035`; run `34320181025` passed native product creation/reload/`delete( true )`/absence verification for candidate rollback cleanup.
- 2026-09-09: Implemented four bounded WooCommerce product abilities at `f3fdd5e34fb884ec57fe6afc8f5c09d2a20ddba2`. Run `34320883241` passed all pre-transaction gates and exposed a final unrelated-order comparison defect.
- 2026-09-09: Preserved the failure and added a dedicated order-baseline diagnostic. It execution-verified that `wc_create_order()` returns zero total as `"0"` in memory while `wc_get_order()` reloads the persisted same order as `"0.00"`; no product operation is needed for the mismatch.
- 2026-09-09: Corrected the probe to compare persisted-before against persisted-after order state at `323503b117c82335e75f1822abba7f6e696605de`. Run `34321646228` passed the 95-ability registry, permissions, REST isolation, diagnostic cleanup, and complete WooCommerce product transaction/rollback/isolation suite.
- 2026-09-09: Ran the unchanged pre-integration regression gates against the candidate. Integrity `34363584118`, content `34363650727`, Events Manager/Weekend Feature `34363690700`, and workbench PHP 7.4/PHP 8.2/WordPress 7.1 maintenance `34363759870` all passed. Removed the temporary source-branch trigger scaffolding; final compare shows no residual changes to those existing workflow files. Source acceptance is complete; next action is clean workbench integration and post-integration validation.