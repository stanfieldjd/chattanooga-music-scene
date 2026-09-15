<?php

declare(strict_types=1);

use Chattanooga\RobustMcp\WordPress\ExternalWordPressBootstrap;
use Chattanooga\RobustMcp\WordPress\NativeWordPressRuntimeSource;
use Chattanooga\RobustMcp\WordPress\RuntimeManifestBuilder;

require __DIR__ . '/vendor/autoload.php';

$wpCliArgs = isset($args) && is_array($args) ? $args : [];
$mode = (string) ($wpCliArgs[0] ?? $argv[1] ?? '');
$wordpressRoot = (string) ($wpCliArgs[1] ?? $argv[2] ?? '');

$builder = new RuntimeManifestBuilder();

if ('inside' === $mode) {
    $manifest = $builder->build(new NativeWordPressRuntimeSource(), 'inside-wordpress');
} elseif ('outside' === $mode) {
    $manifest = $builder->build((new ExternalWordPressBootstrap($wordpressRoot))->boot(), 'outside-wordpress');
} else {
    throw new InvalidArgumentException('Usage: wordpress-runtime-live-probe.php inside | outside <wordpress-root>');
}

echo json_encode(
    $manifest,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
) . PHP_EOL;
