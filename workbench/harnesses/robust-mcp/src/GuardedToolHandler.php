<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Exception\ToolCallException;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

final class GuardedToolHandler implements ToolHandlerInterface
{
    public const DEFAULT_MAX_RESULT_BYTES = 1024 * 1024;

    private \Closure $callback;

    /**
     * @param array<string,mixed> $inputSchema
     * @param array<string,mixed> $outputSchema
     */
    public function __construct(
        private readonly string $name,
        private readonly array $inputSchema,
        private readonly array $outputSchema,
        callable $callback,
        private readonly SchemaGuard $guard,
        private readonly int $maxResultBytes = self::DEFAULT_MAX_RESULT_BYTES,
    ) {
        if ($this->maxResultBytes < 1024 || $this->maxResultBytes > 16 * 1024 * 1024) {
            throw new \InvalidArgumentException('Tool result byte limit must be between 1 KiB and 16 MiB.');
        }
        $this->callback = \Closure::fromCallable($callback);
    }

    /** @param array<string,mixed> $arguments */
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

        $this->assertResultSize($result);

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

    private function assertResultSize(mixed $result): void
    {
        try {
            $encoded = json_encode(
                $result,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (\JsonException $error) {
            throw new ToolCallException(sprintf('Tool "%s" produced a result that cannot be encoded as JSON.', $this->name), 0, $error);
        }

        if (strlen($encoded) > $this->maxResultBytes) {
            throw new ToolCallException(sprintf(
                'Tool "%s" produced a result larger than the configured %d-byte limit.',
                $this->name,
                $this->maxResultBytes,
            ));
        }
    }
}
