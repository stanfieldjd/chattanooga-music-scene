import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
if (!endpoint) {
  throw new Error('MCP_ENDPOINT is required.');
}

const client = new Client(
  { name: 'robust-mcp-official-sdk-probe', version: '1.0.0' },
  { versionNegotiation: { mode: { pin: '2026-07-28' } } }
);
const transport = new StreamableHTTPClientTransport(new URL(endpoint));

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

  const echoDefinition = listed.tools.find((tool) => tool.name === 'robust.echo');
  if (!echoDefinition?.inputSchema?.$defs || !Array.isArray(echoDefinition?.inputSchema?.allOf)) {
    throw new Error(`Full JSON Schema constructs missing from robust.echo: ${JSON.stringify(echoDefinition)}`);
  }
  if (echoDefinition?.inputSchema?.properties?.text?.['x-mcp-header'] !== 'Text') {
    throw new Error(`x-mcp-header metadata missing: ${JSON.stringify(echoDefinition)}`);
  }
  for (const tool of listed.tools) {
    const a = tool.annotations;
    if (!a || a.readOnlyHint !== true || a.destructiveHint !== false || a.idempotentHint !== true || a.openWorldHint !== false) {
      throw new Error(`Explicit tool annotations missing for ${tool.name}: ${JSON.stringify(a)}`);
    }
  }

  const counted = await client.callTool({
    name: 'robust.echo',
    arguments: {
      text: 'registry-works',
      mode: 'counted',
      count: 2,
      tags: ['schema', 'guard'],
      meta: { trace: 'sdk-probe' },
    },
  });
  if (counted.isError !== false || counted.structuredContent?.echo !== 'registry-works') {
    throw new Error(`robust.echo failed: ${JSON.stringify(counted)}`);
  }
  if (counted.structuredContent?.count !== 2 || counted.structuredContent?.source !== 'robust-mcp') {
    throw new Error(`Unexpected robust.echo output: ${JSON.stringify(counted.structuredContent)}`);
  }

  const unicode = await client.callTool({
    name: 'robust.echo',
    arguments: { text: ' Hello, 世界 ', mode: 'plain', tags: [] },
  });
  if (unicode.isError !== false || unicode.structuredContent?.echo !== ' Hello, 世界 ') {
    throw new Error(`Mirrored-header Unicode path failed: ${JSON.stringify(unicode)}`);
  }

  let invalidInputRejected = false;
  try {
    await client.callTool({ name: 'robust.echo', arguments: { text: 'missing-count', mode: 'counted' } });
  } catch (error) {
    const code = error && typeof error === 'object' && 'code' in error ? error.code : undefined;
    if (code !== -32602) {
      throw new Error(`Invalid input rejected with unexpected error code: ${String(code)} ${String(error)}`);
    }
    invalidInputRejected = true;
  }
  if (!invalidInputRejected) {
    throw new Error('Conditional inputSchema violation was not rejected as -32602 Invalid Params.');
  }

  const badOutput = await client.callTool({ name: 'robust.bad-output', arguments: {} });
  if (badOutput.isError !== true) {
    throw new Error(`Invalid output escaped as success: ${JSON.stringify(badOutput)}`);
  }
  if (badOutput.structuredContent !== undefined) {
    throw new Error(`Invalid structuredContent escaped the output guard: ${JSON.stringify(badOutput)}`);
  }

  let unknownRejected = false;
  try {
    await client.callTool({ name: 'missing.tool', arguments: {} });
  } catch (error) {
    const code = error && typeof error === 'object' && 'code' in error ? error.code : undefined;
    if (code !== -32602) {
      throw new Error(`Unknown tool rejected with unexpected code: ${String(code)} ${String(error)}`);
    }
    unknownRejected = true;
  }
  if (!unknownRejected) {
    throw new Error('Unknown tool was not rejected as -32602 Invalid Params.');
  }

  console.log('robust-mcp-official-sdk: PASS protocol=2026-07-28 era=modern schemas=2020-12 input=guarded output=guarded annotations=explicit tools=2');
} finally {
  await client.close().catch(() => {});
}
