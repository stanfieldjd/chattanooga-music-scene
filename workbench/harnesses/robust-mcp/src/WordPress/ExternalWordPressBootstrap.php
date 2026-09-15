<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp\WordPress;

final class ExternalWordPressBootstrap
{
    private readonly string $root;
    private readonly string $wpLoad;

    public function __construct(string $wordpressRoot)
    {
        if ('' === trim($wordpressRoot) || str_contains($wordpressRoot, "\0")) {
            throw new \InvalidArgumentException('WordPress root must be a non-empty filesystem path.');
        }

        $root = realpath($wordpressRoot);
        if (false === $root || !is_dir($root)) {
            throw new \InvalidArgumentException('Configured WordPress root does not exist.');
        }

        $wpLoad = realpath($root . DIRECTORY_SEPARATOR . 'wp-load.php');
        if (false === $wpLoad || !is_file($wpLoad) || !is_readable($wpLoad)) {
            throw new \InvalidArgumentException('Configured WordPress root does not contain a readable wp-load.php.');
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($wpLoad, $prefix)) {
            throw new \InvalidArgumentException('wp-load.php resolves outside the configured WordPress root.');
        }

        $this->root = $root;
        $this->wpLoad = $wpLoad;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function wpLoadPath(): string
    {
        return $this->wpLoad;
    }

    public function boot(): NativeWordPressRuntimeSource
    {
        require_once $this->wpLoad;

        if (!defined('ABSPATH') || !function_exists('get_bloginfo') || !function_exists('get_post_types')) {
            throw new \RuntimeException('wp-load.php returned without a usable WordPress runtime.');
        }

        return new NativeWordPressRuntimeSource();
    }
}
