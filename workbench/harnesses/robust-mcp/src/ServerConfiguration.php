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
    private const AUTH_MODES = ['none', 'manual-bearer'];
    private const DIGEST_PATTERN = '/^[a-f0-9]{64}$/D';

    private function __construct(
        public readonly string $authMode,
        public readonly ?string $bearerSha256,
        public readonly string $sessionDirectory,
        public readonly int $sessionTtl,
        public readonly ?string $telemetryLog,
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
        if ('none' === $mode && null !== $digest) {
            throw new \InvalidArgumentException('A bearer digest must not be configured while authentication is disabled.');
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

        return new self($mode, $digest, $sessionDirectory, $sessionTtl, $telemetryLog);
    }

    /** Validate paths needed by ordinary requests without a sentinel write. */
    public function prepareForServing(): void
    {
        $this->ensureSessionDirectory();
        $this->assertTelemetryParentWritable();
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

    private static function assertPath(string $path, string $label): void
    {
        if ('' === $path || str_contains($path, "\0") || str_contains($path, "\r") || str_contains($path, "\n")) {
            throw new \InvalidArgumentException(sprintf('Invalid %s path.', $label));
        }
    }
}
