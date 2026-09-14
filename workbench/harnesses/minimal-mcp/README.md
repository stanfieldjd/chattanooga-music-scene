# Minimal MCP Transport Proof

Status: WORKBENCH ONLY / NOT DEPLOYED / NO ADMINISTRATION SURFACE

Purpose: prove the MCP tunnel before attaching blanket WordPress administration or plugin-specific capabilities.

This proof deliberately contains one endpoint and one read-only tool:

- Endpoint: `/wp-json/minimal-mcp/v1/mcp`
- Protocol: MCP `2026-07-28` only
- Methods: `server/discover`, `tools/list`, `tools/call`
- Tool: `probe.site`

`probe.site` proves that an authenticated MCP request crossed external HTTP, reached the WordPress runtime, and returned the site's title, home URL, and WordPress version.

The proof intentionally does not contain:

- blanket administration;
- plugin adapters;
- dynamic WordPress capability discovery;
- writes;
- shell commands;
- SQL;
- filesystem access;
- Git operations;
- browser automation;
- MCP legacy `initialize` sessions;
- production deployment code.

The CI probe verifies:

1. anonymous MCP access is denied;
2. authenticated `server/discover` succeeds;
3. the server advertises only `2026-07-28`;
4. server identity is returned in response `_meta`;
5. `tools/list` exposes exactly `probe.site`;
6. `tools/call` executes `probe.site` across real HTTP and reads the disposable WordPress runtime;
7. mismatched MCP routing headers are rejected; and
8. a legacy `2025-11-25` initialize request is rejected.

Only after this transport proof is stable should an administrative workspace be attached behind it.
