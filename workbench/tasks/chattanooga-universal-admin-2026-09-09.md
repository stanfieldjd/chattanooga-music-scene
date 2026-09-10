# Chattanooga Universal Admin — from-scratch engineering experiment

Date: 2026-09-09
Status: IN_PROGRESS
Branch: `work/chattanooga-universal-admin`
Base: `main` at `f1c4c128f29215698a2408880a010e49faccac58`
Production state: NOT_DEPLOYED

## Objective

Determine whether one new WordPress plugin can administer functionality exposed by an arbitrary active plugin without Chattanooga-specific source code knowing that plugin's identity in advance.

## Architectural rule

The candidate plugin may depend only on public WordPress contracts. It must not import, branch on, register adapters for, or otherwise special-case provider/plugin identities. New providers must become usable through runtime discovery rather than source modification.

## Exclusions

- No changes to `main`.
- No deployment or installation on the production WordPress site.
- No modification of Chattanooga CMS Admin, miniOrange, Weekend Feature, Marketplace, WooCommerce, Events Manager, BuddyBoss, or other existing production integrations.
- No provider/plugin names in candidate source.
- No direct database, option, post, term, metadata, filesystem, or plugin-private-state mutation in candidate source.
- No private or deliberately undiscoverable provider interface exposure.
- No bypass of provider-owned WordPress permission callbacks.
- No unrestricted `target + command` dispatch endpoint.

## Rollback point

Delete or abandon `work/chattanooga-universal-admin`; `main` remains unchanged at the experiment base.

## Phase 1 — WordPress Abilities API

### Selected line

Use the WordPress Abilities API as the first executable contract. At runtime the candidate enumerates public abilities and registers explicit facade abilities under its own namespace. Each facade preserves the provider ability's schema and permission callback. The candidate does not contain provider identities and does not perform provider state mutation itself.

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

WordPress 7.1 defines `meta.public` as the high-level signal that an ability is intended for clients such as REST, MCP, or AI agents. The candidate therefore must use the core `public` contract rather than requiring an additional channel-specific provider metadata field. Phase 2 also adds a no-input public ability to verify that absence of an input schema is forwarded without manufacturing an input value.

## Phase 2 — registered WordPress REST routes

### Selected line

Extend the same candidate with a standard-contract module that enumerates the live `WP_REST_Server` route map during Abilities API registration. For each indexed route handler and supported HTTP method, register a deterministic, route-locked facade ability. The facade accepts a concrete path plus request parameters, verifies that the path matches only its captured route expression, applies the route's registered argument validation/sanitization and permission callback, then executes through `rest_do_request()` so WordPress remains the dispatcher.

The candidate does not register provider REST routes, call provider callbacks directly for execution, or mutate provider state itself.

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

## Interpretation boundary

A Phase 1 pass establishes plugin-agnostic administration for functionality exposed through public WordPress abilities. A Phase 2 pass would extend that evidence to indexed registered REST endpoints even when the provider registers no ability. Neither phase by itself proves administration of plugin functionality that is private, undocumented, unregistered, or available only through bespoke internal code or an administrative HTML interface. Those remaining interface classes must be evaluated separately rather than silently replaced with provider-specific adapters.
