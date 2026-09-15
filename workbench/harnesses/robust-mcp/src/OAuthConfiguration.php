<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

/**
 * Immutable OAuth 2.1 resource-server configuration.
 *
 * The MCP remains a resource server only. It never mints access tokens or
 * client credentials; those remain the responsibility of the configured
 * external authorization server.
 */
final class OAuthConfiguration
{
    /** @param non-empty-list<string> $audiences @param non-empty-list<string> $scopes */
    private function __construct(
        public readonly string $issuer,
        public readonly array $audiences,
        public readonly array $scopes,
        public readonly string $resource,
        public readonly ?string $jwksUri,
        public readonly string $cacheDirectory,
        public readonly int $cacheTtl,
    ) {
    }

    public static function fromEnvironment(string $sessionDirectory): self
    {
        $issuer = self::requiredEnvironment('ROBUST_MCP_OAUTH_ISSUER');
        self::assertUrl($issuer, 'OAuth issuer', issuer: true);

        $resource = self::requiredEnvironment('ROBUST_MCP_OAUTH_RESOURCE');
        self::assertUrl($resource, 'OAuth resource identifier');

        $audienceRaw = self::requiredEnvironment('ROBUST_MCP_OAUTH_AUDIENCE');
        $audiences = [];
        foreach (explode(',', $audienceRaw) as $candidate) {
            $candidate = trim($candidate);
            if ('' === $candidate) {
                continue;
            }
            if (strlen($candidate) > 512 || preg_match('/[\x00-\x20\x7f]/', $candidate)) {
                throw new \InvalidArgumentException('OAuth audience contains an invalid value.');
            }
            $audiences[] = $candidate;
        }
        $audiences = array_values(array_unique($audiences));
        if ([] === $audiences) {
            throw new \InvalidArgumentException('OAuth mode requires at least one audience.');
        }

        $scopeRaw = self::requiredEnvironment('ROBUST_MCP_OAUTH_SCOPES');
        $scopes = preg_split('/\s+/', trim($scopeRaw)) ?: [];
        $scopes = array_values(array_unique(array_filter($scopes, static fn (string $scope): bool => '' !== $scope)));
        if ([] === $scopes) {
            throw new \InvalidArgumentException('OAuth mode requires at least one scope.');
        }
        foreach ($scopes as $scope) {
            // RFC 6749 scope-token = %x21 / %x23-5B / %x5D-7E.
            if (1 !== preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/D', $scope)) {
                throw new \InvalidArgumentException('OAuth scope contains invalid characters.');
            }
        }

        $jwksRaw = getenv('ROBUST_MCP_OAUTH_JWKS_URI');
        $jwksUri = false === $jwksRaw || '' === trim($jwksRaw) ? null : trim($jwksRaw);
        if (null !== $jwksUri) {
            self::assertUrl($jwksUri, 'OAuth JWKS URI');
        }

        $cacheRaw = getenv('ROBUST_MCP_OAUTH_CACHE_DIR');
        $cacheDirectory = false === $cacheRaw || '' === trim($cacheRaw)
            ? rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'oauth-cache'
            : trim($cacheRaw);
        self::assertPath($cacheDirectory, 'OAuth cache directory');

        $ttlRaw = getenv('ROBUST_MCP_OAUTH_CACHE_TTL');
        if (false === $ttlRaw || '' === trim($ttlRaw)) {
            $cacheTtl = 900;
        } else {
            $parsed = filter_var($ttlRaw, FILTER_VALIDATE_INT);
            if (false === $parsed || $parsed < 60 || $parsed > 86400) {
                throw new \InvalidArgumentException('OAuth cache TTL must be between 60 and 86400 seconds.');
            }
            $cacheTtl = (int) $parsed;
        }

        return new self(
            issuer: $issuer,
            audiences: $audiences,
            scopes: $scopes,
            resource: $resource,
            jwksUri: $jwksUri,
            cacheDirectory: $cacheDirectory,
            cacheTtl: $cacheTtl,
        );
    }

    /** @return non-empty-list<string> */
    public function metadataPaths(): array
    {
        $path = parse_url($this->resource, PHP_URL_PATH);
        $path = is_string($path) ? trim($path, '/') : '';

        $paths = ['/.well-known/oauth-protected-resource'];
        if ('' !== $path) {
            $paths[] = '/.well-known/oauth-protected-resource/' . $path;
        }

        /** @var non-empty-list<string> $paths */
        return array_values(array_unique($paths));
    }

    public function prepareForServing(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            if (!@mkdir($this->cacheDirectory, 0700, true) && !is_dir($this->cacheDirectory)) {
                throw new \RuntimeException('OAuth cache directory could not be created.');
            }
        }
        @chmod($this->cacheDirectory, 0700);
        if (!is_dir($this->cacheDirectory) || !is_writable($this->cacheDirectory)) {
            throw new \RuntimeException('OAuth cache directory is not writable.');
        }
    }

    public function assertReady(): void
    {
        $this->prepareForServing();

        $sentinel = $this->cacheDirectory . DIRECTORY_SEPARATOR . '.ready-' . bin2hex(random_bytes(12));
        try {
            if (false === @file_put_contents($sentinel, 'ok', LOCK_EX)) {
                throw new \RuntimeException('OAuth cache directory cannot create a locked file.');
            }
            @chmod($sentinel, 0600);
            if ('ok' !== @file_get_contents($sentinel)) {
                throw new \RuntimeException('OAuth cache directory cannot round-trip data.');
            }
        } finally {
            @unlink($sentinel);
        }
    }

    private static function requiredEnvironment(string $name): string
    {
        $value = getenv($name);
        if (false === $value || '' === trim($value)) {
            throw new \InvalidArgumentException(sprintf('%s is required in OAuth mode.', $name));
        }

        return trim($value);
    }

    private static function assertUrl(string $value, string $label, bool $issuer = false): void
    {
        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException(sprintf('%s must be an absolute URL.', $label));
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $loopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
        if ('https' !== $scheme && !('http' === $scheme && $loopback)) {
            throw new \InvalidArgumentException(sprintf('%s must use HTTPS except for loopback test endpoints.', $label));
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException(sprintf('%s must not contain user info or a fragment.', $label));
        }
        if ($issuer && isset($parts['query'])) {
            throw new \InvalidArgumentException('OAuth issuer must not contain a query string.');
        }
    }

    private static function assertPath(string $path, string $label): void
    {
        if ('' === $path || str_contains($path, "\0") || str_contains($path, "\r") || str_contains($path, "\n")) {
            throw new \InvalidArgumentException(sprintf('Invalid %s path.', $label));
        }
    }
}
