<?php

declare(strict_types=1);

use Chattanooga\RobustMcp\ManualBearerAuthMiddleware;
use Chattanooga\RobustMcp\McpHttpSemanticsMiddleware;
use Chattanooga\RobustMcp\RobustToolRegistrar;
use Chattanooga\RobustMcp\SchemaGuard;
use Mcp\Schema\Enum\CacheScope;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\ClientGateway;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Mcp\Server\Wire\CachePolicy;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/vendor/autoload.php';

const ROBUST_MCP_NAME = 'chattanooga-robust-mcp';
const ROBUST_MCP_TITLE = 'Chattanooga Robust MCP';
const ROBUST_MCP_VERSION = '0.2.0';

$factory = new Psr17Factory();
$requestCreator = new ServerRequestCreator($factory, $factory, $factory, $factory);
$request = $requestCreator->fromGlobals();
$path = $request->getUri()->getPath();

$jsonResponse = static function (int $status, array $payload, array $headers = []): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

if ('/healthz' === $path || '/readyz' === $path) {
    if ('GET' !== strtoupper($request->getMethod())) {
        $jsonResponse(405, ['status' => 'error', 'reason' => 'method_not_allowed'], ['Allow' => 'GET']);
    }
    $jsonResponse(200, [
        'status' => 'ok',
        'server' => ROBUST_MCP_NAME,
        'version' => ROBUST_MCP_VERSION,
        'check' => '/readyz' === $path ? 'ready' : 'health',
    ]);
}

if ('/mcp' !== $path) {
    $jsonResponse(404, ['error' => 'not_found']);
}

$configurationFailure = static function () use ($jsonResponse): never {
    $jsonResponse(500, [
        'error' => 'server_configuration_error',
        'error_description' => 'The MCP server configuration is invalid.',
    ]);
};

$authModeValue = getenv('ROBUST_MCP_AUTH_MODE');
$digestValue = getenv('ROBUST_MCP_BEARER_SHA256');
$digestConfigured = false !== $digestValue && '' !== trim($digestValue);

if (false === $authModeValue || '' === trim($authModeValue)) {
    $authMode = $digestConfigured ? 'manual-bearer' : 'none';
} else {
    $authMode = trim($authModeValue);
}

if (!in_array($authMode, ['none', 'manual-bearer'], true)) {
    $configurationFailure();
}
if ('none' === $authMode && $digestConfigured) {
    $configurationFailure();
}
if ('manual-bearer' === $authMode && !$digestConfigured) {
    $configurationFailure();
}

$sessionDirValue = getenv('ROBUST_MCP_SESSION_DIR');
$sessionDir = false === $sessionDirValue || '' === trim($sessionDirValue)
    ? sys_get_temp_dir() . '/chattanooga-robust-mcp-sessions'
    : trim($sessionDirValue);
if (str_contains($sessionDir, "\0")) {
    $configurationFailure();
}
if (!is_dir($sessionDir) && !@mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
    $configurationFailure();
}
@chmod($sessionDir, 0700);
if (!is_dir($sessionDir) || !is_writable($sessionDir)) {
    $configurationFailure();
}

$sessionTtlValue = getenv('ROBUST_MCP_SESSION_TTL');
$sessionTtl = false === $sessionTtlValue || '' === trim($sessionTtlValue) ? 3600 : filter_var($sessionTtlValue, FILTER_VALIDATE_INT);
if (false === $sessionTtl || $sessionTtl < 60 || $sessionTtl > 86400) {
    $configurationFailure();
}

$builder = Server::builder()
    ->setServerInfo(
        ROBUST_MCP_NAME,
        ROBUST_MCP_VERSION,
        'Dual-era MCP endpoint engineered for strict schemas, deterministic discovery, resilient HTTP transport, and ChatGPT custom-app compatibility.',
        title: ROBUST_MCP_TITLE,
    )
    ->setInstructions(
        'Use only capabilities returned by MCP discovery. Treat each tool input and output schema as authoritative. '
        . 'Prefer read-only and idempotent tools when multiple tools could satisfy a request. Do not infer hidden capabilities. '
        . 'A tool result marked as an error is not a successful action and must not be represented as one. '
        . 'This endpoint currently exposes MCP/runtime proof capabilities and discovery metadata; it does not expose WordPress administration or mutation tools.'
    )
    ->setSession(new FileSessionStore($sessionDir, (int) $sessionTtl))
    ->setCachePolicy(
        CachePolicy::default(0, CacheScope::Private)
            ->withMethod('tools/list', 300_000, CacheScope::Private)
    )
    ->setModernVersions([ProtocolVersion::V2026_07_28])
    ->setLazyLoading(false);

$guard = new SchemaGuard();
$tools = new RobustToolRegistrar($builder, $guard);
$counterFile = getenv('ROBUST_MCP_COUNTER_FILE') ?: '/tmp/robust-mcp-handler-count';
$readOnlyAnnotations = new ToolAnnotations(
    readOnlyHint: true,
    destructiveHint: false,
    idempotentHint: true,
    openWorldHint: false,
);

$tools->addTool(
    name: 'robust.echo',
    title: 'Robust Echo',
    description: 'Read-only schema-conformance probe. Echoes validated input to prove deterministic MCP tool discovery, JSON Schema 2020-12 validation, and mirrored-header interoperability.',
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
    annotations: $readOnlyAnnotations,
);

$tools->addTool(
    name: 'robust.bad-output',
    title: 'Output Guard Probe',
    description: 'Read-only CI probe that intentionally violates its declared output schema so clients and tests can verify that invalid structured output is contained as a tool error.',
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
    annotations: $readOnlyAnnotations,
);

$server = $builder->build();
$middleware = StreamableHttpTransport::defaultMiddleware();

if ('manual-bearer' === $authMode) {
    try {
        $middleware[] = new ManualBearerAuthMiddleware(
            expectedSha256: (string) $digestValue,
            responseFactory: $factory,
            streamFactory: $factory,
        );
    } catch (\InvalidArgumentException) {
        $configurationFailure();
    }
}

$middleware[] = new McpHttpSemanticsMiddleware($factory, $factory);

$transport = new StreamableHttpTransport(
    request: $request,
    responseFactory: $factory,
    streamFactory: $factory,
    middleware: $middleware,
    maxBodyBytes: 1024 * 1024,
);
$response = $server->run($transport);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
echo (string) $response->getBody();
