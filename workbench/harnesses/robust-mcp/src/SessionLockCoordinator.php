<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

final class SessionLockCoordinator
{
    private string $lockDirectory;

    public function __construct(string $sessionDirectory)
    {
        $this->lockDirectory = rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.locks';
        if (!is_dir($this->lockDirectory) && !@mkdir($this->lockDirectory, 0700, true) && !is_dir($this->lockDirectory)) {
            throw new \RuntimeException('Session lock directory could not be created.');
        }
        @chmod($this->lockDirectory, 0700);
        if (!is_writable($this->lockDirectory)) {
            throw new \RuntimeException('Session lock directory is not writable.');
        }
    }

    public function acquire(string $sessionId, int $timeoutMs): ?SessionLockLease
    {
        $sessionId = strtolower(trim($sessionId));
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $sessionId)) {
            throw new \InvalidArgumentException('Session lock ID must be a canonical UUID.');
        }
        if ($timeoutMs < 0 || $timeoutMs > 30000) {
            throw new \InvalidArgumentException('Session lock timeout must be between 0 and 30000 milliseconds.');
        }

        $path = $this->lockDirectory . DIRECTORY_SEPARATOR . $sessionId . '.lock';
        $handle = @fopen($path, 'c+');
        if (false === $handle) {
            throw new \RuntimeException('Session lock file could not be opened.');
        }
        @chmod($path, 0600);

        $deadline = hrtime(true) + ($timeoutMs * 1_000_000);
        do {
            if (@flock($handle, LOCK_EX | LOCK_NB)) {
                return new SessionLockLease($handle);
            }
            if (0 === $timeoutMs || hrtime(true) >= $deadline) {
                @fclose($handle);
                return null;
            }
            usleep(10_000);
        } while (true);
    }
}
