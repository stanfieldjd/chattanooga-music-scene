<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class McpHttpSemanticsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ('OPTIONS' === $method) {
            return $handler->handle($request);
        }

        if ('POST' !== $method) {
            return $this->jsonRpcError(405, -32600, sprintf('The modern MCP lifecycle accepts POST only, got %s.', $method))
                ->withHeader('Allow', 'POST');
        }

        if ('application/json' !== $this->baseMediaType($request->getHeaderLine('Content-Type'))) {
            return $this->jsonRpcError(415, -32600, 'Content-Type must be application/json.');
        }

        $accept = $request->getHeaderLine('Accept');
        if (!$this->accepts($accept, 'application/json') || !$this->accepts($accept, 'text/event-stream')) {
            return $this->jsonRpcError(406, -32600, 'Accept must permit application/json and text/event-stream.');
        }

        return $handler->handle($request);
    }

    private function baseMediaType(string $value): string
    {
        $parts = explode(';', $value, 2);
        return strtolower(trim($parts[0] ?? ''));
    }

    private function accepts(string $header, string $wanted): bool
    {
        if ('' === trim($header)) {
            return false;
        }

        [$wantedType, $wantedSubtype] = explode('/', strtolower($wanted), 2);
        foreach (explode(',', $header) as $item) {
            $segments = array_map('trim', explode(';', $item));
            $range = strtolower((string) array_shift($segments));
            $quality = 1.0;
            foreach ($segments as $parameter) {
                if (preg_match('/^q\s*=\s*([01](?:\.\d{0,3})?)$/i', $parameter, $match)) {
                    $quality = (float) $match[1];
                }
            }
            if ($quality <= 0.0 || !str_contains($range, '/')) {
                continue;
            }

            [$type, $subtype] = explode('/', $range, 2);
            if (('*' === $type || $wantedType === $type) && ('*' === $subtype || $wantedSubtype === $subtype)) {
                return true;
            }
        }

        return false;
    }

    private function jsonRpcError(int $status, int $code, string $message): ResponseInterface
    {
        $payload = json_encode(
            [
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream($payload));
    }
}
