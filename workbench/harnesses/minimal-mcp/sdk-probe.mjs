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
  if (!server || server.name !== 'minimal-mcp-tunnel' || server.version !== '0.0.5') {
    throw new Error(`Unexpected server identity: ${JSON.stringify(server)}`);
  }

  const listed = await client.listTools();
  const names = Array.isArray(listed.tools) ? listed.tools.map((tool) => tool.name).sort() : [];
  if (JSON.stringify(names) !== JSON.stringify(['fixture.echo', 'probe.site'])) {
    throw new Error(`Unexpected tool catalog: ${JSON.stringify(listed)}`);
  }

  const echoDefinition = listed.tools.find((tool) => tool.name === 'fixture.echo');
  if (echoDefinition?.inputSchema?.properties?.text?.['x-mcp-header'] !== 'Text') {
    throw new Error(`fixture.echo is missing x-mcp-header metadata: ${JSON.stringify(echoDefinition)}`);
  }

  const site = await client.callTool({ name: 'probe.site', arguments: {} });
  if (site.isError !== false || !site.structuredContent || site.structuredContent.ok !== true) {
    throw new Error(`probe.site failed: ${JSON.stringify(site)}`);
  }
  if (site.structuredContent.siteTitle !== 'Minimal MCP Tunnel') {
    throw new Error(`Unexpected WordPress title: ${JSON.stringify(site.structuredContent)}`);
  }

  const echo = await client.callTool({ name: 'fixture.echo', arguments: { text: 'registry-works' } });
  if (echo.isError !== false || !echo.structuredContent) {
    throw new Error(`fixture.echo failed: ${JSON.stringify(echo)}`);
  }
  if (echo.structuredContent.echo !== 'registry-works' || echo.structuredContent.source !== 'external-wordpress-plugin') {
    throw new Error(`Unexpected external fixture result: ${JSON.stringify(echo.structuredContent)}`);
  }

  const unicode = await client.callTool({ name: 'fixture.echo', arguments: { text: ' Hello, 世界 ' } });
  if (unicode.isError !== false || unicode.structuredContent?.echo !== ' Hello, 世界 ') {
    throw new Error(`SDK Base64 mirrored-header path failed: ${JSON.stringify(unicode)}`);
  }

  let unknownRejected = false;
  try {
    await client.callTool({ name: 'missing.tool', arguments: {} });
  } catch (error) {
    const code = error && typeof error === 'object' && 'code' in error ? error.code : undefined;
    if (code !== -32602) {
      throw new Error(`Unknown tool rejected with unexpected error: ${String(error)} code=${String(code)}`);
    }
    unknownRejected = true;
  }
  if (!unknownRejected) {
    throw new Error('Unknown tool was not rejected as JSON-RPC -32602 Invalid Params.');
  }

  console.log('minimal-mcp-official-sdk: PASS protocol=2026-07-28 era=modern tools=2 x-mcp-header=verified unknown-tool=-32602');
} finally {
  await client.close().catch(() => {});
}
