import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
const user = process.env.MCP_USER;
const password = process.env.MCP_PASSWORD;

if (!endpoint || !user || !password) {
  throw new Error('MCP_ENDPOINT, MCP_USER, and MCP_PASSWORD are required.');
}

const authorization = `Basic ${Buffer.from(`${user}:${password}`, 'utf8').toString('base64')}`;
const client = new Client(
  { name: 'wordpress-ability-bridge-probe', version: '1.0.0' },
  { versionNegotiation: { mode: { pin: '2026-07-28' } } }
);
const transport = new StreamableHTTPClientTransport(new URL(endpoint), {
  requestInit: { headers: { Authorization: authorization } },
});

function requireTool(tools, name) {
  const tool = tools.find((candidate) => candidate.name === name);
  if (!tool) throw new Error(`Missing MCP tool ${name}: ${JSON.stringify(tools)}`);
  return tool;
}

try {
  await client.connect(transport);
  if (client.getProtocolEra() !== 'modern') {
    throw new Error(`Expected modern MCP era, got ${String(client.getProtocolEra())}.`);
  }

  const listed = await client.listTools();
  const tools = listed.tools ?? [];
  requireTool(tools, 'probe.site');
  requireTool(tools, 'wordpress.discover-abilities');
  const infoTool = requireTool(tools, 'wordpress.get-ability-info');
  const executeTool = requireTool(tools, 'wordpress.execute-ability');

  if (tools.some((tool) => tool.name.includes('mcp-fixture'))) {
    throw new Error(`WordPress abilities leaked into tools/list instead of staying layered: ${JSON.stringify(tools)}`);
  }
  if (infoTool.inputSchema?.properties?.ability_name?.['x-mcp-header'] !== 'Ability-Name') {
    throw new Error('get-ability-info is missing its modern routing header annotation.');
  }
  if (executeTool.inputSchema?.properties?.ability_name?.['x-mcp-header'] !== 'Ability-Name') {
    throw new Error('execute-ability is missing its modern routing header annotation.');
  }

  const discovered = await client.callTool({ name: 'wordpress.discover-abilities', arguments: {} });
  if (discovered.isError !== false || !discovered.structuredContent) {
    throw new Error(`Ability discovery failed: ${JSON.stringify(discovered)}`);
  }
  const abilityNames = (discovered.structuredContent.abilities ?? []).map((ability) => ability.name);
  for (const required of ['mcp-fixture/read-site-title', 'mcp-fixture/write-option']) {
    if (!abilityNames.includes(required)) throw new Error(`Missing public ability ${required}: ${JSON.stringify(abilityNames)}`);
  }
  for (const forbidden of ['mcp-fixture/private-ability', 'mcp-fixture/public-but-mcp-private']) {
    if (abilityNames.includes(forbidden)) throw new Error(`Private/opted-out ability leaked through discovery: ${forbidden}`);
  }

  const info = await client.callTool({
    name: 'wordpress.get-ability-info',
    arguments: { ability_name: 'mcp-fixture/write-option' },
  });
  if (info.isError !== false || info.structuredContent?.ability?.name !== 'mcp-fixture/write-option') {
    throw new Error(`Ability inspection failed: ${JSON.stringify(info)}`);
  }
  if (info.structuredContent.ability.input_schema?.properties?.value?.type !== 'string') {
    throw new Error(`Ability schema was not preserved: ${JSON.stringify(info.structuredContent)}`);
  }

  const read = await client.callTool({
    name: 'wordpress.execute-ability',
    arguments: { ability_name: 'mcp-fixture/read-site-title' },
  });
  if (read.isError !== false || read.structuredContent?.result !== 'MCP Ability Bridge') {
    throw new Error(`Read ability execution failed: ${JSON.stringify(read)}`);
  }

  const write = await client.callTool({
    name: 'wordpress.execute-ability',
    arguments: {
      ability_name: 'mcp-fixture/write-option',
      input: { value: 'bridge-write-verified' },
    },
  });
  if (write.isError !== false || write.structuredContent?.result?.value !== 'bridge-write-verified') {
    throw new Error(`Write ability execution failed: ${JSON.stringify(write)}`);
  }

  for (const forbidden of ['mcp-fixture/private-ability', 'mcp-fixture/public-but-mcp-private']) {
    const denied = await client.callTool({
      name: 'wordpress.execute-ability',
      arguments: { ability_name: forbidden },
    });
    if (denied.isError !== true) {
      throw new Error(`Forbidden ability unexpectedly executed: ${forbidden}: ${JSON.stringify(denied)}`);
    }
  }

  console.log(`wordpress-ability-bridge: PASS public=${abilityNames.length} layered=3 read=verified write=verified private=blocked`);
} finally {
  await client.close().catch(() => {});
}
