<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;

final class RobustToolRegistrar
{
    private const TOOL_NAME = '/^[A-Za-z0-9_.-]{1,128}$/D';
    private const MAX_TITLE_BYTES = 128;
    private const MAX_DESCRIPTION_BYTES = 4096;

    /** @var array<string,true> */
    private array $registeredNames = [];

    public function __construct(
        private readonly Builder $builder,
        private readonly SchemaGuard $guard,
        private readonly int $maxResultBytes = GuardedToolHandler::DEFAULT_MAX_RESULT_BYTES,
    ) {
    }

    /**
     * Register a tool only after its model-facing contract is complete and its
     * schemas pass the robust guard. This server deliberately requires an
     * output schema and all four MCP safety hints so ChatGPT never scans an
     * ambiguous tool contract.
     *
     * @param array<string,mixed> $inputSchema
     * @param array<string,mixed>|bool $outputSchema
     */
    public function addTool(
        string $name,
        string $description,
        array $inputSchema,
        array|bool $outputSchema,
        callable $callback,
        string $title,
        ToolAnnotations $annotations,
    ): void {
        if (1 !== preg_match(self::TOOL_NAME, $name)) {
            throw new SchemaGuardException(sprintf(
                'Tool name "%s" must be 1-128 ASCII letters, digits, underscores, hyphens, or dots.',
                $name,
            ));
        }
        if (isset($this->registeredNames[$name])) {
            throw new SchemaGuardException(sprintf('Tool name "%s" is already registered.', $name));
        }

        $title = trim($title);
        if ('' === $title || strlen($title) > self::MAX_TITLE_BYTES || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $title)) {
            throw new SchemaGuardException(sprintf('Tool "%s" must have a safe non-empty title of at most %d bytes.', $name, self::MAX_TITLE_BYTES));
        }

        $description = trim($description);
        if (strlen($description) < 20 || strlen($description) > self::MAX_DESCRIPTION_BYTES || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $description)) {
            throw new SchemaGuardException(sprintf('Tool "%s" must have a model-usable description of 20-%d bytes.', $name, self::MAX_DESCRIPTION_BYTES));
        }

        foreach (['readOnlyHint', 'destructiveHint', 'idempotentHint', 'openWorldHint'] as $hint) {
            if (null === $annotations->{$hint}) {
                throw new SchemaGuardException(sprintf('Tool "%s" must explicitly define annotation %s.', $name, $hint));
            }
        }
        if (true === $annotations->readOnlyHint && true === $annotations->destructiveHint) {
            throw new SchemaGuardException(sprintf('Tool "%s" cannot be both read-only and destructive.', $name));
        }

        if (($inputSchema['type'] ?? null) !== 'object') {
            throw new SchemaGuardException(sprintf('Input schema for tool "%s" must have a JSON Schema root type of object.', $name));
        }

        $normalizedOutput = match ($outputSchema) {
            true => [],
            false => ['not' => []],
            default => $outputSchema,
        };

        $tool = new Tool(
            name: $name,
            title: $title,
            inputSchema: $inputSchema,
            description: $description,
            annotations: $annotations,
            outputSchema: $normalizedOutput,
        );

        $this->guard->assertSafeSchema($tool->inputSchema, sprintf('inputSchema for tool "%s"', $name));
        if (null === $tool->outputSchema) {
            throw new SchemaGuardException(sprintf('Tool "%s" must declare an outputSchema.', $name));
        }
        if (($tool->outputSchema['type'] ?? null) !== 'object') {
            throw new SchemaGuardException(sprintf('Output schema for tool "%s" must have a JSON Schema root type of object.', $name));
        }
        $this->guard->assertSafeSchema($tool->outputSchema, sprintf('outputSchema for tool "%s"', $name));

        $handler = new GuardedToolHandler(
            $name,
            $tool->inputSchema,
            $tool->outputSchema,
            $callback,
            $this->guard,
            $this->maxResultBytes,
        );

        $this->builder->add($tool, $handler);
        $this->registeredNames[$name] = true;
    }
}
