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

- [ ] A disposable plugin registering one or more native WordPress abilities is discovered by the universal control plane with zero plugin-name-specific CMSA source.
- [ ] A second unrelated disposable plugin is discovered by the same unchanged control-plane code.
- [ ] CMSA's own namespace is not recursively proxied or duplicated.
- [ ] Universal discovery exposes only bounded metadata/schema fields and does not expose callback internals or secrets.
- [ ] Native target permission behavior remains authoritative.
- [ ] No universal execute wrapper bypasses miniOrange/NHI ability grants.
- [ ] Existing 98-ability workbench candidate remains regression-green until a verified migration removes superseded adapters.
- [ ] New plugin integration no longer requires adding a plugin-specific CMSA class when the plugin exposes a supported standard interface.
- [ ] Production remains unchanged.

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

Recalculated next position:

The universal layer now has two generic discovery contracts: native WordPress abilities and standard post-type/taxonomy registries. The next source-only line is parity classification: determine which existing bespoke CMSA adapters can be replaced by a verified native/standard interface without losing domain invariants. Any adapter lacking full parity remains in place. Generic CPT/taxonomy mutation remains prohibited until a concrete interface is execution-tested to preserve the target plugin's save semantics and permission model.

## Production state

NOT_DEPLOYED
