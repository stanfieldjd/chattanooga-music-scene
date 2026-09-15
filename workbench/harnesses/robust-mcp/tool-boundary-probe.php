<?php

declare(strict_types=1);

use Chattanooga\RobustMcp\GuardedToolHandler;
use Chattanooga\RobustMcp\RobustToolRegistrar;
use Chattanooga\RobustMcp\SchemaGuard;
use Chattanooga\RobustMcp\SchemaGuardException;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\ClientGateway;

require __DIR__ . '/vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$expectGuardFailure = static function (callable $operation, string $label) use ($fail): void {
    try {
        $operation();
    } catch (SchemaGuardException) {
        return;
    }
    $fail($label . ' was unexpectedly accepted.');
};

$guard = new SchemaGuard();
$builder = Server::builder()->setServerInfo('tool-boundary-probe', '1.0.0');
$registrar = new RobustToolRegistrar($builder, $guard, 1024);
$annotations = new ToolAnnotations(
    readOnlyHint: true,
    destructiveHint: false,
    idempotentHint: true,
    openWorldHint: false,
);
$input = [
    '$schema' => SchemaGuard::DRAFT_2020_12,
    'type' => 'object',
    'properties' => [],
    'additionalProperties' => false,
];
$output = [
    '$schema' => SchemaGuard::DRAFT_2020_12,
    'type' => 'object',
    'properties' => ['ok' => ['type' => 'boolean']],
    'required' => ['ok'],
    'additionalProperties' => false,
];

$registrar->addTool(
    name: 'probe.valid',
    title: 'Valid Probe',
    description: 'Read-only fixture proving strict tool contract registration rules.',
    inputSchema: $input,
    outputSchema: $output,
    callback: static fn (): array => ['ok' => true],
    annotations: $annotations,
);

$expectGuardFailure(static fn () => $registrar->addTool(
    name: 'probe.valid',
    title: 'Duplicate Probe',
    description: 'Duplicate tool name must be rejected before reaching the SDK registry.',
    inputSchema: $input,
    outputSchema: $output,
    callback: static fn (): array => ['ok' => true],
    annotations: $annotations,
), 'duplicate tool name');

$expectGuardFailure(static fn () => $registrar->addTool(
    name: 'bad tool name',
    title: 'Bad Name',
    description: 'Tool names containing spaces must be rejected by the robust registrar.',
    inputSchema: $input,
    outputSchema: $output,
    callback: static fn (): array => ['ok' => true],
    annotations: $annotations,
), 'invalid tool name');

$expectGuardFailure(static fn () => $registrar->addTool(
    name: 'probe.short-description',
    title: 'Short Description',
    description: 'too short',
    inputSchema: $input,
    outputSchema: $output,
    callback: static fn (): array => ['ok' => true],
    annotations: $annotations,
), 'weak description');

$partialAnnotations = new ToolAnnotations(
    readOnlyHint: true,
    destructiveHint: false,
    idempotentHint: true,
    openWorldHint: null,
);
$expectGuardFailure(static fn () => $registrar->addTool(
    name: 'probe.partial-annotations',
    title: 'Partial Annotations',
    description: 'Every ChatGPT-visible safety annotation must be explicitly declared.',
    inputSchema: $input,
    outputSchema: $output,
    callback: static fn (): array => ['ok' => true],
    annotations: $partialAnnotations,
), 'partial safety annotations');

$contradictoryAnnotations = new ToolAnnotations(
    readOnlyHint: true,
    destructiveHint: true,
    idempotentHint: true,
    openWorldHint: false,
);
$expectGuardFailure(static fn () => $registrar->addTool(
    name: 'probe.contradictory',
    title: 'Contradictory Probe',
    description: 'A tool cannot truthfully advertise both read-only and destructive behavior.',
    inputSchema: $input,
    outputSchema: $output,
    callback: static fn (): array => ['ok' => true],
    annotations: $contradictoryAnnotations,
), 'contradictory safety annotations');

$expectGuardFailure(static fn () => $registrar->addTool(
    name: 'probe.bad-output-root',
    title: 'Bad Output Root',
    description: 'Tool outputs must expose an object-root structured contract for ChatGPT.',
    inputSchema: $input,
    outputSchema: ['$schema' => SchemaGuard::DRAFT_2020_12, 'type' => 'array', 'items' => ['type' => 'string']],
    callback: static fn (): array => [],
    annotations: $annotations,
), 'non-object output schema');

$largeOutputSchema = [
    '$schema' => SchemaGuard::DRAFT_2020_12,
    'type' => 'object',
    'properties' => ['payload' => ['type' => 'string']],
    'required' => ['payload'],
    'additionalProperties' => false,
];
$handler = new GuardedToolHandler(
    'probe.large-result',
    $input,
    $largeOutputSchema,
    static fn (): array => ['payload' => str_repeat('x', 2048)],
    $guard,
    1024,
);

$gateway = (new ReflectionClass(ClientGateway::class))->newInstanceWithoutConstructor();
try {
    $handler->execute([], $gateway);
    $fail('oversized tool result escaped the byte ceiling.');
} catch (ToolCallException $error) {
    if (!str_contains($error->getMessage(), 'larger than the configured')) {
        $fail('oversized tool result failed for the wrong reason: ' . $error->getMessage());
    }
}

echo 'robust-mcp-tool-boundary: PASS names=stable duplicates=rejected metadata=explicit schemas=object output-bytes=bounded' . PHP_EOL;
