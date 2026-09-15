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
 * Explicit out-of-band manual bearer mode.
 *
 * The plaintext credential is never stored by the server. Configuration holds
 * only SHA-256(token), where token is exactly 32 random bytes encoded as 64
 * lowercase hexadecimal characters. This mode intentionally does not mint,
 * rotate, or discover credentials; provisioning is external to MCP.
 */
final class ManualBearerAuthMiddleware implements MiddlewareInterface
{
    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/D';

    public function __construct(
        private readonly string $expectedSha256,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $realm = 'chattanooga-robust-mcp',
    ) {
        if (1 !== preg_match(self::TOKEN_PATTERN, $this->expectedSha256)) {
            throw new \InvalidArgumentException('Manual bearer digest must be exactly 64 lowercase hexadecimal characters.');
        }
        if ('' === trim($this->realm)) {
            throw new \InvalidArgumentException('Manual bearer realm must not be empty.');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Match the previously proven WordPress behavior: the MCP POST is the
        // protected operation. OPTIONS and method-not-allowed responses remain
        // transport behavior rather than authentication oracles.
        if ('POST' !== strtoupper($request->getMethod())) {
            return $handler->handle($request);
        }

        $authorization = $request->getHeaderLine('Authorization');
        $matches = [];
        $validShape = 1 === preg_match('/^(?i:Bearer)[ \t]+([a-f0-9]{64})$/D', $authorization, $matches);
        $provided = $validShape ? (string) $matches[1] : '';

        // Always perform the digest comparison, even for malformed/missing
        // credentials, so the final allow/deny branch is constant-time with
        // respect to the stored digest.
        $providedHash = '' === $provided ? str_repeat('0', 64) : hash('sha256', $provided);
        if ('' === $provided || !hash_equals($this->expectedSha256, $providedHash)) {
            return $this->unauthorized('' !== $authorization);
        }

        return $handler->handle(
            $request
                ->withAttribute('robust_mcp_authenticated', true)
                ->withAttribute('robust_mcp_auth_mode', 'manual-bearer'),
        );
    }

    private function unauthorized(bool $credentialWasPresented): ResponseInterface
    {
        $challenge = 'Bearer realm="' . $this->escapeChallengeValue($this->realm) . '"';
        if ($credentialWasPresented) {
            $challenge .= ', error="invalid_token"';
        }

        $payload = json_encode(
            [
                'error' => 'invalid_token',
                'error_description' => $credentialWasPresented
                    ? 'The manually configured bearer token is invalid.'
                    : 'A manually configured bearer token is required.',
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->responseFactory->createResponse(401)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('WWW-Authenticate', $challenge)
            ->withBody($this->streamFactory->createStream($payload));
    }

    private function escapeChallengeValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
