# Chattanooga CMS Admin — universal replacement engineering experiment

Date: 2026-09-09
Status: REPLACEMENT_IN_PROGRESS
Branch: `work/chattanooga-universal-admin`
Base: `main` at `f1c4c128f29215698a2408880a010e49faccac58`
Production state: NOT_DEPLOYED

## Objective

Replace the existing Chattanooga CMS Admin plugin with a plugin-agnostic implementation that can administer functionality exposed by arbitrary active plugins without its source code knowing those plugins' identities in advance.

The universal architecture is the new implementation of Chattanooga CMS Admin. It is not a third production plugin and is not intended to coexist with the old Chattanooga CMS Admin implementation after replacement acceptance is complete.

## Replacement identity rule

The candidate WordPress plugin occupies the existing Chattanooga CMS Admin plugin slot:

- WordPress plugin name: `Chattanooga CMS Admin`;
- production folder target: `chattanooga-cms-admin`;
- entrypoint target: `chattanooga-cms-admin.php`;
- no second `Chattanooga Universal Admin` plugin entrypoint may exist;
- the lab branch/folder may retain the historical `chattanooga-universal-admin` engineering name, but that is workbench naming only and must not create another WordPress plugin.

Production transition, when separately authorized, must be one-for-one: the replacement takes the Chattanooga CMS Admin slot rather than being installed beside the old implementation.

## Architectural rule

The replacement may depend only on public WordPress contracts. It must not import, branch on, register adapters for, or otherwise special-case provider/plugin identities. New providers must become usable through runtime discovery rather than source modification.

The replacement is not accepted merely because it can coexist with the old plugin. Acceptance requires sufficient verified coverage that the old Chattanooga CMS Admin implementation can be removed rather than hidden or retained as a fallback.

## Exclusions

- No changes to `main`.
- No deployment or installation on the production WordPress site.
- No modification of miniOrange, Weekend Feature, Marketplace, WooCommerce, Events Manager, BuddyBoss, or other existing production integrations.
- No production co-installation of a separate Chattanooga Universal Admin plugin.
- No provider/plugin names in candidate source.
- No direct database, option, post, term, metadata, filesystem, or plugin-private-state mutation in candidate source.
- No private or deliberately undiscoverable provider interface exposure.
- No bypass of provider-owned WordPress permission callbacks.
- No unrestricted `target + command` dispatch endpoint.
- No deletion of the live Chattanooga CMS Admin implementation until replacement parity is execution-verified and deployment is explicitly authorized.

## Rollback point

Delete or abandon `work/chattanooga-universal-admin`; `main` and the production Chattanooga CMS Admin plugin remain unchanged.

## Phase 1 — WordPress Abilities API

### Selected line

Use the WordPress Abilities API as the first executable contract. At runtime the replacement enumerates public abilities and registers explicit facade abilities under its administration namespace. Each facade preserves the provider ability's schema and permission callback. The replacement does not contain provider identities and does not perform provider state mutation itself.

### Verified checkpoint

`0794f0a78267741c2bb52a23e6726539418bab2c`

GitHub Actions run `34421558160` completed successfully on disposable WordPress 7.1 / PHP 8.2 after temporary diagnostics were removed.

Verified outcomes:

1. Candidate source contained no test-provider or production-plugin identity.
2. Two independently installed provider plugins registered abilities unknown to candidate source.
3. Candidate discovered and generated facade abilities without source changes for either provider.
4. Public read execution passed.
5. Provider-owned mutation execution passed while candidate source contained no direct state-mutation primitive.
6. Private ability exclusion passed.
7. Provider permission denial remained blocked.
8. Candidate administrative boundary and source-boundary gates passed.

### Phase 1 refinement entering Phase 2

WordPress 7.1 defines `meta.public` as the high-level signal that an ability is intended for clients such as REST, MCP, or AI agents. The candidate therefore uses the core `public` contract rather than requiring an additional channel-specific provider metadata field. Phase 2 also added a no-input public ability to verify that absence of an input schema is forwarded without manufacturing an input value.

## Phase 2 — registered WordPress REST routes

### Selected line

