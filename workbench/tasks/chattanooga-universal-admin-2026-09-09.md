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
- administration ability namespace: `chattanooga-cms-admin/`;
- no second `Chattanooga Universal Admin` plugin entrypoint or temporary administration namespace may exist;
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

The Phase 2 commit differed from the prior clean checkpoint only in intended candidate, fixture, probe, workflow, and task-record files. No production deployment occurred.

## Replacement packaging and namespace correction

The prior lab candidate was incorrectly packaged as a separate WordPress plugin named `Chattanooga Universal Admin`. That packaging contradicted the project objective even though the universal engine itself was valid.

The candidate was repackaged as the replacement `Chattanooga CMS Admin` plugin. The separate `chattanooga-universal-admin.php` WordPress entrypoint was deleted. CI installs the candidate only as `chattanooga-cms-admin`, verifies exactly one plugin header exists in the candidate, and fails if a separate Chattanooga Universal Admin plugin is installed.

A second replacement-identity defect was then found: generated facade abilities still used the temporary `chattanooga-universal-admin/` namespace. Both bridge engines and both execution probes were changed to use and require `chattanooga-cms-admin/`. CI now fails if the temporary administration namespace returns.

## Phase 3 — WordPress core REST replacement parity

### Objective

Determine how much of the old Chattanooga CMS Admin's hand-written WordPress-core administration surface is unnecessary because the unchanged universal REST engine can operate the corresponding core controllers directly.

### Execution probe

`workbench/labs/chattanooga-universal-admin/probes/cmsa-core-rest-parity-cli.php`

The probe locates generated facades only through `chattanooga-cms-admin/catalog`; it contains no replacement adapter code. On disposable WordPress 7.1 it executes through the generated core REST facades and verifies:

- post create, read, update, and draft-to-published status transition;
- page create and update;
- category create and update;
- tag create and update;
- member creation plus profile and role update;
- navigation menu create/update and menu-item create/update;
- plugin inventory read;
- theme inventory read; and
- menu-location inventory read.

Disposable objects are deleted only by the probe cleanup code. Candidate source remains free of direct content/user/menu mutation primitives.

### Verified checkpoint

`458a59643009bf34c6f101557ff985624b8bcf38`

GitHub Actions run `34427584215`, job `102715987016`, completed successfully on disposable WordPress 7.1 / PHP 8.2.

Verified outputs:

`chattanooga-cms-admin-probe: PASS namespace=verified dynamic_discovery=verified standard_public=verified no_input_forwarding=verified public_bridge=verified private_exclusion=verified read_execution=verified mutation_execution=verified admin_boundary=verified target_permissions=preserved denied_execution=blocked direct_universal_mutation=absent`

`cua-rest-bridge-cli: PASS namespace=verified rest_only_provider=verified route_discovery=verified route_lock=verified hidden_route=blocked read_execution=verified mutation_execution=verified provider_permissions=preserved denied_execution=blocked admin_boundary=verified direct_universal_mutation=absent`

`cmsa-core-rest-parity-cli: PASS posts=read_create_update_status pages=create_update categories=create_update tags=create_update members=create_profile_roles navigation=menu_item_create_update plugin_inventory=read theme_inventory=read menu_locations=read candidate_adapters=none`

This is functional REST coverage, not yet semantic parity with the old plugin's custom conflict tokens, rollback snapshots, guarded permanent-deletion rules, revision handling, or other transaction controls.

## Live old-plugin inventory for replacement analysis

Read-only live ability discovery shows the existing Chattanooga CMS Admin currently exposes 82 abilities, grouped as:

- 24 system/maintenance operations;
- 18 post/page operations;
- 12 category/tag/relationship operations;
- 8 navigation operations;
- 6 member operations; and
- 14 Events Manager operations.

This inventory is a replacement checklist, not a requirement to preserve the old implementation's one-class-per-domain architecture.

## Native provider ability findings

Read-only live discovery found that Events Manager already exposes a large native `events-manager__...` ability surface covering event, location, ticket, booking, category/tag, media, and related operations. Therefore the replacement must consume that public native ability contract dynamically; no Events Manager adapter is to be added.

Read-only live discovery also found native `woocommerce__...` abilities for product and order administration, plus an additional broad WooCommerce ability surface exposed by the active MCP environment. Therefore no WooCommerce adapter is to be added to the replacement.

Searches for `marketplace` and `classified` returned no native ability surface. Marketplace/AWP Classifieds remains an uncovered domain pending investigation of indexed REST or another standard public contract. Absence of a native ability is not authorization to create a provider-specific adapter.

## Registered Settings API finding

The WordPress Settings API provides registered-setting discovery, sanitization metadata, and settings-group capability rules, but the standard wp-admin save path ultimately performs direct option mutation. No reusable public execution dispatcher analogous to `WP_Ability::execute()` or `rest_do_request()` has been established for non-REST registered settings.

Under the current architectural exclusion against generic direct `update_option()` mutation, a universal Settings API write bridge is therefore NOT_ADMITTED on current evidence. Registered settings already exposed through REST remain reachable through the existing REST engine. This finding prevents using the Settings API as a disguised arbitrary-option backdoor.

## Current replacement coverage state

VERIFIED functional universal paths:

1. public WordPress Abilities API registrations;
2. indexed registered WordPress REST routes;
3. core post/page/category/tag/member/navigation administration exercised through generated REST facades;
4. core plugin/theme/menu-location inventory reads through generated REST facades; and
5. live existence of native Events Manager and WooCommerce ability surfaces that match the replacement's generic Ability contract.

INCOMPLETE / remaining replacement blockers:

1. system/maintenance semantics: health, core/plugin/theme updates, backups, backup verification, restoration, cache clearing, audit log, auto-update policy, and rollback guarantees;
2. theme mutation beyond the read-only core theme REST surface;
3. Marketplace/AWP Classifieds if no indexed REST or public ability contract exists;
4. old-plugin safety semantics that exceed ordinary REST behavior, including conflict checks, revisions, guarded destructive actions, verification and rollback; and
5. execution evidence with the actual replacement installed in place of the old implementation on a safe non-production target before production transition.

## Current conclusion boundary

The experiment execution-verifies one plugin-agnostic Chattanooga CMS Admin replacement engine administering unrelated providers through public WordPress contracts without provider-specific candidate code. Core WordPress content/member/navigation administration is now also execution-verified through that same engine.

The target remains one replacement plugin, not an additional plugin. Full replacement is not yet complete. Gaps must not be filled with per-plugin adapters merely to increase apparent coverage.

Final replacement acceptance requires execution evidence that required site administration remains available after the old implementation is absent. Only then may the old implementation be removed during a separately authorized production transition.
