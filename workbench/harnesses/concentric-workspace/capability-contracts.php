<?php

declare(strict_types=1);

final class CMSA_Concentric_Capability_Contracts
{
    /** @var array<string,array<string,mixed>> */
    private const CONTRACTS = [
        'site.inspect' => [
            'allowed_rings' => [1],
            'mutating' => false,
            'verification' => 'read_result',
        ],
        'post.update' => [
            'allowed_rings' => [1],
            'mutating' => true,
            'wordpress_semantics_required' => true,
            'snapshot_required' => true,
            'verification' => 'wordpress_readback',
        ],
        'event.update' => [
            'allowed_rings' => [1],
            'mutating' => true,
            'wordpress_semantics_required' => true,
            'snapshot_required' => true,
            'verification' => 'provider_readback',
        ],
        'plugin.update' => [
            'allowed_rings' => [1, 2],
            'mutating' => true,
            'snapshot_required' => true,
            'verification' => 'version_active_health',
        ],
        'plugin.deactivate' => [
            'allowed_rings' => [1, 2],
            'mutating' => true,
            'recovery' => true,
            'snapshot_required' => false,
            'verification' => 'plugin_inactive_boot_health',
        ],
        'plugin.rollback' => [
            'allowed_rings' => [2, 3],
            'mutating' => true,
            'recovery' => true,
            'snapshot_required' => true,
            'verification' => 'version_active_boot_health',
        ],
        'database.restore' => [
            'allowed_rings' => [4],
            'mutating' => true,
            'recovery' => true,
            'database_recovery' => true,
            'snapshot_required' => true,
            'verification' => 'database_integrity_wordpress_boot',
        ],
        'private-ui.update' => [
            'allowed_rings' => [5],
            'mutating' => true,
            'ui_fallback_allowed' => true,
            'snapshot_required' => true,
            'verification' => 'ui_readback',
        ],
    ];

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public static function normalize(array $request): array
    {
        $operation = trim((string)($request['operation'] ?? ''));
        if ($operation === '' || !isset(self::CONTRACTS[$operation])) {
            throw new InvalidArgumentException('Unknown administrative capability.');
        }

        $contract = self::CONTRACTS[$operation];
        return array_merge($request, $contract, [
            'operation' => $operation,
            'capability' => $operation,
        ]);
    }

    /** @return array<string,array<string,mixed>> */
    public static function catalog(): array
    {
        return self::CONTRACTS;
    }
}
