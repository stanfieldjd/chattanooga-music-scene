# Minimal MCP Transport Proof

Status: WORKBENCH ONLY / NOT DEPLOYED / NO ADMINISTRATION SURFACE

Purpose: prove the MCP tunnel before attaching blanket WordPress administration or plugin-specific capabilities.

This proof deliberately contains one endpoint and one read-only tool:

- Endpoint: `/wp-json/minimal-mcp/v1/mcp`
- Protocol: MCP `2026-07-28` only
- Methods: `server/discover`, `tools/list`, `tools/call`
- Tool: `probe.site`

`probe.site` proves that an authenticated MCP request crossed external HTTP, reached the WordPress runtime, and returned the site's title, home URL, and WordPress version.

## Reference implementations followed

The proof is intentionally modeled after mature MCP implementations rather than growing a bespoke all-in-one request handler.

### Official MCP TypeScript v2 SDK

The CI suite installs the current official v2 client and pins protocol `2026-07-28`. The proof must interoperate with that client rather than only passing hand-written curl requests.

### WordPress MCP Adapter

The official `WordPress/mcp-adapter` project separates transport, request routing, components, permissions, and error handling. This proof follows the same separation at a smaller scale:

1. `CMSA_Minimal_MCP_HTTP_Transport` owns WordPress REST registration, transport authentication, MCP routing-header validation, JSON parsing, and HTTP response creation.
2. `CMSA_Minimal_MCP_Request_Router` owns MCP method routing and protocol response envelopes.
3. `CMSA_Minimal_MCP_Tool_Registry` owns the available tool inventory and execution logic.

The official adapter's current released transport still targets initialization-based 2025-era MCP revisions. This proof therefore copies the architecture pattern but retains the current stateless `2026-07-28` protocol already verified by the official v2 client.

The adapter's default-server pattern of keeping MCP discovery compact while discovering application capabilities behind a small meta-tool surface is a candidate for later blanket-administration experiments; it is not implemented in this transport-only proof.

### GitHub MCP Server

GitHub's server maintains an inventory of tools and supports server-level filtering such as toolsets and read-only mode. The separate tool registry in this proof creates the same boundary: transport code does not know how individual tools work. If the proof later gains additional tools, server-level inventory/policy should filter them before exposure rather than scattering policy across handlers.

### Cloudflare remote MCP examples

Cloudflare's examples treat Streamable HTTP as the normal remote-server transport and keep authentication in front of the MCP application. The WordPress REST permission callback serves the same boundary in this proof while it remains a WordPress-hosted fixture.

## Current architecture

```text
MCP client
   |
   v
HTTP transport
   |
   v
request router
   |
   v
tool registry
   |
   v
probe.site
   |
   v
WordPress runtime
```

The transport must remain ignorant of WordPress administration semantics. The registry must remain ignorant of HTTP authentication and protocol headers. The router must remain ignorant of how a tool performs its work.

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
7. mismatched MCP routing headers are rejected;
8. a legacy `2025-11-25` initialize request is rejected; and
9. the official MCP v2 client connects with `2026-07-28`, lists `probe.site`, and calls it successfully.

Only after this transport proof is stable should an administrative workspace be attached behind it.
