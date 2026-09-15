<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

/**
 * Validated, immutable runtime configuration for the MCP HTTP process.
 *
 * Configuration errors are intentionally represented as exceptions here so the
 * entrypoint can map them to a generic 503 readiness result or a generic 500
 * serving error without disclosing filesystem paths or credential details.
 */
final class ServerConfiguration
{
    private const AUTH_MODES = ['none', 'manual-bearer', 'oauth-jwt'];
    private const DIGEST_PATTERN = '/^[a-f0-9]{64}$/D';
    private const DEFAULT_ALLOWED_HOSTS = ['localhost', '127.0.0.1', '[::1]'];
    private const DEFAULT_CHATGPT_ORIGINS = ['https://chatgpt.com', 'https://chat.openai.com'];
    private const OAUTH_ENVIRONMENT = [
        'ROBUST_MCP_OAUTH_ISSUER',
        'ROBUST_MCP_OAUTH_AUDIENCE',
        'ROBUST_MCP_OAUTH_SCOPES',
        'ROBUST_MCP_OAUTH_RESOURCE',
        'ROBUST_MCP_OAUTH_JWKS_URI',
        'ROBUST_MCP_OAUTH_CACHE_DIR',
        'ROBUST_MCP_OAUTH_CACHE_TTL',
    ];

    /**
     * @param non-empty-list<string> $allowedHosts
     * @param list<string> $allowedOrigins
     */
    private function __construct(
        public readonly string $authMode,
        public readonly ?string $bearerSha256,
        public readonly ?OAuthConfiguration $oauth,
        public readonly string $sessionDirectory,
        public readonly int $sessionTtl,
        public readonly ?string $telemetryLog,
        public readonly array $allowedHosts,
        public readonly array $allowedOrigins,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $digestRaw = getenv('ROBUST_MCP_BEARER_SHA256');
        $digest = false === $digestRaw || '' === trim($digestRaw) ? null : trim($digestRaw);
        if (null !== $digest && 1 !== preg_match(self::DIGEST_PATTERN, $digest)) {
            throw new \InvalidArgumentException('Invalid manual bearer digest configuration.');
        }

        $modeRaw = getenv('ROBUST_MCP_AUTH_MODE');
        $mode = false === $modeRaw || '' === trim($modeRaw)
            ? (null !== $digest ? 'manual-bearer' : 'none')
            : trim($modeRaw);

        if (!in_array($mode, self::AUTH_MODES, true)) {
            throw new \InvalidArgumentException('Unknown MCP authentication mode.');
        }
        if ('manual-bearer' === $mode && null === $digest) {
            throw new \InvalidArgumentException('Manual bearer mode requires a digest.');
        }
        if ('manual-bearer' !== $mode && null !== $digest) {
            throw new \InvalidArgumentException('A manual bearer digest must not be configured outside manual bearer mode.');
        }

        $sessionDirRaw = getenv('ROBUST_MCP_SESSION_DIR');
        $sessionDirectory = false === $sessionDirRaw || '' === trim($sessionDirRaw)
            ? sys_get_temp_dir() . '/chattanooga-robust-mcp-sessions'
            : trim($sessionDirRaw);
        self::assertPath($sessionDirectory, 'session directory');

        $ttlRaw = getenv('ROBUST_MCP_SESSION_TTL');
        if (false === $ttlRaw || '' === trim($ttlRaw)) {
            $sessionTtl = 3600;
        } else {
            $parsed = filter_var($ttlRaw, FILTER_VALIDATE_INT);
            if (false === $parsed || $parsed < 60 || $parsed > 86400) {
                throw new \InvalidArgumentException('Session TTL must be between 60 and 86400 seconds.');
            }
            $sessionTtl = (int) $parsed;
        }

        $telemetryRaw = getenv('ROBUST_MCP_LOG_FILE');
        $telemetryLog = false === $telemetryRaw || '' === trim($telemetryRaw) ? null : trim($telemetryRaw);
        if (null !== $telemetryLog) {
            self::assertPath($telemetryLog, 'telemetry log');
        }

        $oauthEnvironmentConfigured = self::oauthEnvironmentConfigured();
        if ('oauth-jwt' !== $mode && $oauthEnvironmentConfigured) {
            throw new \InvalidArgumentException('OAuth configuration must not be present outside oauth-jwt mode.');
        }

        $oauth = 'oauth-jwt' === $mode
            ? OAuthConfiguration::fromEnvironment($sessionDirectory)
            : null;

        $allowedHosts = self::allowedHosts($oauth);
        $allowedOrigins = self::allowedOrigins();

        return new self(
            $mode,
            $digest,
            $oauth,
            $sessionDirectory,
            $sessionTtl,
            $telemetryLog,
            $allowedHosts,
            $allowedOrigins,
        );
    }

    /** Validate paths needed by ordinary requests without a sentinel write. */
    public function prepareForServing(): void
    {
        $this->ensureSessionDirectory();
        $this->assertTelemetryParentWritable();
        $this->oauth?->prepareForServing();
    }

