import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
const token = process.env.MCP_BEARER_TOKEN;

if (!endpoint || !token) {
  throw new Error('MCP_ENDPOINT and MCP_BEARER_TOKEN are required.');
}

const client = new Client(
  { name: 'minimal-mcp-bearer-sdk-probe', version: '1.0.0' },
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
  if (!server || server.name !== 'minimal-mcp-tunnel' || server.version !== '0.0.5') {
    throw new Error(`Unexpected server identity: ${JSON.stringify(server)}`);
  }

  const listed = await client.listTools();
  const names = listed.tools.map((tool) => tool.name).sort();
  if (JSON.stringify(names) !== JSON.stringify(['fixture.echo', 'probe.site'])) {
    throw new Error(`Unexpected tool catalog: ${JSON.stringify(listed)}`);
  }

  const called = await client.callTool({ name: 'probe.site', arguments: {} });
  if (called.isError !== false || called.structuredContent?.ok !== true) {
    throw new Error(`Bearer-authenticated probe.site failed: ${JSON.stringify(called)}`);
  }

  console.log('minimal-mcp-bearer-sdk: PASS protocol=2026-07-28 bearer=manual');
} finally {
  await client.close().catch(() => {});
}
