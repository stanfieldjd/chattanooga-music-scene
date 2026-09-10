# Chattanooga CMS Admin — universal replacement engineering record

Date opened: 2026-09-09
Engineering state: SOURCE_ENGINEERING_ACCEPTED
Branch: `work/chattanooga-universal-admin-v2`
Base / merge base: `main` at `f1c4c128f29215698a2408880a010e49faccac58`
Production state: NOT_DEPLOYED

## Objective

Replace the existing Chattanooga CMS Admin implementation with one provider-agnostic Chattanooga CMS Admin plugin that administers WordPress and compatible active plugins through public WordPress contracts, while retaining bounded intrinsic WordPress platform administration for functions WordPress itself owns.

This is a replacement of the existing plugin slot, not an additional production plugin.

## Canonical replacement identity

- Plugin name: `Chattanooga CMS Admin`
- Version: `1.0.0`
- Canonical source: `site-plugins/chattanooga-cms-admin/`
- Entrypoint: `site-plugins/chattanooga-cms-admin/chattanooga-cms-admin.php`
- Ability namespace: `chattanooga-cms-admin/`
- Separate `Chattanooga Universal Admin` production plugin: ABSENT
- Obsolete `workbench/labs/chattanooga-universal-admin` tree: DELETED

The canonical source was promoted in commit `8367f1aff1c203f6ae70eda45ebc384f6416bac6`.

## Architectural contract

The replacement contains two administration layers.

### Dynamic public-contract bridge

The plugin dynamically discovers and preserves provider contracts exposed through:

1. public WordPress Abilities API registrations; and
2. indexed registered WordPress REST routes.

The bridge does not contain provider identities. Provider permission callbacks remain authoritative. Explicit MCP exposure metadata is honored when present; otherwise the WordPress `public` contract is used. Explicitly private abilities are not promoted into the Chattanooga CMS Admin namespace.

### Intrinsic WordPress platform layer

The plugin provides bounded administration for platform functions that WordPress itself owns, including health/inventory, plugin and theme lifecycle, updates, update policy, backups, restore, cache, audit, core maintenance, and registered settings.

Provider-private state is not accessed through this layer.

## Required safety properties

VERIFIED:

- provider-agnostic canonical source;
- no unrestricted target/command dispatcher;
- provider permission preservation;
- anonymous administration denial;
- control-plane self-protection;
- private Ability exclusion;
- indexed-REST-only discovery and route locking;
- conflict-aware registered-setting mutation;
- sanitizer and capability preservation for registered settings;
- local integrity hashes for rollback artifacts;
- component rollback for destructive/update operations;
- database rollback and failed-restore recovery;
- core-file plus database rollback;
- no raw audit inputs/outputs or credentials persisted by the administration audit;
- no bespoke Events Manager, WooCommerce, Rank Math, AWP Classifieds, BuddyBoss, Marketplace, or Weekend Feature adapters in canonical source.

## Replacement coverage

The replacement acceptance probe requires all 24 intrinsic system/maintenance functions represented by the old Chattanooga CMS Admin system surface:

- health and update inventory;
- plugin and theme inventory;
- backup inventory, creation, verification, and restore;
- audit log;
- plugin update/install/activate/deactivate/delete/auto-update policy;
- theme update/install/switch/delete/auto-update policy;
- WordPress core update;
- cache clearing;
- component restore;
- database restore; and
- core restore.

All 24 are present under the replacement identity.

Standard WordPress content administration is provided through generated REST facades rather than recreating the old bespoke post/page/taxonomy/member/navigation wrappers. Execution tests cover posts, pages, categories, tags, users, navigation menus, navigation items, menu locations, plugin inventory, and theme inventory.

Registered Settings API administration is provided through bounded intrinsic abilities for listing, inspecting, and conflict-checked updating currently registered settings. Arbitrary unregistered `wp_options` access is not exposed.

## Real-provider compatibility

### Events Manager 7.4.3 — VERIFIED E3

The real plugin was installed in disposable WordPress. Chattanooga CMS Admin dynamically bridged its public native Ability/REST contracts without provider-specific replacement code. Event administration and provider permission behavior were exercised successfully.

### WooCommerce 11.0.1 — VERIFIED E3

