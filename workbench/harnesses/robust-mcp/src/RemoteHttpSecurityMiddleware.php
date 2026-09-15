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
 * Remote-MCP HTTP boundary for Host validation and browser CORS.
 *
 * The official SDK's DNS-rebinding middleware treats Origin as an alternative
 * to Host. That is suitable for localhost development, but a remote ChatGPT
 * request legitimately has a ChatGPT Origin and an MCP-server Host. This
 * middleware validates both independently and never uses Origin as proof that
 * the requested server Host is trusted.
 */
final class RemoteHttpSecurityMiddleware implements MiddlewareInterface
{
    /** @var array<string,true> */
    private array $allowedHosts = [];

    /** @var array<string,true> */
    private array $allowedOrigins = [];

    /** @var array<string,true> */
    private const STATIC_REQUEST_HEADERS = [
        'accept' => true,
        'authorization' => true,
        'content-type' => true,
        'last-event-id' => true,
        'mcp-protocol-version' => true,
        'mcp-session-id' => true,
        'mcp-method' => true,
        'mcp-name' => true,
        'x-request-id' => true,
    ];

    /** @param list<string> $allowedHosts @param list<string> $allowedOrigins */
    public function __construct(
        array $allowedHosts,
        array $allowedOrigins,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        foreach ($allowedHosts as $host) {
            $normalized = self::normalizeConfiguredHost($host);
            $this->allowedHosts[$normalized] = true;
        }
        if ([] === $this->allowedHosts) {
            throw new \InvalidArgumentException('At least one MCP Host must be allowed.');
        }

        foreach ($allowedOrigins as $origin) {
            $normalized = self::normalizeOrigin($origin);
            $this->allowedOrigins[$normalized] = true;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->hostAllowed($request->getHeaderLine('Host'))) {
            return $this->plain(403, 'Forbidden: Invalid Host header.');
        }

        $origin = trim($request->getHeaderLine('Origin'));
        $normalizedOrigin = null;
        if ('' !== $origin) {
            try {
                $normalizedOrigin = self::normalizeOrigin($origin);
            } catch (\InvalidArgumentException) {
                return $this->plain(403, 'Forbidden: Invalid Origin header.');
            }
            if (!isset($this->allowedOrigins[$normalizedOrigin])) {
                return $this->plain(403, 'Forbidden: Origin is not allowed.');
            }
        }

        if ($this->isPreflight($request)) {
            if (null === $normalizedOrigin) {
                return $this->plain(400, 'Bad Request: CORS preflight requires Origin.');
            }

            $requestedMethod = strtoupper(trim($request->getHeaderLine('Access-Control-Request-Method')));
            if (!in_array($requestedMethod, ['POST', 'DELETE'], true)) {
                return $this->plain(403, 'Forbidden: Requested CORS method is not allowed.');
            }

            try {
                $requestedHeaders = $this->requestedHeaders($request->getHeaderLine('Access-Control-Request-Headers'));
            } catch (\InvalidArgumentException) {
                return $this->plain(403, 'Forbidden: Requested CORS header is malformed.');
            }
            foreach ($requestedHeaders as $header) {
                if (!$this->requestHeaderAllowed($header)) {
                    return $this->plain(403, 'Forbidden: Requested CORS header is not allowed.');
                }
            }

            $response = $this->responseFactory->createResponse(204)
                ->withHeader('Access-Control-Allow-Origin', $normalizedOrigin)
                ->withHeader('Access-Control-Allow-Methods', 'POST, DELETE')
                ->withHeader('Access-Control-Max-Age', '600')
                ->withHeader('Vary', 'Origin');

            if ([] !== $requestedHeaders) {
                $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $requestedHeaders));
            }

            return $response;
        }

        $response = $handler->handle($request);
        if (null !== $normalizedOrigin) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $normalizedOrigin)
                ->withHeader('Vary', $this->mergeVary($response->getHeaderLine('Vary'), 'Origin'));
        }

        return $response->withHeader('Access-Control-Expose-Headers', 'Mcp-Session-Id, X-Request-Id');
    }

    private function hostAllowed(string $hostHeader): bool
    {
        $hostHeader = trim($hostHeader);
        if ('' === $hostHeader || preg_match('/[\r\n]/', $hostHeader)) {
            return false;
        }

        if (str_starts_with($hostHeader, '[')) {
            $end = strpos($hostHeader, ']');
            if (false === $end) {
                return false;
            }
            $host = substr($hostHeader, 0, $end + 1);
        } else {
            $host = explode(':', $hostHeader, 2)[0];
        }

        return isset($this->allowedHosts[strtolower($host)]);
    }

    private function isPreflight(ServerRequestInterface $request): bool
    {
        return 'OPTIONS' === strtoupper($request->getMethod())
            && '' !== trim($request->getHeaderLine('Access-Control-Request-Method'));
    }

    /** @return list<string> */
    private function requestedHeaders(string $headerLine): array
    {
        if ('' === trim($headerLine)) {
            return [];
        }

        $headers = [];
        foreach (explode(',', $headerLine) as $header) {
            $header = strtolower(trim($header));
            if ('' === $header || 1 !== preg_match("/^[a-z0-9!#$%&'*+.^_`|~-]+$/D", $header)) {
                throw new \InvalidArgumentException('Malformed CORS request header name.');
            }
            $headers[$header] = true;
        }

        $result = array_keys($headers);
        sort($result, SORT_STRING);
        return $result;
    }

    private function requestHeaderAllowed(string $header): bool
    {
        return isset(self::STATIC_REQUEST_HEADERS[$header]) || str_starts_with($header, 'mcp-param-');
    }

    private function plain(int $status, string $message): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($message));
    }

    private function mergeVary(string $current, string $token): string
    {
        if ('' === trim($current)) {
            return $token;
        }
        if ('*' === trim($current)) {
            return '*';
        }
        $tokens = array_map('trim', explode(',', $current));
        foreach ($tokens as $existing) {
            if (0 === strcasecmp($existing, $token)) {
                return $current;
            }
        }
        return $current . ', ' . $token;
    }

    private static function normalizeConfiguredHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ('' === $host || preg_match('/[\x00-\x20\x7f\/]/', $host)) {
            throw new \InvalidArgumentException('Invalid allowed MCP Host.');
        }

        if (str_starts_with($host, '[')) {
            if (!str_ends_with($host, ']')) {
                throw new \InvalidArgumentException('Invalid bracketed IPv6 MCP Host.');
            }
            return $host;
        }

        if (str_contains($host, ':')) {
            throw new \InvalidArgumentException('Allowed MCP Hosts must not include ports.');
        }

        return $host;
    }

    private static function normalizeOrigin(string $origin): string
    {
        $origin = trim($origin);
        $parts = parse_url($origin);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Origin must be an absolute origin URL.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Origin must not contain user info, query, or fragment.');
        }
        $path = (string) ($parts['path'] ?? '');
        if ('' !== $path && '/' !== $path) {
            throw new \InvalidArgumentException('Origin must not contain a path.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new \InvalidArgumentException('Origin scheme must be HTTP or HTTPS.');
        }
        $host = strtolower((string) $parts['host']);
        $portNumber = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = ('https' === $scheme && 443 === $portNumber) || ('http' === $scheme && 80 === $portNumber);
        $port = null !== $portNumber && !$defaultPort ? ':' . $portNumber : '';

        return $scheme . '://' . $host . $port;
    }
}
