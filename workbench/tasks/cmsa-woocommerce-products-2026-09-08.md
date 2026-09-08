# Task: cmsa-woocommerce-products-2026-09-08

Status: SOURCE_ENGINEERING_IN_PROGRESS

## Objective

Extend the Chattanooga CMS Admin workbench candidate with a bounded WooCommerce product-catalog administration surface, beginning from the authoritative WooCommerce product model rather than treating products as generic WordPress posts.

## Position and selected line

- Workbench base and rollback point: `f9bacf3291bd382030c945521414ae25aa86967c`.
- Source branch: `work/cmsa-woocommerce-products`.
- Current workbench candidate: 91 registered abilities.
- `workbench/labs/chattanooga-cms-admin/ROADMAP.md` explicitly identifies WooCommerce / marketplace product and listing inventory plus metadata as Layer E work.
- The current generic `CMSA_Content` service supports only `post` and `page`; WooCommerce `product` objects therefore require a WooCommerce-specific contract instead of an arbitrary custom-post-type expansion.
- The live maintenance inventory established that WooCommerce is an active Chattanooga dependency. Production is not part of this source transaction.
- Candidate lines considered:
  1. broaden the generic content service to arbitrary `product` post/meta operations;
  2. build a dedicated WooCommerce product adapter after runtime inspection of the exact product model and native permissions;
  3. begin with order/customer/financial administration.
- Line 2 is selected. Line 1 would weaken type boundaries and expose arbitrary product metadata. Line 3 crosses materially higher privacy and financial-risk boundaries before the lower-risk catalog contract is established.

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

### MUTATION_SET

1. Add a disposable WooCommerce runtime workflow pinned to the Chattanooga-relevant WooCommerce version where available.
2. Add a read-only runtime model probe that records WooCommerce version, product classes/helpers, product post type/taxonomies, native product capabilities, product CRUD object methods needed for bounded administration, and stable fields suitable for a typed contract.
3. Only after that probe passes, add WooCommerce-specific product service/abilities whose scope is supported by the verified model.
4. Add permission, exact-state, validation, readback, rollback, and unrelated-state-isolation probes for every mutation that is ultimately admitted.
5. Run the existing CMS Admin regression workflows before any integration into `workbench/mars`.

### RISK_SET

- Treating WooCommerce products as generic posts can bypass WooCommerce data stores, validation, lookup-table synchronization, stock semantics, or extension hooks.
- Product prices, inventory, SKU, tax status, visibility, and publication state can have commerce consequences; no such mutation is admitted until the native model and rollback behavior are execution-verified.
- Product data extensions may store additional private or plugin-specific state; the service must return only an explicit allowlist rather than arbitrary metadata.
- WooCommerce version drift can invalidate an adapter contract; the runtime test must record and pin the tested dependency version.
- Product mutations can affect externally visible storefront state even without touching orders; this source task tests only disposable fixtures and does not authorize live execution.

### ROLLBACK_POINT

- Branch base `f9bacf3291bd382030c945521414ae25aa86967c` is the source rollback point.
- No production state is included in this transaction.
- Disposable WooCommerce fixtures must be created and removed inside the CI runtime; any admitted mutation must prove exact semantic rollback before acceptance.

### ACCEPTANCE_TESTS

- [ ] Real WordPress 7.1 + WooCommerce runtime establishes the exact WooCommerce version and product object/data-store contract used by the adapter.
- [ ] Product read permissions are verified against native WooCommerce/WordPress capabilities; anonymous and unauthorized users are denied.
- [ ] Any added product abilities remain `show_in_rest=false`, MCP-visible under the existing candidate architecture, and appear exactly once in the expected registry manifest.
- [ ] Product list/get output is bounded to an explicit allowlist and does not expose arbitrary postmeta, order/customer data, credentials, or unrelated extension state.
- [ ] Every admitted product mutation requires exact prior state, rejects stale/no-change state, uses WooCommerce's native product object/data store rather than generic postmeta writes, verifies readback, and proves exact semantic rollback after injected verification failure.
- [ ] Unrelated products, WordPress posts, orders/customer fixtures, media, events, and existing candidate abilities remain unchanged by product transaction probes.
- [ ] PHP 7.4/8.2 and all existing WordPress 7.1, content, Events Manager, Weekend Feature, maintenance, and workbench-integrity regression gates remain green.
- [ ] No production mutation or deployment occurs.

## Production state

NOT_DEPLOYED

This task is source/workbench engineering only. Any later production deployment or product mutation is a separate authorization and verification transaction.

## Result journal

- 2026-09-08: Created source branch `work/cmsa-woocommerce-products` from verified workbench head `f9bacf3291bd382030c945521414ae25aa86967c`. Selected a dedicated WooCommerce product adapter rather than arbitrary generic CPT/postmeta expansion or higher-risk order/customer operations.