    /**
     * Prove that the process can actually use its configured persistence paths.
     * This is intentionally stronger than checking is_writable(): it creates,
     * locks, atomically renames, reads, and removes a sentinel in the session
     * directory, matching the operations used by FileSessionStore.
     */
    public function assertReady(): void
    {
        $this->prepareForServing();

        $sentinel = $this->sessionDirectory . DIRECTORY_SEPARATOR . '.ready-' . bin2hex(random_bytes(12));
        $renamed = $sentinel . '.ok';
        $payload = bin2hex(random_bytes(16));

        try {
            if (strlen($this->sessionDirectory) > 4096) {
                throw new \RuntimeException('Session directory path is unreasonably long.');
            }
            if (false === @file_put_contents($sentinel, $payload, LOCK_EX)) {
                throw new \RuntimeException('Session directory cannot create a locked file.');
            }
            @chmod($sentinel, 0600);
            if (!@rename($sentinel, $renamed)) {
                throw new \RuntimeException('Session directory cannot perform an atomic rename.');
            }
            $read = @file_get_contents($renamed);
            if ($payload !== $read) {
                throw new \RuntimeException('Session directory cannot round-trip data.');
            }
        } finally {
            @unlink($sentinel);
            @unlink($renamed);
        }

        $this->oauth?->assertReady();
    }

    public function ensureSessionDirectory(): void
    {
        if (!is_dir($this->sessionDirectory)) {
            if (!@mkdir($this->sessionDirectory, 0700, true) && !is_dir($this->sessionDirectory)) {
                throw new \RuntimeException('Session directory could not be created.');
            }
        }
        @chmod($this->sessionDirectory, 0700);
        if (!is_dir($this->sessionDirectory) || !is_writable($this->sessionDirectory)) {
            throw new \RuntimeException('Session directory is not writable.');
        }
    }

    private function assertTelemetryParentWritable(): void
    {
        if (null === $this->telemetryLog) {
            return;
        }
        $parent = dirname($this->telemetryLog);
        if (!is_dir($parent) || !is_writable($parent)) {
            throw new \RuntimeException('Telemetry log parent directory is not writable.');
        }
    }

    /** @return non-empty-list<string> */
    private static function allowedHosts(?OAuthConfiguration $oauth): array
    {
        $hosts = [];
        foreach (self::DEFAULT_ALLOWED_HOSTS as $host) {
            $hosts[self::normalizeHost($host)] = true;
        }

        if (null !== $oauth) {
            $resourceHost = parse_url($oauth->resource, PHP_URL_HOST);
            if (is_string($resourceHost) && '' !== $resourceHost) {
                if (str_contains($resourceHost, ':') && !str_starts_with($resourceHost, '[')) {
                    $resourceHost = '[' . $resourceHost . ']';
                }
                $hosts[self::normalizeHost($resourceHost)] = true;
            }
        }

        $raw = getenv('ROBUST_MCP_ALLOWED_HOSTS');
        if (false !== $raw && '' !== trim($raw)) {
            foreach (explode(',', $raw) as $host) {
                $host = trim($host);
                if ('' !== $host) {
                    $hosts[self::normalizeHost($host)] = true;
                }
            }
        }

        /** @var non-empty-list<string> $result */
        $result = array_keys($hosts);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @return list<string> */
    private static function allowedOrigins(): array
    {
        $raw = getenv('ROBUST_MCP_ALLOWED_ORIGINS');
        $origins = false === $raw || '' === trim($raw)
            ? self::DEFAULT_CHATGPT_ORIGINS
            : explode(',', $raw);

        $normalized = [];
        foreach ($origins as $origin) {
            $origin = trim($origin);
            if ('' === $origin) {
                continue;
            }
            $normalized[self::normalizeOrigin($origin)] = true;
        }

        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ('' === $host || '*' === $host || preg_match('/[\x00-\x20\x7f\/]/', $host)) {
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
        $parts = parse_url($origin);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Allowed MCP Origin must be absolute.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Allowed MCP Origin contains forbidden URL components.');
        }
        $path = (string) ($parts['path'] ?? '');
        if ('' !== $path && '/' !== $path) {
            throw new \InvalidArgumentException('Allowed MCP Origin must not contain a path.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $loopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
        if ('https' !== $scheme && !('http' === $scheme && $loopback)) {
            throw new \InvalidArgumentException('Allowed MCP Origins must use HTTPS except for loopback testing.');
        }

        $portNumber = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = ('https' === $scheme && 443 === $portNumber) || ('http' === $scheme && 80 === $portNumber);
        $port = null !== $portNumber && !$defaultPort ? ':' . $portNumber : '';
        return $scheme . '://' . $host . $port;
    }

    private static function oauthEnvironmentConfigured(): bool
    {
        foreach (self::OAUTH_ENVIRONMENT as $name) {
            $value = getenv($name);
            if (false !== $value && '' !== trim($value)) {
                return true;
            }
        }

        return false;
    }

    private static function assertPath(string $path, string $label): void
    {
        if ('' === $path || str_contains($path, "\0") || str_contains($path, "\r") || str_contains($path, "\n")) {
            throw new \InvalidArgumentException(sprintf('Invalid %s path.', $label));
        }
    }
}
