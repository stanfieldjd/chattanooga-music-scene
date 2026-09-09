# Task: cmsa-universal-control-plane-2026-09-09

Status: ARCHITECTURE_REPLACEMENT_IN_PROGRESS

## Objective

Replace the current plugin-by-plugin Chattanooga CMS Admin integration pattern with one site-owned universal control plane that can discover and use supported WordPress/plugin administration surfaces without requiring a new CMSA source adapter every time another plugin is installed.

The end state is one integration architecture inside Chattanooga CMS Admin, not a growing set of hard-coded plugin-specific bridges.

## Verified starting position

- Workbench base: `f2e8aed1d4dc01de4f8bffac92fec88fe93f0a5a` on `workbench/mars`.
- The candidate bootstrap currently hard-codes plugin-specific files for Events Manager, Weekend Feature, WooCommerce products, and Marketplace listings.
- `CMSA_Plugin` currently stores and registers separate plugin-specific ability objects for those integrations.
- WordPress 7.1 provides the native Abilities API, including `wp_get_abilities()`, `wp_get_ability()`, ability schemas/metadata, permission callbacks, and `WP_Ability::execute()`.

## Architectural requirement

1. One universal control plane SHALL own extension discovery and integration.
2. Installing another plugin SHALL NOT by itself require a new CMSA PHP adapter class.
3. Native WordPress Abilities API registrations SHALL be treated as the primary executable plugin contract when available.
4. Standard WordPress resource registries MAY be supported by generic resource engines only where the native object model supplies enough information to preserve object capabilities and invariants.
5. The universal layer SHALL NOT proxy an ability in a way that bypasses miniOrange/NHI per-ability governance. Native abilities remain native abilities for authorization and execution.
6. The universal layer SHALL NOT use arbitrary SQL, arbitrary postmeta/options scanning, arbitrary filesystem access, or undocumented plugin internals to fake universal support.
7. Unsupported private plugin internals SHALL fail closed rather than trigger another bespoke adapter by default.
8. Existing plugin-specific CMSA adapters are migration inputs only. They SHALL remain until equivalent universal coverage is execution-verified, then be deleted rather than hidden or left as duplicate fallback paths.

## Target set

- `workbench/labs/chattanooga-cms-admin/candidate/`
- New universal-control-plane source and probes on `work/cmsa-universal-control-plane`
- Workbench-only workflow/test files required to prove dynamic discovery and no hard-coded plugin dependency

## Exclusion set

- Production WordPress/DreamHost
- `main`
- `feature/chattanooga-cms-admin`
- miniOrange policy
- Live content, media, events, products, listings, users, settings, files, or plugin state
- Generic execution proxy that would let one outer CMSA ability invoke a native ability denied by MCP/NHI policy
- Arbitrary SQL, arbitrary plugin-table discovery, arbitrary postmeta/options mutation, arbitrary filesystem endpoints, or remote command execution
- Deletion of current plugin-specific adapters before universal parity is proven

## Selected line

1. Build a universal discovery/index layer around native WordPress registries, starting with the Abilities API.
2. Prove with disposable fixture plugins that new native plugin abilities appear in the universal catalog without any CMSA source change.
3. Keep execution on the native ability object/name so its own schema, permission callback, annotations, and MCP/NHI governance remain authoritative.
4. Add generic WordPress resource families only by interface type, not plugin identity, and only after execution tests prove the interface carries enough invariants for safe mutation.
5. Migrate current bespoke integrations to universal/native surfaces where equivalence can be proven.
6. Delete superseded plugin-specific adapter code after parity and regression validation.

## Risks

- A single generic `run-anything` wrapper could bypass miniOrange per-ability policy; prohibited.
- Generic CPT mutation can corrupt plugin state when a plugin requires domain-specific save APIs; such resources must not be treated as safely writable without evidence.
- Plugin abilities may mislabel annotations; annotations are descriptive hints, not sufficient authorization or safety proof.
- Some plugins expose no standard callable contract. The control plane must report that fact rather than inventing a hidden adapter or generic database workaround.

## Rollback point

- Exact branch base `f2e8aed1d4dc01de4f8bffac92fec88fe93f0a5a`.
- No production state is part of this transaction.

## Acceptance tests

