# ChatGPT integration hardening

This workbench remains MCP/runtime only. It does not implement WordPress administration.

Goals for this branch:

- one remote `/mcp` endpoint serving both MCP handshake-era and 2026-07-28 modern clients;
- deterministic, richly described tool catalogs suitable for ChatGPT tool scanning;
- strict JSON Schema input/output enforcement;
- explicit server identity and model-facing instructions;
- exclusive manual-bearer authentication when configured, applied to every MCP transport method;
- persistent handshake-era sessions suitable for multi-request PHP runtimes;
- bounded request bodies and explicit HTTP media semantics;
- health/readiness endpoints that disclose no credentials or site data;
- correlation IDs and structured transport logging without credential/body logging;
- CI coverage through raw HTTP, the official TypeScript client in modern, legacy, and auto-negotiation modes, and MCP Inspector;
- a ChatGPT-scan compatibility probe modeled after OpenAI MCPKit/custom-app behavior;
- locked and audited PHP/Node dependency graphs.

The branch must not add CRUD, generic administrators, plugin mutation adapters, or other WordPress administration surfaces.
