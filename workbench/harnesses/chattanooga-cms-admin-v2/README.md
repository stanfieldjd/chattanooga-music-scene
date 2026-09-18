# Chattanooga CMS Admin v2 Engineering Harness

This is the active verification environment for the Chattanooga CMS Admin plug-in and its MCP.

## Canonical source

The replacement WordPress plugin source exists only at:

`site-plugins/chattanooga-cms-admin/`

Its production identity is `Chattanooga CMS Admin`, its entrypoint is `chattanooga-cms-admin.php`, and the accepted engineering version is `1.2.1`. The former `workbench/labs/chattanooga-universal-admin` tree has been deleted and must not be recreated.

## Harness rules

- The harness does not create a separate Universal Admin production plugin.
- Test fixtures remain outside canonical plugin source and may never be named or special-cased by the replacement.
- Provider functionality is bridged only when the provider exposes a public WordPress Ability contract or an indexed registered REST route.
- Explicitly private provider abilities remain private and are not re-exposed by Chattanooga CMS Admin.
- Registered Settings API administration is bounded to registered settings and preserves capability, sanitization, conflict, verification, and rollback behavior.
- Intrinsic platform administration uses bounded WordPress core interfaces and preserves permissions, verification, rollback, and control-plane self-protection.
- The Chattanooga MCP plug-in provides its own MCP transport. It exposes only explicitly MCP-public Chattanooga CMS Admin abilities, requires WordPress administrator authority, and preserves each ability's permission checks and control-plane guards.
- MCP authorization can be selected as automatic OAuth authorization or a manually managed administrator-bound bearer token. Manual mode disables the OAuth authorization, registration, token, and metadata routes while keeping the MCP endpoint active.
- No test may weaken a production acceptance rule merely to obtain a passing run.

## Verified scope

The clean harness covers dynamic Ability and REST discovery, plugin/theme lifecycle, package installation, update policy, audit, registered settings, WordPress core content and user REST administration, local/database/core backups and rollback, and an actual Core Upgrader transition followed by verified rollback.

The MCP probes separately verify the WordPress REST MCP endpoint, modern MCP 2026-07-28 discovery/tool calls, legacy 2025-11-25 initialization contract verification, administrator authorization, origin/header validation, deterministic public tool listing, private bridge exclusion, manual bearer authorization and rejection, external user CRUD, and real read/write administration calls.

The provider contract verification workflow separately verifies real Events Manager, WooCommerce, and Rank Math execution through public contracts and verifies that AWP Classifieds' private generic CPT Ability remains excluded from the universal namespace.

Production deployment is outside this harness and remains a separately authorized transition.
