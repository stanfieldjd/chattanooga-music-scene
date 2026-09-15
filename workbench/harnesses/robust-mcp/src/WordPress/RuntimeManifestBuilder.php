<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp\WordPress;

final class RuntimeManifestBuilder
{
    public const MANIFEST_VERSION = 1;

    /** @var list<string> */
    private const REQUIRED_SECTIONS = [
        'site',
        'principal',
        'plugins',
        'themes',
        'post_types',
        'taxonomies',
        'settings',
        'rest_routes',
        'abilities',
        'roles',
        'features',
    ];

    /**
     * Build a deterministic inventory manifest. The residence is deliberately
     * excluded from the fingerprint: inside-WordPress and externally booted
     * executions over the same runtime must fingerprint identically.
     *
     * @return array<string,mixed>
     */
    public function build(RuntimeInventorySourceInterface $source, string $residence): array
    {
        if (!in_array($residence, ['inside-wordpress', 'outside-wordpress'], true)) {
            throw new \InvalidArgumentException('Unknown WordPress workspace residence.');
        }

        $inventory = $source->collect();
        foreach (self::REQUIRED_SECTIONS as $section) {
            if (!array_key_exists($section, $inventory) || !is_array($inventory[$section])) {
                throw new \UnexpectedValueException(sprintf('WordPress runtime inventory is missing array section "%s".', $section));
            }
        }

        $inventory = $this->canonicalize($inventory);
        $json = json_encode(
            $inventory,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return [
            'manifest_version' => self::MANIFEST_VERSION,
            'residence' => $residence,
            'fingerprint' => hash('sha256', $json),
            'inventory' => $inventory,
        ];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            if (is_object($value)) {
                throw new \UnexpectedValueException('Runtime inventory must contain JSON data only; objects are not allowed.');
            }
            if (is_resource($value)) {
                throw new \UnexpectedValueException('Runtime inventory must not contain resources.');
            }
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
