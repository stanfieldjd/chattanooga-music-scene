<?php

declare(strict_types=1);

final class CMSA_Concentric_WP_CLI_Planner
{
    /** @param array<string,mixed> $arguments @return array<int,string> */
    public static function plan(string $operation, array $arguments, string $wordpressPath): array
    {
        $path = self::validatePath($wordpressPath);
        $base = ['wp'];

        switch ($operation) {
            case 'plugin.update':
                $slug = self::pluginSlug($arguments['slug'] ?? null);
                return array_merge($base, ['plugin', 'update', $slug, '--path=' . $path]);

            case 'plugin.deactivate':
                $slug = self::pluginSlug($arguments['slug'] ?? null);
                return array_merge($base, ['plugin', 'deactivate', $slug, '--path=' . $path]);

            case 'plugin.rollback':
                $slug = self::pluginSlug($arguments['slug'] ?? null);
                $version = self::version($arguments['version'] ?? null);
                return array_merge($base, ['plugin', 'install', $slug, '--version=' . $version, '--force', '--path=' . $path]);

            case 'cache.flush':
                return array_merge($base, ['cache', 'flush', '--path=' . $path]);

            default:
                throw new InvalidArgumentException('Operation is not available through the bounded WP-CLI planner.');
        }
    }

    private static function pluginSlug(mixed $value): string
    {
        $slug = trim((string)$value);
        if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/', $slug)) {
            throw new InvalidArgumentException('Invalid plugin slug.');
        }
        return $slug;
    }

    private static function version(mixed $value): string
    {
        $version = trim((string)$value);
        if ($version === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,63}$/', $version)) {
            throw new InvalidArgumentException('Invalid version.');
        }
        return $version;
    }

    private static function validatePath(string $path): string
    {
        $path = rtrim(trim($path), '/');
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0") || str_contains($path, "\n") || str_contains($path, "\r")) {
            throw new InvalidArgumentException('WordPress path must be a clean absolute path.');
        }
        return $path;
    }
}