Extend the same replacement with a standard-contract module that enumerates the live `WP_REST_Server` route map during Abilities API registration. For each indexed route handler and supported HTTP method, register a deterministic, route-locked facade ability. The facade accepts a concrete path plus request parameters, verifies that the path matches only its captured route expression, applies the route's registered argument validation/sanitization and permission callback, then executes through `rest_do_request()` so WordPress remains the dispatcher.

The replacement does not register provider REST routes, call provider callbacks directly for execution, or mutate provider state itself.

### REST-only provider control

A third disposable provider registers REST routes but registers no WordPress abilities. It exposes:

- one permission-protected GET route;
- one permission-protected POST mutation route whose state change remains provider-owned;
- one public-index route whose permission callback always denies execution; and
- one route explicitly hidden from the REST index.

The candidate source does not contain the provider's identity.

### Phase 2 acceptance tests

1. The REST-only provider contains no Abilities API registration.
2. Candidate source contains no REST-provider identity or provider-specific route.
3. Candidate discovers indexed GET and POST provider routes without source changes.
4. A discovered facade cannot be redirected to a different registered route.
5. GET execution returns the provider response through WordPress REST dispatch.
6. POST execution performs the provider-owned mutation through WordPress REST dispatch.
7. The hidden route is not exposed.
8. The denied route remains denied and its provider callback is not executed.
9. The candidate administration boundary rejects an anonymous user.
10. The Abilities bridge still passes, including a core-`public` ability with no channel-specific MCP metadata and no input schema.
11. Candidate source still contains no direct provider-state mutation primitive.
12. Entire combined proof passes on disposable WordPress 7.1 / PHP 8.2.

### Verified checkpoint

`88bbb68cc213ccc4769f06142cb10ae6a493f841`

GitHub Actions run `34422232141` completed successfully on disposable WordPress 7.1 / PHP 8.2.

Verified Ability-contract result:

`chattanooga-universal-admin-probe: PASS dynamic_discovery=verified standard_public=verified no_input_forwarding=verified public_bridge=verified private_exclusion=verified read_execution=verified mutation_execution=verified admin_boundary=verified target_permissions=preserved denied_execution=blocked direct_universal_mutation=absent`

Verified REST-contract result:

`cua-rest-bridge-cli: PASS rest_only_provider=verified route_discovery=verified route_lock=verified hidden_route=blocked read_execution=verified mutation_execution=verified provider_permissions=preserved denied_execution=blocked admin_boundary=verified direct_universal_mutation=absent`

The Phase 2 commit differs from the prior clean checkpoint only in the nine intended candidate, fixture, probe, workflow, and task-record files. No production deployment occurred.

## Replacement identity correction — current checkpoint target

The prior lab candidate was incorrectly packaged as a separate WordPress plugin named `Chattanooga Universal Admin`. That packaging contradicted the project objective even though the universal engine itself was valid.

The candidate has now been repackaged as the replacement `Chattanooga CMS Admin` plugin. The separate `chattanooga-universal-admin.php` WordPress entrypoint has been deleted. CI must install the candidate only as `chattanooga-cms-admin`, verify exactly one plugin header exists in the candidate, and fail if a separate Chattanooga Universal Admin plugin is installed.

The already-verified Abilities and REST behavior must continue to pass unchanged after this replacement packaging correction.

## Current conclusion boundary

The experiment execution-verifies one plugin-agnostic engine administering unrelated providers through two public WordPress contracts without provider-specific candidate code:

1. public WordPress Abilities API registrations; and
2. indexed registered WordPress REST routes, including a provider that registers no ability.

The target product is now explicitly the replacement Chattanooga CMS Admin plugin, not an additional plugin. Full replacement is not yet complete because the old Chattanooga CMS Admin capability surface has not yet been proven unnecessary across all required site administration functions. Private, undocumented, deliberately hidden, direct-PHP/internal, administrative-HTML-only, and other uncovered interfaces still require investigation. Those gaps must not be filled with per-plugin adapters merely to increase apparent coverage.

Final replacement acceptance requires execution evidence that required site administration remains available after the old implementation is absent. Only then may the old implementation be removed during a separately authorized production transition.
