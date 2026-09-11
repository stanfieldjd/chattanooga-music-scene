# Chattanooga CMS Admin v2 Engineering Harness

This is the active verification environment for the universal Chattanooga CMS Admin replacement.

## Canonical source

The replacement WordPress plugin source exists only at:

`site-plugins/chattanooga-cms-admin/`

Its production identity is `Chattanooga CMS Admin`, its entrypoint is `chattanooga-cms-admin.php`, and the accepted engineering version is `1.1.0`. The former `workbench/labs/chattanooga-universal-admin` tree has been deleted and must not be recreated.

## Harness rules

- The harness does not create a separate Universal Admin production plugin.
- Test fixtures remain outside canonical plugin source and may never be named or special-cased by the replacement.
- Provider functionality is bridged only when the provider exposes a public WordPress Ability contract or an indexed registered REST route.
- Explicitly private provider abilities remain private and are not re-exposed by Chattanooga CMS Admin.
- Registered Settings API administration is bounded to registered settings and preserves capability, sanitization, conflict, verification, and rollback behavior.
- Intrinsic platform administration uses bounded WordPress core interfaces and preserves permissions, verification, rollback, and control-plane self-protection.
- Native MCP transport is implemented inside Chattanooga CMS Admin rather than as a second bridge plugin. It exposes only explicitly MCP-public Chattanooga CMS Admin abilities, requires WordPress administrator authority, and preserves each ability's permission checks and control-plane guards.
- No test may weaken a production acceptance rule merely to obtain a passing run.

## Verified scope

The clean harness covers dynamic Ability and REST discovery, plugin/theme lifecycle, package installation, update policy, audit, registered settings, WordPress core content and user REST administration, local/database/core backups and rollback, and an actual Core Upgrader transition followed by verified rollback.

The native MCP probe separately verifies the WordPress REST MCP endpoint, modern MCP 2026-07-28 discovery/tool calls, legacy 2025-11-25 initialization compatibility, administrator authorization, origin/header validation, deterministic public tool listing, private bridge exclusion, and a real read-only administration call.

The provider compatibility workflow separately verifies real Events Manager, WooCommerce, and Rank Math execution through public contracts and verifies that AWP Classifieds' private generic CPT Ability remains excluded from the universal namespace.

Production deployment is outside this harness and remains a separately authorized transition.
