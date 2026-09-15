import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
if (!endpoint) {
  throw new Error('MCP_ENDPOINT is required.');
}

// This exercises the public MCP operations a ChatGPT custom-app tool scan
// depends on: negotiate, identify the server, enumerate a deterministic tool
// catalog with model-usable metadata, and execute a discovered tool.
const client = new Client(
  { name: 'chatgpt-custom-app-scan-probe', version: '1.0.0' },
  { versionNegotiation: { mode: 'auto' } }
);
const transport = new StreamableHTTPClientTransport(new URL(endpoint));

try {
  await client.connect(transport);

  const server = client.getServerVersion();
  if (!server || server.name !== 'chattanooga-robust-mcp' || server.version !== '0.2.0') {
    throw new Error(`ChatGPT scan saw unexpected server identity: ${JSON.stringify(server)}`);
  }

  const first = await client.listTools();
  const second = await client.listTools();
  if (JSON.stringify(first) !== JSON.stringify(second)) {
    throw new Error('Tool catalog is not deterministic across identical scans.');
  }

  if (!Array.isArray(first.tools) || first.tools.length !== 2) {
    throw new Error(`Unexpected scan catalog: ${JSON.stringify(first)}`);
  }

  for (const tool of first.tools) {
    if (typeof tool.name !== 'string' || tool.name.length === 0) {
      throw new Error(`Tool missing stable name: ${JSON.stringify(tool)}`);
    }
    if (typeof tool.title !== 'string' || tool.title.length === 0) {
      throw new Error(`Tool ${tool.name} missing model/UI title.`);
    }
    if (typeof tool.description !== 'string' || tool.description.length < 20) {
      throw new Error(`Tool ${tool.name} description is too weak for reliable selection.`);
    }
    if (!tool.inputSchema || tool.inputSchema.type !== 'object') {
      throw new Error(`Tool ${tool.name} lacks an object input schema.`);
    }
    const a = tool.annotations;
    if (!a || a.readOnlyHint !== true || a.destructiveHint !== false || a.idempotentHint !== true || a.openWorldHint !== false) {
      throw new Error(`Tool ${tool.name} lacks explicit safety annotations: ${JSON.stringify(a)}`);
    }
  }

  const echo = first.tools.find((tool) => tool.name === 'robust.echo');
  if (!echo?.outputSchema || echo.outputSchema.type !== 'object') {
    throw new Error('robust.echo lacks a declared output schema.');
  }

  const result = await client.callTool({
    name: 'robust.echo',
    arguments: { text: 'chatgpt-scan-compatible', mode: 'plain', tags: ['chatgpt'] },
  });
  if (result.isError !== false || result.structuredContent?.echo !== 'chatgpt-scan-compatible') {
    throw new Error(`Discovered tool failed after scan: ${JSON.stringify(result)}`);
  }

  console.log('robust-mcp-chatgpt-scan: PASS identity catalog metadata annotations deterministic execution');
} finally {
  await client.close().catch(() => {});
}
