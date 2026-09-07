# Chattanooga CMS Admin Workbench Lab

Purpose: provide a source-controlled laboratory for Chattanooga CMS Admin without changing `main`, the live WordPress site, or the `feature/chattanooga-cms-admin` source branch.

## Layout

- `plugin/` — exact mirror of the verified plugin source checkpoint `0b34773ebc8073cb657477770b34cabc280f5892` using the same Git blobs.
- `fixtures/` — expected source and ability manifests.
- `tests/` — GitHub-executable static and stub-runtime tests.
- `probes/` — read-only probes intended for a real WordPress runtime later.
- `results/` — evidence matrix. Runtime-dependent items remain UNKNOWN until executed against WordPress.
- `scripts/run-lab.sh` — deterministic lab runner used by CI.

## Test tiers

1. **Source integrity** — prove the workbench mirror is byte-identical to the verified feature-branch source.
2. **Syntax compatibility** — lint on PHP 7.4 (declared minimum) and PHP 8.2 (current Chattanooga Music Scene runtime).
3. **Architecture/security** — reject arbitrary command execution and direct REST-route exposure; inventory outbound/network primitives without pretending a static scan proves runtime privacy.
4. **Stub registration** — load the plugin with minimal WordPress stubs and verify the expected Abilities registration shape and permission callbacks.
5. **Real runtime probes** — later execute read-only probes inside WordPress 7.1 to establish availability of Abilities API functions, upgrader classes, filesystem APIs, ZipArchive, database access, and writable backup locations.

## Evidence rule

A passing GitHub test proves only the condition it executes. It does not prove the plugin is installed, activated, discoverable through MCP, or safe on production. Those remain runtime gates.

## Promotion rule

Changes discovered in this lab are not production changes. A fix must be deliberately promoted to a source branch, reviewed/tested there, then deployed under a separate authorized transaction.
