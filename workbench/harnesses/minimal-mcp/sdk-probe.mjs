import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
const user = process.env.MCP_USER;
const password = process.env.MCP_PASSWORD;

if (!endpoint || !user || !password) {
  throw new Error('MCP_ENDPOINT, MCP_USER, and MCP_PASSWORD are required.');
}

const authorization = `Basic ${Buffer.from(`${user}:${password}`, 'utf8').toString('base64')}`;

const client = new Client(
  { name: 'minimal-mcp-official-sdk-probe', version: '1.0.0' },
  { versionNegotiation: { mode: { pin: '2026-07-28' } } }
);

const transport = new StreamableHTTPClientTransport(new URL(endpoint), {
  requestInit: {
    headers: {
      Authorization: authorization,
    },
  },
});

try {
  await client.connect(transport);

  if (client.getProtocolEra() !== 'modern') {
    throw new Error(`Expected modern MCP era, got ${String(client.getProtocolEra())}.`);
  }

  const server = client.getServerVersion();
  if (!server || server.name !== 'minimal-mcp-tunnel' || server.version !== '0.0.1') {
    throw new Error(`Unexpected server identity: ${JSON.stringify(server)}`);
  }

  const listed = await client.listTools();
  if (!Array.isArray(listed.tools) || listed.tools.length !== 1 || listed.tools[0].name !== 'probe.site') {
    throw new Error(`Unexpected tool catalog: ${JSON.stringify(listed)}`);
  }

  const called = await client.callTool({ name: 'probe.site', arguments: {} });
  if (called.isError !== false) {
    throw new Error(`probe.site returned an MCP tool error: ${JSON.stringify(called)}`);
  }
  if (!called.structuredContent || called.structuredContent.ok !== true) {
    throw new Error(`probe.site did not return structured site proof: ${JSON.stringify(called)}`);
  }
  if (called.structuredContent.siteTitle !== 'Minimal MCP Tunnel') {
    throw new Error(`Unexpected WordPress title: ${JSON.stringify(called.structuredContent)}`);
  }

  console.log('minimal-mcp-official-sdk: PASS protocol=2026-07-28 era=modern tool=probe.site');
} finally {
  await client.close().catch(() => {});
}
