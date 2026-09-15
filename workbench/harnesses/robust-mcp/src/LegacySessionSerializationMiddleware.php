<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serializes requests that operate on one existing handshake-era MCP session.
 *
 * SessionLockLease instances remain referenced by this middleware until the PHP
 * request ends. That keeps the lock through final response-body emission, which
 * also covers callback/SSE bodies whose work may occur after handler return.
 */
final class LegacySessionSerializationMiddleware implements MiddlewareInterface
{
    /** @var list<SessionLockLease> */
    private array $heldLocks = [];

    public function __construct(
        private readonly string $sessionDirectory,
        private readonly SessionLockCoordinator $locks,
        private readonly int $timeoutMs,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        if ($this->timeoutMs < 0 || $this->timeoutMs > 30000) {
            throw new \InvalidArgumentException('Legacy session lock timeout is out of range.');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'DELETE'], true)) {
            return $handler->handle($request);
        }

        $headers = $request->getHeader('Mcp-Session-Id');
        if (1 !== count($headers)) {
            return $handler->handle($request);
        }

        $sessionId = strtolower(trim($headers[0]));
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $sessionId)) {
            return $handler->handle($request);
        }

        // Do not create lock files for arbitrary unknown session IDs. The MCP
        // transport remains responsible for returning the canonical missing-
        // session error when the persistence file is absent.
        $sessionPath = rtrim($this->sessionDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $sessionId;
        if (!is_file($sessionPath)) {
            return $handler->handle($request);
        }

        try {
            $lease = $this->locks->acquire($sessionId, $this->timeoutMs);
        } catch (\Throwable) {
            return $this->busy();
        }
        if (null === $lease) {
            return $this->busy();
        }

        $this->heldLocks[] = $lease;
        try {
            return $handler->handle($request);
        } catch (\Throwable $error) {
            $lease->release();
            array_pop($this->heldLocks);
            throw $error;
        }
    }

    private function busy(): ResponseInterface
    {
        $payload = json_encode([
            'error' => 'session_busy',
            'error_description' => 'Another request is currently using this MCP session.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $this->responseFactory->createResponse(409)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Retry-After', '1')
            ->withBody($this->streamFactory->createStream($payload));
    }
}