The real plugin was installed in disposable WordPress. Product query/create/update/delete operations executed through generated universal Ability facades. Provider permissions and anonymous denial were verified. No WooCommerce-specific replacement source was added.

### Rank Math SEO 1.0.278 — VERIFIED E3

The real plugin was installed in disposable WordPress with Rank Math's supported registration-skip bootstrap flag so the disposable site can initialize its Ability layer without fabricated account credentials.

The replacement dynamically bridged Rank Math's public SEO abilities. Execution verification covered:

- Rank Math settings read;
- post SEO metadata read;
- reversible global SEO setting mutation;
- readback verification;
- restoration of the original setting;
- provider permission preservation; and
- anonymous denial.

No Rank Math-specific replacement source was added.

### AWP Classifieds 4.4.8 / miniOrange MCP 1.4.10 — PRIVATE CONTRACT BOUNDARY VERIFIED

AWP registers the `awpcp_listing` post type. miniOrange registers its generic `mosmcp/cpt-list-types` Ability in the disposable environment, but its WordPress Ability metadata explicitly declares `public:false` and `show_in_rest:false`, with no `mcp.public:true` override.

Chattanooga CMS Admin therefore correctly does not re-expose that provider-private Ability. This is required by the replacement's private-interface exclusion rule. The live miniOrange MCP server may expose its own tools under its own policy; that does not authorize Chattanooga CMS Admin to promote a private WordPress Ability into its namespace.

No AWP-specific adapter was added.

## Verification evidence

### Final post-cleanup full v2 harness

Workflow: `Chattanooga CMS Admin v2 Clean Harness`
Run: `34504939807`
Result: SUCCESS

The same run passed:

- clean replacement boundary;
- disposable WordPress 7.1 installation;
- universal Ability/REST baseline;
- update policy;
- reversible component lifecycle;
- package installation and plugin state lifecycle;
- generic administration audit;
- registered Settings API administration;
- WordPress core content REST parity;
- WordPress core users REST parity;
- local backups and database rollback;
- core/database rollback; and
- actual `Core_Upgrader` transition followed by verified rollback.

### Final real-provider compatibility suite

Workflow: `Chattanooga CMS Admin v2 Provider Compatibility`
Run: `34505114334`
Result: SUCCESS

Jobs:

- Events Manager 7.4.3 — SUCCESS
- WooCommerce 11.0.1 — SUCCESS
- Rank Math SEO 1.0.278 — SUCCESS
- AWP Classifieds private-contract boundary — SUCCESS

### Hardened replacement acceptance

Workflow: `Chattanooga CMS Admin v2 Replacement Acceptance`
Run: `34504837023`
Result: SUCCESS

The acceptance gate verifies:

- exactly one Chattanooga CMS Admin plugin identity;
- version `1.0.0`;
- no old lab tree;
- no production-provider identifiers in canonical implementation source;
- all 24 intrinsic replacement functions;
- required WordPress core REST contracts;
- registered Settings API contract; and
- administrative permission boundaries.

## Repository conformance

A branch-to-`main` comparison after lab cleanup showed the branch is based directly on `main` at `f1c4c128f29215698a2408880a010e49faccac58` with no unrelated existing site-file modifications. The branch changes are confined to the Chattanooga CMS Admin replacement source, its v2 workflows/harness, and this engineering record.

The obsolete Phase-1 `workbench/labs/chattanooga-universal-admin` probes/providers/theme fixtures were deleted after the v2 harness superseded them. They are not retained as fallback code.

## Engineering acceptance decision

SOURCE ENGINEERING: COMPLETE / VERIFIED.

The canonical `1.0.0` replacement has passed the final post-cleanup universal harness, replacement-coverage acceptance gate, real Events Manager compatibility, real WooCommerce compatibility, real Rank Math SEO compatibility, and the AWP private-contract boundary test.

This decision applies to source engineering only.

## Production transition boundary

Production is still running the existing Chattanooga CMS Admin 0.1.0 implementation. No production replacement, deletion, activation, permission change, or deployment is authorized by this engineering record.

A production transition remains a separate A3 operation. It must use the one-for-one Chattanooga CMS Admin slot, preserve rollback capability, validate the replacement in the target environment, and only then remove the old implementation rather than retaining it as a fallback.
