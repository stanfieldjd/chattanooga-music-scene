<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp\WordPress;

interface RuntimeInventorySourceInterface
{
    /**
     * Return safe structural information about one fully booted WordPress runtime.
     * Values and callbacks that may contain secrets are intentionally excluded.
     *
     * @return array<string,mixed>
     */
    public function collect(): array;
}
