<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

final class SessionLockLease
{
    /** @param resource $handle */
    public function __construct(private $handle)
    {
        if (!is_resource($this->handle)) {
            throw new \InvalidArgumentException('Session lock lease requires an open resource.');
        }
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }
        @flock($this->handle, LOCK_UN);
        @fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
