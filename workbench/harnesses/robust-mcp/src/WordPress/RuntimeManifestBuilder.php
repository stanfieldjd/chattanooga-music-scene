<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp\WordPress;

final class RuntimeManifestBuilder
{
    public const MANIFEST_VERSION = 1;

    /** @var list<string> */
    private const CONTEXT_SECTIONS = [
        'runtime',
        'principal',
    ];

    /** @var list<string> */
    private const INVENTORY_SECTIONS = [
        'site',
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
     * Build a deterministic site manifest. Residence and execution context are
     * deliberately excluded from the fingerprint: an inside process and an
     * external wp-load.php process over the same site must fingerprint the same
     * structural WordPress inventory.
     *
     * @return array<string,mixed>
     */
    public function build(RuntimeInventorySourceInterface $source, string $residence): array
    {
        if (!in_array($residence, ['inside-wordpress', 'outside-wordpress'], true)) {
            throw new \InvalidArgumentException('Unknown WordPress workspace residence.');
        }

        $collected = $source->collect();
        foreach ([...self::CONTEXT_SECTIONS, ...self::INVENTORY_SECTIONS] as $section) {
            if (!array_key_exists($section, $collected) || !is_array($collected[$section])) {
                throw new \UnexpectedValueException(sprintf('WordPress runtime inventory is missing array section "%s".', $section));
            }
        }

        $context = [];
        foreach (self::CONTEXT_SECTIONS as $section) {
            $context[$section] = $collected[$section];
        }

        $inventory = [];
        foreach (self::INVENTORY_SECTIONS as $section) {
            $inventory[$section] = $collected[$section];
        }

        $context = $this->canonicalize($context);
        $inventory = $this->canonicalize($inventory);
        $json = json_encode(
            $inventory,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return [
            'manifest_version' => self::MANIFEST_VERSION,
            'residence' => $residence,
            'fingerprint' => hash('sha256', $json),
            'context' => $context,
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
