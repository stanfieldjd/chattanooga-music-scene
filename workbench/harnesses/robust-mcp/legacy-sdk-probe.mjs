import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
if (!endpoint) {
  throw new Error('MCP_ENDPOINT is required.');
}

// No versionNegotiation option: the official v2 client intentionally defaults
// to the handshake/legacy era. This is a compatibility requirement, not an
// accidental fallback.
const client = new Client({ name: 'robust-mcp-legacy-sdk-probe', version: '1.0.0' });
const transport = new StreamableHTTPClientTransport(new URL(endpoint));

try {
  await client.connect(transport);

  if (client.getProtocolEra() !== 'legacy') {
    throw new Error(`Expected legacy MCP era, got ${String(client.getProtocolEra())}.`);
  }

  const server = client.getServerVersion();
  if (!server || server.name !== 'chattanooga-robust-mcp' || server.version !== '0.2.0') {
    throw new Error(`Unexpected server identity: ${JSON.stringify(server)}`);
  }

  const listed = await client.listTools();
  const names = Array.isArray(listed.tools) ? listed.tools.map((tool) => tool.name).sort() : [];
  if (JSON.stringify(names) !== JSON.stringify(['robust.bad-output', 'robust.echo'])) {
    throw new Error(`Unexpected legacy tool catalog: ${JSON.stringify(names)}`);
  }

  const called = await client.callTool({
    name: 'robust.echo',
    arguments: { text: 'legacy-client-works', mode: 'plain', tags: ['legacy'] },
  });
  if (called.isError !== false || called.structuredContent?.echo !== 'legacy-client-works') {
    throw new Error(`Legacy robust.echo failed: ${JSON.stringify(called)}`);
  }

  console.log('robust-mcp-legacy-sdk: PASS era=legacy same-endpoint tools=2');
} finally {
  await client.close().catch(() => {});
}