- [x] A disposable plugin registering one or more native WordPress abilities is discovered by the universal control plane with zero plugin-name-specific CMSA source.
- [x] A second unrelated disposable plugin is discovered by the same unchanged control-plane code.
- [x] CMSA's own namespace is not recursively proxied or duplicated.
- [x] Universal discovery exposes only bounded metadata/schema fields and does not expose callback internals or secrets.
- [x] Native target permission behavior remains authoritative.
- [x] No universal execute wrapper bypasses miniOrange/NHI ability grants.
- [x] Current 101-ability workbench candidate remains regression-green while bespoke adapters remain in place pending verified parity.
- [x] New plugin integration no longer requires adding a plugin-specific CMSA class when the plugin exposes a supported native ability or read-only standard WordPress post-type/taxonomy interface.
- [x] Production remains unchanged.

## Checkpoint — 2026-09-09 universal discovery and standard registry slice

Checkpoint head before record: `4887a39266829c89d55c2e5623d9364ca0066122` on `work/cmsa-universal-control-plane`.

Verified source changes:

- `CMSA_Universal_Control_Plane` dynamically discovers public native WordPress abilities and excludes the CMSA namespace from recursive mirroring.
- `chattanooga-cms-admin/inspect-extension-capabilities` remains discovery-only; there is no generic native-ability execute proxy.
- `chattanooga-cms-admin/inspect-resource-registry` now discovers administratively exposed registered post types and taxonomies through the standard WordPress registries without plugin-name-specific source logic.
- Resource-registry output is bounded to an explicit metadata/capability allowlist. Registered resources hidden from both the admin UI and REST are excluded.
- Standard-registry discovery is read-only. No generic post-type/taxonomy mutation path is admitted by this checkpoint.
- Two unrelated disposable fixtures independently register a visible/hidden post-type pair and a visible/hidden taxonomy pair so interface discovery is tested without special-casing either fixture in the control-plane source.
- The expected CMSA registry is now 100 abilities.

Execution evidence:

- GitHub Actions run `34414315685` on head `4887a39266829c89d55c2e5623d9364ca0066122` passed PHP lint.
- The interface-only/non-proxy source contract passed.
- Disposable WordPress 7.1 installation and activation passed.
- The 100-ability registry test passed.
- Existing native-ability discovery across both unrelated fixtures passed.
- Standard post-type/taxonomy registry discovery passed, including hidden-resource exclusion and bounded output-field checks.

Preserved constraints:

- Production WordPress/DreamHost unchanged.
- `main` unchanged.
- `feature/chattanooga-cms-admin` unchanged.
- miniOrange/NHI policy unchanged.
- Existing plugin-specific adapters remain present; none has been deleted, hidden, or converted into a fallback path.

## Checkpoint — 2026-09-09 universal standard-resource reader and parity classification

Validated source head before this record: `43ab4f1c76f4b391342d093a39c01df3d2216213` on `work/cmsa-universal-control-plane`.

Verified source changes:

- `CMSA_Universal_Resource_Reader` is loaded by Chattanooga CMS Admin and is now exposed through `chattanooga-cms-admin/read-standard-resource`.
- The new ability reads only administratively exposed standard WordPress post types and taxonomies, using the registered object capability model and exact object identity checks.
- Post-type output is bounded to core identity/status/title/excerpt/slug/parent/date fields for list operations, with content added only for an exact get operation.
- Taxonomy output is bounded to core term identity/name/slug/description/parent/count fields.
- Arbitrary postmeta, termmeta, options, plugin tables, filesystem access, private plugin APIs, and mutation are not exposed.
- Hidden registered resources remain rejected.
- The expected CMSA registry is now 101 abilities.
- CI statically rejects generic post/term mutation primitives and `$wpdb` usage from the universal resource reader.

Execution evidence:

- GitHub Actions run `34416223499` on head `43ab4f1c76f4b391342d093a39c01df3d2216213` completed successfully.
- PHP lint passed for the candidate and all universal probes.
- Interface-only and non-proxy source checks passed.
- Disposable WordPress 7.1 installation and fixture activation passed.
- The 101-ability registry test passed.
- Native ability discovery across unrelated fixtures remained green.
- Standard registry discovery remained green.
- `universal-resource-reader-cli.php` passed bounded post-type list/get, bounded taxonomy list/get, hidden-resource rejection, cross-resource identity rejection, permission checks, zero read-side mutation, and absence of a generic mutation/execution proxy.

Parity classification of current bespoke adapters:

