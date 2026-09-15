import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
const token = process.env.MCP_OAUTH_TOKEN;
const requestedEra = process.env.MCP_EXPECTED_ERA ?? 'modern';
if (!endpoint || !token) {
  throw new Error('MCP_ENDPOINT and MCP_OAUTH_TOKEN are required.');
}
if (!['modern', 'legacy', 'auto'].includes(requestedEra)) {
  throw new Error(`Unsupported MCP_EXPECTED_ERA: ${requestedEra}`);
}

const options = requestedEra === 'modern'
  ? { versionNegotiation: { mode: { pin: '2026-07-28' } } }
  : requestedEra === 'auto'
    ? { versionNegotiation: { mode: 'auto' } }
    : {};

const client = new Client(
  { name: `robust-mcp-oauth-${requestedEra}-probe`, version: '1.0.0' },
  options
);
const transport = new StreamableHTTPClientTransport(new URL(endpoint), {
  requestInit: { headers: { Authorization: `Bearer ${token}` } },
});

try {
  await client.connect(transport);

  const actualEra = client.getProtocolEra();
  const expectedEra = requestedEra === 'auto' ? 'modern' : requestedEra;
  if (actualEra !== expectedEra) {
    throw new Error(`OAuth ${requestedEra} probe expected ${expectedEra}, got ${String(actualEra)}.`);
  }

  const server = client.getServerVersion();
  if (!server || server.name !== 'chattanooga-robust-mcp' || server.version !== '0.2.0') {
    throw new Error(`Unexpected OAuth server identity: ${JSON.stringify(server)}`);
  }

  const listed = await client.listTools();
  const names = Array.isArray(listed.tools) ? listed.tools.map((tool) => tool.name).sort() : [];
  if (JSON.stringify(names) !== JSON.stringify(['robust.bad-output', 'robust.echo'])) {
    throw new Error(`Unexpected OAuth tool catalog: ${JSON.stringify(names)}`);
  }

  const called = await client.callTool({
    name: 'robust.echo',
    arguments: { text: `oauth-${requestedEra}-works`, mode: 'plain', tags: ['oauth', requestedEra] },
  });
  if (called.isError !== false || called.structuredContent?.echo !== `oauth-${requestedEra}-works`) {
    throw new Error(`OAuth ${requestedEra} call failed: ${JSON.stringify(called)}`);
  }

  console.log(`robust-mcp-oauth-sdk: PASS requested=${requestedEra} era=${actualEra} auth=oauth-jwt`);
} finally {
  await client.close().catch(() => {});
}
