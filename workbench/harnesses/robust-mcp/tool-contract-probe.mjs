import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Client, StreamableHTTPClientTransport } from '@modelcontextprotocol/client';

const endpoint = process.env.MCP_ENDPOINT;
if (!endpoint) {
  throw new Error('MCP_ENDPOINT is required.');
}

const here = path.dirname(fileURLToPath(import.meta.url));
const baselinePath = path.join(here, 'tool-contract-baseline.json');
const baseline = JSON.parse(fs.readFileSync(baselinePath, 'utf8'));

function canonicalize(value) {
  if (Array.isArray(value)) {
    return value.map(canonicalize);
  }
  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.keys(value)
        .sort()
        .map((key) => [key, canonicalize(value[key])])
    );
  }
  return value;
}

function stable(value) {
  return JSON.stringify(canonicalize(value));
}

function contractView(tool) {
  return {
    name: tool.name,
    title: tool.title,
    description: tool.description,
    annotations: tool.annotations ?? null,
    inputSchema: tool.inputSchema ?? null,
    outputSchema: tool.outputSchema ?? null,
  };
}

const client = new Client(
  { name: 'chatgpt-frozen-contract-probe', version: '1.0.0' },
  { versionNegotiation: { mode: 'auto' } }
);
const transport = new StreamableHTTPClientTransport(new URL(endpoint));

try {
  await client.connect(transport);

  const server = client.getServerVersion();
  if (!server || server.name !== baseline.server) {
    throw new Error(`Server identity drifted from frozen contract: ${JSON.stringify(server)}`);
  }

  const response = await client.listTools();
  if (!Array.isArray(response.tools)) {
    throw new Error('tools/list did not return a tool array.');
  }

  const actual = response.tools.map(contractView).sort((a, b) => a.name.localeCompare(b.name));
  const expected = [...baseline.tools].sort((a, b) => a.name.localeCompare(b.name));

  const actualNames = actual.map((tool) => tool.name);
  const expectedNames = expected.map((tool) => tool.name);
  if (stable(actualNames) !== stable(expectedNames)) {
    throw new Error(
      `Frozen ChatGPT tool catalog changed without a baseline review. ` +
      `expected=${JSON.stringify(expectedNames)} actual=${JSON.stringify(actualNames)}`
    );
  }

  for (let index = 0; index < expected.length; index += 1) {
    if (stable(actual[index]) !== stable(expected[index])) {
      throw new Error(
        `Frozen ChatGPT tool contract drift for ${expected[index].name}. ` +
        `Update the baseline only as an explicit reviewed app-contract change.\n` +
        `expected=${JSON.stringify(canonicalize(expected[index]))}\n` +
        `actual=${JSON.stringify(canonicalize(actual[index]))}`
      );
    }
  }

  console.log(
    `robust-mcp-tool-contract: PASS catalog_version=${baseline.catalog_version} ` +
    `tools=${expectedNames.length} frozen_snapshot_compatible=true`
  );
} finally {
  await client.close().catch(() => {});
}