- Events Manager: NOT_REPLACEABLE_BY_STANDARD_READER. The adapter carries Events Manager event/location identity, date/time/timezone/location semantics and state tokens through `EM_Event`, `EM_Location`, `EM_Events`, and `EM_Locations`; generic post fields are not equivalent.
- Weekend Feature: NOT_REPLACEABLE_BY_STANDARD_READER. The adapter owns source-plugin settings, schedule state, current weekend identity, event availability, guarded generation/publication, conflict tokens, readback verification, and rollback behavior; these are not a standard post/taxonomy contract.
- WooCommerce products: NOT_REPLACEABLE_BY_STANDARD_READER. The adapter deliberately uses WooCommerce product objects/data stores and preserves SKU, pricing, catalog visibility, inventory, taxonomy/image relationships, state-token conflict control, verification, and rollback; a generic product CPT read is not equivalent.
- Marketplace listings: NOT_REPLACEABLE_BY_STANDARD_READER. The adapter depends on the verified AWP Classifieds collection, renderer, and authorization contracts and exposes bounded domain fields such as price, category IDs, visibility/expiry/featured/review state and view URL; generic post fields do not preserve that contract.

No current bespoke adapter is eligible for deletion from this checkpoint. Keeping them is not a fallback workaround; it is required to preserve domain semantics until an equivalent native/standard contract exists and is execution-verified.

Preserved constraints:

- Production WordPress/DreamHost unchanged.
- `main` unchanged.
- `feature/chattanooga-cms-admin` unchanged.
- miniOrange/NHI policy unchanged.
- No live content, media, events, products, listings, users, settings, files, or plugin state changed.
- No existing bespoke adapter was deleted or hidden.

Recalculated next position:

The universal control plane now covers three standard interface classes without plugin-name-specific integration: native WordPress abilities, registered post-type/taxonomy discovery, and bounded read-only access to those standard resources. Existing bespoke adapters remain only where verified domain semantics exceed those interfaces. The next engineering line is to continue interface-level coverage, not add new plugin-name-specific bridges: identify the next standard WordPress administration surface with a concrete Chattanooga use, define a bounded typed contract, prove permissions/invariants in disposable WordPress, and only then reconsider whether any bespoke code has become redundant.

## Rejected experiment — standard REST-controller mutation contract

Experiment source head: `2e95585a5a321680363d50f7c42c994fb10b6bb9` on `work/cmsa-universal-control-plane`.

Objective:

- Determine whether standard WordPress REST post-type and taxonomy controllers preserve enough registered permission and plugin validation behavior to serve as a future interface-level mutation contract.
- No universal mutation ability was registered or exposed during the experiment.

Execution evidence:

- GitHub Actions run `34416612901` preserved all established green gates: PHP lint, interface-only/non-proxy checks, WordPress 7.1 installation, fixture activation, 101-ability registry, native ability discovery, standard registry discovery, and universal standard-resource reads all passed.
- The new REST-controller mutation-contract probe failed at the taxonomy fail-closed validation test.
- The exact runtime failure included `Undefined property: WP_Error::$name` from `WP_REST_Terms_Controller`, followed by `Taxonomy REST pre-insert contract was not authoritative.`
- The post-type portion had already passed its permission, rejected-create, accepted-create, rejected-update, accepted-update, and post-insert contract checks before the taxonomy failure was reached.

Engineering conclusion:

- The standard post REST controller remains a possible future source-only research line, but it is not admitted as a universal write ability by this checkpoint.
- The WordPress 7.1 taxonomy REST controller does not provide the required fail-closed invariant for a generic mutation layer when a `rest_pre_insert_{$taxonomy}` validation filter returns `WP_Error`; the controller proceeds through an object-oriented prepared-term contract rather than safely propagating that error.
- Therefore a combined generic post-type/taxonomy mutation engine is REJECTED and generic taxonomy mutation is NOT_ADMITTED.
- This negative result must not be worked around with direct term writes, arbitrary metadata/database access, hidden special cases, or a permissive generic endpoint.

Rollback verification:

- Experimental REST hooks were removed from both disposable fixture plugins.
- `universal-rest-controller-contract-cli.php` was deleted rather than disabled.
- The reader-only CI workflow was restored exactly.
- Tree comparison from verified checkpoint `fe4bee7a917a367331adebc163c530c43fbd06cb` to post-rollback head `e09c2276bfc4ba8913c6d7abc97b3cf5246a40f4` returned zero changed files, proving the failed experiment left no source/test residue.
- The 101-ability universal reader checkpoint remains the active source position.

Recalculated next position:

Continue standard-interface engineering from the verified 101-ability reader position. A post-type-only REST mutation contract may be investigated separately only when tied to a concrete Chattanooga administrative use and only with exact-state conflict control, readback verification, rollback, permission tests, and plugin-save invariant evidence. Do not generalize that work to taxonomies or to domain-heavy plugin resources without separate proof.

## Production state

NOT_DEPLOYED
