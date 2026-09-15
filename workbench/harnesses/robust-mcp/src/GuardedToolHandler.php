<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Exception\ToolCallException;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

final class GuardedToolHandler implements ToolHandlerInterface
{
    private \Closure $callback;

    /**
     * @param array<string,mixed> $inputSchema
     * @param array<string,mixed>|null $outputSchema
     */
    public function __construct(
        private readonly string $name,
        private readonly array $inputSchema,
        private readonly ?array $outputSchema,
        callable $callback,
        private readonly SchemaGuard $guard,
    ) {
        $this->callback = \Closure::fromCallable($callback);
    }

    /**
     * @param array<string,mixed> $arguments
     */
    public function execute(array $arguments, ClientGateway $gateway): mixed
    {
        try {
            $this->guard->assertValidData($arguments, $this->inputSchema, sprintf('Input for tool "%s"', $this->name), true);
        } catch (SchemaGuardException $error) {
            throw new ToolCallException(sprintf('Tool "%s" rejected invalid input: %s', $this->name, $error->getMessage()), 0, $error);
        }

        try {
            $result = ($this->callback)($arguments, $gateway);
        } catch (ToolCallException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new ToolCallException(sprintf('Tool "%s" failed during execution.', $this->name), 0, $error);
        }

        if (null === $this->outputSchema) {
            return $result;
        }

        if ($result instanceof CallToolResult) {
            if ($result->isError) {
                return $result;
            }
            if (null === $result->structuredContent) {
                throw new ToolCallException(sprintf('Tool "%s" declared an outputSchema but returned no structuredContent.', $this->name));
            }
            $output = $result->structuredContent;
        } else {
            $output = $result;
        }

        try {
            $this->guard->assertValidData($output, $this->outputSchema, sprintf('Output from tool "%s"', $this->name));
        } catch (SchemaGuardException $error) {
            throw new ToolCallException(sprintf('Tool "%s" produced output that violates its declared outputSchema.', $this->name), 0, $error);
        }

        return $result;
    }
}
