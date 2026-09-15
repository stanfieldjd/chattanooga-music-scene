import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
const token = process.env.MCP_BEARER_TOKEN;

if (!endpoint || !token) {
  throw new Error('MCP_ENDPOINT and MCP_BEARER_TOKEN are required.');
}

const client = new Client(
  { name: 'robust-mcp-bearer-sdk-probe', version: '1.0.0' },
  { versionNegotiation: { mode: { pin: '2026-07-28' } } }
);

const transport = new StreamableHTTPClientTransport(new URL(endpoint), {
  requestInit: {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  },
});

try {
  await client.connect(transport);

  if (client.getProtocolEra() !== 'modern') {
    throw new Error(`Expected modern MCP era, got ${String(client.getProtocolEra())}.`);
  }

  const server = client.getServerVersion();
  if (!server || server.name !== 'chattanooga-robust-mcp' || server.version !== '0.2.0') {
    throw new Error(`Unexpected server identity: ${JSON.stringify(server)}`);
  }

  const listed = await client.listTools();
  const names = Array.isArray(listed.tools) ? listed.tools.map((tool) => tool.name).sort() : [];
  if (JSON.stringify(names) !== JSON.stringify(['robust.bad-output', 'robust.echo'])) {
    throw new Error(`Unexpected tool catalog: ${JSON.stringify(names)}`);
  }

  const called = await client.callTool({
    name: 'robust.echo',
    arguments: { text: 'bearer-sdk-works', mode: 'plain', tags: ['auth'] },
  });
  if (called.isError !== false || called.structuredContent?.echo !== 'bearer-sdk-works') {
    throw new Error(`Bearer-authenticated robust.echo failed: ${JSON.stringify(called)}`);
  }

  console.log('robust-mcp-bearer-sdk: PASS protocol=2026-07-28 bearer=manual');
} finally {
  await client.close().catch(() => {});
}
