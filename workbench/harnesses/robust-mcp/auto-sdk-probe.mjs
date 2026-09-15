import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
if (!endpoint) {
  throw new Error('MCP_ENDPOINT is required.');
}

const client = new Client(
  { name: 'robust-mcp-auto-sdk-probe', version: '1.0.0' },
  { versionNegotiation: { mode: 'auto' } }
);
const transport = new StreamableHTTPClientTransport(new URL(endpoint));

try {
  await client.connect(transport);

  if (client.getProtocolEra() !== 'modern') {
    throw new Error(`Auto negotiation did not select modern MCP: ${String(client.getProtocolEra())}.`);
  }

  const first = await client.listTools();
  const second = await client.listTools();
  const normalize = (value) => JSON.stringify(value, Object.keys(value ?? {}).sort());
  if (normalize(first) !== normalize(second)) {
    throw new Error('tools/list changed across identical auto-negotiated reads.');
  }

  const called = await client.callTool({
    name: 'robust.echo',
    arguments: { text: 'auto-negotiation-works', mode: 'plain', tags: ['auto'] },
  });
  if (called.isError !== false || called.structuredContent?.echo !== 'auto-negotiation-works') {
    throw new Error(`Auto-negotiated robust.echo failed: ${JSON.stringify(called)}`);
  }

  console.log('robust-mcp-auto-sdk: PASS era=modern negotiation=auto catalog=stable');
} finally {
  await client.close().catch(() => {});
}
