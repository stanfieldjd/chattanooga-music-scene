import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
const token = process.env.MCP_BEARER_TOKEN;
if (!endpoint || !token) {
  throw new Error('MCP_ENDPOINT and MCP_BEARER_TOKEN are required.');
}

const client = new Client({ name: 'robust-mcp-bearer-legacy-probe', version: '1.0.0' });
const transport = new StreamableHTTPClientTransport(new URL(endpoint), {
  requestInit: { headers: { Authorization: `Bearer ${token}` } },
});

try {
  await client.connect(transport);
  if (client.getProtocolEra() !== 'legacy') {
    throw new Error(`Expected authenticated legacy era, got ${String(client.getProtocolEra())}.`);
  }
  const called = await client.callTool({
    name: 'robust.echo',
    arguments: { text: 'bearer-legacy-works', mode: 'plain', tags: ['legacy', 'auth'] },
  });
  if (called.isError !== false || called.structuredContent?.echo !== 'bearer-legacy-works') {
    throw new Error(`Authenticated legacy call failed: ${JSON.stringify(called)}`);
  }
  console.log('robust-mcp-bearer-legacy-sdk: PASS era=legacy bearer=manual');
} finally {
  await client.close().catch(() => {});
}
