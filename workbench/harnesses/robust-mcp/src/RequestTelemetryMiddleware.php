<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Minimal structured transport telemetry.
 *
 * Deliberately excludes Authorization, cookies, request/response bodies, tool
 * arguments, WordPress inventory, and arbitrary headers. The recorded fields
 * are routing/operational metadata only.
 */
final class RequestTelemetryMiddleware implements MiddlewareInterface
{
    private const REQUEST_ID = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D';

    public function __construct(private readonly ?string $logFile = null)
    {
        if (null !== $this->logFile && ('' === trim($this->logFile) || str_contains($this->logFile, "\0"))) {
            throw new \InvalidArgumentException('Telemetry log path must be a non-empty filesystem path without NUL bytes.');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $this->requestId($request->getHeaderLine('X-Request-Id'));
        $request = $request->withAttribute('robust_mcp_request_id', $requestId);
        $started = hrtime(true);
        $status = 500;

        try {
            $response = $handler->handle($request);
            $status = $response->getStatusCode();

            return $response->withHeader('X-Request-Id', $requestId);
        } finally {
            $durationMs = (hrtime(true) - $started) / 1_000_000;
            $this->write([
                'ts' => gmdate('c'),
                'request_id' => $requestId,
                'worker_pid' => getmypid(),
                'http_method' => strtoupper($request->getMethod()),
                'path' => $request->getUri()->getPath(),
                'mcp_protocol_version' => $this->boundedToken($request->getHeaderLine('MCP-Protocol-Version')),
                'mcp_method' => $this->boundedToken($request->getHeaderLine('Mcp-Method')),
                'mcp_name' => $this->boundedToken($request->getHeaderLine('Mcp-Name')),
                'status' => $status,
                'duration_ms' => round($durationMs, 3),
            ]);
        }
    }

    private function requestId(string $candidate): string
    {
        $candidate = trim($candidate);
        if (1 === preg_match(self::REQUEST_ID, $candidate)) {
            return $candidate;
        }

        return bin2hex(random_bytes(16));
    }

    private function boundedToken(string $value): ?string
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        if (strlen($value) > 128 || preg_match('/[^\x21-\x7E]/', $value)) {
            return '[invalid]';
        }

        return $value;
    }

    /** @param array<string,mixed> $event */
    private function write(array $event): void
    {
        $line = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        if (null === $this->logFile) {
            error_log(rtrim($line));
            return;
        }

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
