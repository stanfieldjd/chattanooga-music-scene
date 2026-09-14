# Minimal MCP Transport Proof

Status: WORKBENCH ONLY / NOT DEPLOYED / NO ADMINISTRATION SURFACE

Purpose: prove a current MCP tunnel into WordPress before attaching blanket administration or plugin-specific capabilities.

## Architecture

The proof is deliberately layered:

1. `CMSA_Minimal_MCP_HTTP_Transport` owns HTTP, authentication, Origin validation, and modern MCP header/body validation.
2. `CMSA_Minimal_MCP_Request_Router` owns JSON-RPC method routing and server metadata.
3. `CMSA_Minimal_MCP_Tool_Registry` owns MCP tool definitions and execution callbacks.
4. WordPress components can register tools on `minimal_mcp_register_tools` without changing the transport or router.

This follows the same broad separation used by mature MCP servers: transport is replaceable, tool inventory is centralized, and application capabilities plug into the registry rather than being embedded in the wire protocol.

## Protocol boundary

- Endpoint: `/wp-json/minimal-mcp/v1/mcp`
- Protocol: MCP `2026-07-28` only
- Methods: `server/discover`, `tools/list`, `tools/call`
- Stateless: no `initialize`, `initialized`, or MCP session ID
- Authentication: WordPress administrator authentication
- Request metadata: protocol version and client capabilities are required in `params._meta`
- HTTP mirror validation: `MCP-Protocol-Version`, `Mcp-Method`, and `Mcp-Name` are validated against the body
- `Mcp-Name` supports the 2026-07-28 Base64 sentinel encoding
- Origin headers are rejected unless they match the WordPress origin or an explicitly filtered allowed origin

## Extensibility proof

The core plugin contains one built-in read-only tool:

- `probe.site` — proves the MCP request reached the WordPress runtime.

CI also installs a separate disposable WordPress plugin from `fixtures/dynamic-tool-fixture.php`. That plugin registers:

- `fixture.echo` — proves an external WordPress component can add a discoverable/callable MCP tool without changing MCP transport or router source.

The fixture is not part of the MCP plugin and is never intended for production deployment.

## Independent clients

The endpoint is tested through three independent paths:

1. raw HTTP requests that verify exact status codes, headers, and JSON-RPC error contracts;
2. the official MCP TypeScript v2 client pinned to protocol `2026-07-28`; and
3. the official MCP Inspector CLI configured with `protocolEra: "modern"`.

## CI proof

The workflow verifies all PHP and probe syntax, installs disposable WordPress, activates the MCP plugin and the independent fixture plugin, then verifies:

1. anonymous MCP access is denied;
2. invalid browser Origin is denied;
3. authenticated `server/discover` succeeds;
4. only MCP `2026-07-28` is advertised;
5. `tools/list` discovers both the core and externally registered tools;
6. `probe.site` executes across real HTTP;
7. `fixture.echo` executes across the same unchanged transport/router stack;
8. Base64-sentinel `Mcp-Name` decoding works;
9. header/body protocol mismatch returns MCP `HeaderMismatch` (`-32020`);
10. a mutually matched but unsupported protocol returns `UnsupportedProtocolVersion` (`-32022`) with supported/requested data;
11. legacy initialization is rejected;
12. the official MCP TypeScript v2 client discovers and calls both tools; and
13. the official MCP Inspector CLI discovers the dynamic registry and calls the external fixture over the same HTTP endpoint.

## Deliberate omissions

This proof still contains no blanket administration, plugin-specific administration, writes, shell commands, SQL, filesystem access, Git operations, browser automation, legacy MCP compatibility layer, or production deployment code.

The next architectural layer can now be developed behind the registry without rewriting the proven MCP tunnel.
