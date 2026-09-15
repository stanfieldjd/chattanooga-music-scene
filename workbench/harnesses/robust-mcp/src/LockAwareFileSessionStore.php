<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Server\Session\FileSessionStore;
use Symfony\Component\Uid\Uuid;

/** File session persistence whose garbage collection never removes a busy session. */
final class LockAwareFileSessionStore extends FileSessionStore
{
    public function __construct(
        private readonly string $sessionDirectory,
        private readonly int $sessionTtl,
        private readonly SessionLockCoordinator $locks,
    ) {
        parent::__construct($sessionDirectory, $sessionTtl);
    }

    /** @return Uuid[] */
    public function gc(): array
    {
        $deleted = [];
        $now = time();
        $directory = @opendir($this->sessionDirectory);
        if (false === $directory) {
            return $deleted;
        }

        try {
            while (false !== ($entry = readdir($directory))) {
                if ('.' === $entry || '..' === $entry || !Uuid::isValid($entry)) {
                    continue;
                }
                $path = $this->sessionDirectory . DIRECTORY_SEPARATOR . $entry;
                if (!is_file($path)) {
                    continue;
                }
                $mtime = @filemtime($path) ?: 0;
                if (($now - $mtime) <= $this->sessionTtl) {
                    continue;
                }

                try {
                    $lease = $this->locks->acquire($entry, 0);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                if (null === $lease) {
                    continue;
                }

                try {
                    // Re-check after acquiring the session lock. A request may
                    // have refreshed the session while GC was waiting to inspect it.
                    $mtime = @filemtime($path) ?: 0;
                    if (is_file($path) && ($now - $mtime) > $this->sessionTtl && @unlink($path)) {
                        $deleted[] = Uuid::fromString($entry);
                    }
                } finally {
                    $lease->release();
                }
            }
        } finally {
            closedir($directory);
        }

        return $deleted;
    }
}
