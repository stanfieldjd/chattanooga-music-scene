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

## Phase 1 selected line

Use the WordPress Abilities API as the first executable contract. At runtime the candidate enumerates public MCP-exposed abilities and registers explicit facade abilities under its own namespace. Each facade preserves the provider ability's input/output schema and delegates permission checks to the provider before execution. The candidate does not expose one unrestricted `target + command` dispatch endpoint.

## Exclusions

- No changes to `main`.
- No deployment or installation on the production WordPress site.
- No modification of Chattanooga CMS Admin, miniOrange, Weekend Feature, Marketplace, WooCommerce, Events Manager, BuddyBoss, or other existing production integrations.
- No provider/plugin names in candidate source.
- No direct database, option, post, term, metadata, filesystem, or plugin-private-state mutation in candidate source.
- No private ability exposure.
- No bypass of a provider ability's own WordPress permission callback.

## Rollback point

Delete or abandon `work/chattanooga-universal-admin`; `main` remains unchanged at the experiment base.

## Phase 1 acceptance tests

1. Candidate source contains no test-provider or production-plugin identity.
2. Two independently installed provider plugins register abilities unknown to candidate source.
3. Candidate discovers and generates facade abilities for both providers without source changes.
4. A public read ability executes through its facade.
5. A public mutating ability executes through its facade while the mutation remains provider-owned.
6. A private ability is not exposed.
7. A public ability whose provider permission callback denies access remains denied through the facade.
8. Candidate source contains no direct provider-state mutation primitives.
9. Entire proof passes on disposable WordPress 7.1 / PHP 8.2 in CI.

## Interpretation boundary

A Phase 1 pass proves plugin-agnostic administration for functionality already exposed through the public WordPress Abilities API. It does not by itself prove that arbitrary private or undocumented plugin internals can be administered universally. Additional standard-contract discovery must be evaluated separately for functionality not represented by an ability.
