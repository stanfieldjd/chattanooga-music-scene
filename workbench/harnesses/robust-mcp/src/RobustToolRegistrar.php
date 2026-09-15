<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;

final class RobustToolRegistrar
{
    public function __construct(
        private readonly Builder $builder,
        private readonly SchemaGuard $guard,
    ) {
    }

    /**
     * Register a dynamically defined tool only after its schemas pass the robust guard.
     *
     * JSON Schema boolean output schemas are normalized to equivalent object schemas
     * because the PHP SDK's Tool value object currently accepts array schemas.
     *
     * @param array<string,mixed> $inputSchema
     * @param array<string,mixed>|bool|null $outputSchema
     */
    public function addTool(
        string $name,
        string $description,
        array $inputSchema,
        array|bool|null $outputSchema,
        callable $callback,
        ?string $title = null,
        ?ToolAnnotations $annotations = null,
    ): void {
        if (($inputSchema['type'] ?? null) !== 'object') {
            throw new SchemaGuardException(sprintf('Input schema for tool "%s" must have a JSON Schema root type of object.', $name));
        }

        $normalizedOutput = match ($outputSchema) {
            true => [],
            false => ['not' => []],
            default => $outputSchema,
        };

        // Mcp\Schema\Tool performs the SDK's canonical normalization of empty
        // sub-schemas before we validate or advertise the definition.
        $tool = new Tool(
            name: $name,
            title: $title,
            inputSchema: $inputSchema,
            description: $description,
            annotations: $annotations,
            outputSchema: $normalizedOutput,
        );

        $this->guard->assertSafeSchema($tool->inputSchema, sprintf('inputSchema for tool "%s"', $name));
        if (null !== $tool->outputSchema) {
            $this->guard->assertSafeSchema($tool->outputSchema, sprintf('outputSchema for tool "%s"', $name));
        }

        $handler = new GuardedToolHandler(
            $name,
            $tool->inputSchema,
            $tool->outputSchema,
            $callback,
            $this->guard,
        );

        $this->builder->add($tool, $handler);
    }
}
