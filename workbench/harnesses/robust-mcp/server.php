<?php

declare(strict_types=1);

use Chattanooga\RobustMcp\RobustToolRegistrar;
use Chattanooga\RobustMcp\SchemaGuard;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\ClientGateway;
use Mcp\Server\Transport\StatelessHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/vendor/autoload.php';

$factory = new Psr17Factory();
$requestCreator = new ServerRequestCreator($factory, $factory, $factory, $factory);
$request = $requestCreator->fromGlobals();

if ('/mcp' !== $request->getUri()->getPath()) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR);
    exit;
}

$builder = Server::builder()
    ->setServerInfo(
        'chattanooga-robust-mcp',
        '0.1.0',
        'Robust MCP 2026-07-28 workbench using the official PHP SDK with strict schema guards.',
    )
    ->setLazyLoading(false);

$guard = new SchemaGuard();
$tools = new RobustToolRegistrar($builder, $guard);
$counterFile = getenv('ROBUST_MCP_COUNTER_FILE') ?: '/tmp/robust-mcp-handler-count';

$tools->addTool(
    name: 'robust.echo',
    title: 'Robust Echo',
    description: 'Schema-heavy read-only fixture proving JSON Schema 2020-12, mirrored headers, and guarded output.',
    inputSchema: [
        '$schema' => SchemaGuard::DRAFT_2020_12,
        'type' => 'object',
        '$defs' => [
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32],
                'maxItems' => 8,
                'uniqueItems' => true,
            ],
        ],
        'properties' => [
            'text' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 256,
                'x-mcp-header' => 'Text',
            ],
            'mode' => [
                'type' => 'string',
                'enum' => ['plain', 'counted'],
            ],
            'count' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 1000,
            ],
            'tags' => ['$ref' => '#/$defs/tags'],
            'meta' => [
                'type' => 'object',
                'properties' => [
                    'trace' => ['type' => 'string', 'maxLength' => 64],
                ],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['text', 'mode'],
        'allOf' => [
            [
                'if' => [
                    'properties' => ['mode' => ['const' => 'counted']],
                    'required' => ['mode'],
                ],
                'then' => ['required' => ['count']],
            ],
        ],
        'additionalProperties' => false,
    ],
    outputSchema: [
        '$schema' => SchemaGuard::DRAFT_2020_12,
        'type' => 'object',
        '$defs' => [
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'maxItems' => 8,
            ],
        ],
        'properties' => [
            'echo' => ['type' => 'string'],
            'source' => ['const' => 'robust-mcp'],
            'count' => ['type' => 'integer'],
            'tags' => ['$ref' => '#/$defs/tags'],
        ],
        'required' => ['echo', 'source', 'tags'],
        'additionalProperties' => false,
    ],
    callback: static function (array $arguments, ClientGateway $gateway) use ($counterFile): array {
        unset($gateway);
        file_put_contents($counterFile, "1\n", FILE_APPEND | LOCK_EX);

        $result = [
            'echo' => $arguments['text'],
            'source' => 'robust-mcp',
            'tags' => $arguments['tags'] ?? [],
        ];
        if (array_key_exists('count', $arguments)) {
            $result['count'] = $arguments['count'];
        }
        return $result;
    },
);

$tools->addTool(
    name: 'robust.bad-output',
    title: 'Broken Output Fixture',
    description: 'Intentional CI fixture whose callback violates its outputSchema.',
    inputSchema: [
        '$schema' => SchemaGuard::DRAFT_2020_12,
        'type' => 'object',
        'properties' => new stdClass(),
        'additionalProperties' => false,
    ],
    outputSchema: [
        '$schema' => SchemaGuard::DRAFT_2020_12,
        'type' => 'object',
        'properties' => [
            'ok' => ['type' => 'boolean'],
        ],
        'required' => ['ok'],
        'additionalProperties' => false,
    ],
    callback: static function (array $arguments, ClientGateway $gateway): array {
        unset($arguments, $gateway);
        return ['ok' => 'this-must-never-be-emitted-as-valid-structured-output'];
    },
);

$protocol = $builder->buildStateless([ProtocolVersion::V2026_07_28]);
$transport = new StatelessHttpTransport(
    protocol: $protocol,
    responseFactory: $factory,
    streamFactory: $factory,
    maxBodyBytes: 1024 * 1024,
);
$response = $transport->handle($request);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
echo (string) $response->getBody();
