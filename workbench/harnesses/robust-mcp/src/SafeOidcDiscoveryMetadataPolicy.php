<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Server\Transport\Http\OAuth\OidcDiscoveryMetadataPolicyInterface;
use Mcp\Server\Transport\Http\OAuth\StrictOidcDiscoveryMetadataPolicy;

/**
 * Tightens the SDK discovery policy so token/JWKS endpoints cannot downgrade
 * to cleartext or embed credentials. Loopback HTTP remains available for CI.
 */
final class SafeOidcDiscoveryMetadataPolicy implements OidcDiscoveryMetadataPolicyInterface
{
    private StrictOidcDiscoveryMetadataPolicy $inner;

    public function __construct()
    {
        $this->inner = new StrictOidcDiscoveryMetadataPolicy();
    }

    public function isValid(mixed $metadata): bool
    {
        if (!$this->inner->isValid($metadata) || !is_array($metadata)) {
            return false;
        }

        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
            $value = $metadata[$field] ?? null;
            if (!is_string($value) || !$this->isSafeEndpoint($value)) {
                return false;
            }
        }

        $methods = $metadata['code_challenge_methods_supported'] ?? [];
        if (!is_array($methods) || !in_array('S256', $methods, true)) {
            return false;
        }

        return true;
    }

    private function isSafeEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $loopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

        if ('https' !== $scheme && !('http' === $scheme && $loopback)) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        return true;
    }
}
