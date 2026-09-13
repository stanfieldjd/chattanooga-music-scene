<?php

declare(strict_types=1);

final class CMSA_Concentric_Workspace_Router
{
    public const RING_WORDPRESS = 1;
    public const RING_WP_CLI = 2;
    public const RING_HOST = 3;
    public const RING_DATABASE = 4;
    public const RING_BROWSER = 5;

    /** @var array<int,string> */
    private const RING_NAMES = [
        self::RING_WORDPRESS => 'wordpress',
        self::RING_WP_CLI => 'wp_cli',
        self::RING_HOST => 'host_recovery',
        self::RING_DATABASE => 'database_recovery',
        self::RING_BROWSER => 'browser_fallback',
    ];

    /**
     * Route a normalized administrative task to the least-authoritative eligible ring.
     *
     * This POC is deliberately transport-independent and does not execute anything.
     * It proves escalation policy and produces an evidence journal for inspection.
     *
     * @param array<string,mixed> $task
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function route(array $task, array $state): array
    {
        $journal = [];
        $operation = trim((string)($task['operation'] ?? ''));
        $capability = trim((string)($task['capability'] ?? $operation));

        if ($operation === '') {
            return self::blocked('operation_required', $journal);
        }

        if (($task['authorized'] ?? false) !== true) {
            return self::blocked('authorization_required', $journal);
        }

        if (($task['state_conflict'] ?? false) === true) {
            return self::blocked('stale_expected_state', $journal);
        }

        if (($task['destructive'] ?? false) === true && ($task['snapshot_required'] ?? true) === true && ($state['snapshot_available'] ?? false) !== true) {
            return self::blocked('snapshot_required', $journal);
        }

        $rings = [
            self::RING_WORDPRESS => self::wordpress_eligibility($task, $state, $capability),
            self::RING_WP_CLI => self::wp_cli_eligibility($task, $state, $capability),
            self::RING_HOST => self::host_eligibility($task, $state, $capability),
            self::RING_DATABASE => self::database_eligibility($task, $state, $capability),
            self::RING_BROWSER => self::browser_eligibility($task, $state, $capability),
        ];

        foreach ($rings as $ring => $eligibility) {
            $journal[] = [
                'ring' => $ring,
                'name' => self::RING_NAMES[$ring],
                'eligible' => $eligibility['eligible'],
                'reason' => $eligibility['reason'],
            ];
            if ($eligibility['eligible']) {
                return [
                    'status' => 'routed',
                    'ring' => $ring,
                    'executor' => self::RING_NAMES[$ring],
                    'operation' => $operation,
                    'capability' => $capability,
                    'journal' => $journal,
                ];
            }
        }

        return self::blocked('no_eligible_executor', $journal);
    }

    /** @return array{eligible:bool,reason:string} */
    private static function wordpress_eligibility(array $task, array $state, string $capability): array
    {
        if (!self::ringAllowed($task, self::RING_WORDPRESS)) {
            return self::no('ring_not_allowed_by_contract');
        }
        if (($state['wordpress_boots'] ?? false) !== true) {
            return self::no('wordpress_unavailable');
        }
        if (($state['wordpress_executor_available'] ?? false) !== true) {
            return self::no('wordpress_executor_unavailable');
        }
        if (!self::supports($state['wordpress_capabilities'] ?? [], $capability)) {
            return self::no('capability_not_exposed');
        }
        return self::yes('lowest_authority_executor');
    }

    /** @return array{eligible:bool,reason:string} */
    private static function wp_cli_eligibility(array $task, array $state, string $capability): array
    {
        if (!self::ringAllowed($task, self::RING_WP_CLI)) {
            return self::no('ring_not_allowed_by_contract');
        }
        if (($state['wp_cli_available'] ?? false) !== true) {
            return self::no('wp_cli_unavailable');
        }
        if (!self::supports($state['wp_cli_capabilities'] ?? [], $capability)) {
            return self::no('capability_not_supported');
        }
        if (($task['wordpress_semantics_required'] ?? false) === true && ($state['wordpress_boots'] ?? false) === true) {
            return self::no('wordpress_semantics_preferred');
        }
        return self::yes('inner_ring_unavailable_or_incapable');
    }

    /** @return array{eligible:bool,reason:string} */
    private static function host_eligibility(array $task, array $state, string $capability): array
    {
        if (!self::ringAllowed($task, self::RING_HOST)) {
            return self::no('ring_not_allowed_by_contract');
        }
        if (($state['host_recovery_available'] ?? false) !== true) {
            return self::no('host_recovery_unavailable');
        }
        if (($task['recovery'] ?? false) !== true) {
            return self::no('host_ring_recovery_only');
        }
        if (!self::supports($state['host_capabilities'] ?? [], $capability)) {
            return self::no('capability_not_supported');
        }
        return self::yes('recovery_escalation');
    }

    /** @return array{eligible:bool,reason:string} */
    private static function database_eligibility(array $task, array $state, string $capability): array
    {
        if (!self::ringAllowed($task, self::RING_DATABASE)) {
            return self::no('ring_not_allowed_by_contract');
        }
        if (($state['database_recovery_available'] ?? false) !== true) {
            return self::no('database_recovery_unavailable');
        }
        if (($task['database_recovery'] ?? false) !== true) {
            return self::no('database_ring_explicit_recovery_only');
        }
        if (($state['snapshot_available'] ?? false) !== true) {
            return self::no('database_snapshot_required');
        }
        if (!self::supports($state['database_capabilities'] ?? [], $capability)) {
            return self::no('capability_not_supported');
        }
        return self::yes('explicit_database_recovery');
    }

    /** @return array{eligible:bool,reason:string} */
    private static function browser_eligibility(array $task, array $state, string $capability): array
    {
        if (!self::ringAllowed($task, self::RING_BROWSER)) {
            return self::no('ring_not_allowed_by_contract');
        }
        if (($state['browser_available'] ?? false) !== true) {
            return self::no('browser_unavailable');
        }
        if (($task['ui_fallback_allowed'] ?? false) !== true) {
            return self::no('browser_fallback_not_authorized');
        }
        if (!self::supports($state['browser_capabilities'] ?? [], $capability)) {
            return self::no('capability_not_supported');
        }
        return self::yes('last_resort_ui_adapter');
    }

    /** @param array<string,mixed> $task */
    private static function ringAllowed(array $task, int $ring): bool
    {
        if (!array_key_exists('allowed_rings', $task)) {
            return true;
        }
        return is_array($task['allowed_rings']) && in_array($ring, $task['allowed_rings'], true);
    }

    /** @param mixed $capabilities */
    private static function supports($capabilities, string $capability): bool
    {
        return is_array($capabilities) && in_array($capability, $capabilities, true);
    }

    /** @return array{eligible:bool,reason:string} */
    private static function yes(string $reason): array
    {
        return ['eligible' => true, 'reason' => $reason];
    }

    /** @return array{eligible:bool,reason:string} */
    private static function no(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason];
    }

    /** @param array<int,array<string,mixed>> $journal */
    private static function blocked(string $reason, array $journal): array
    {
        return [
            'status' => 'blocked',
            'reason' => $reason,
            'journal' => $journal,
        ];
    }
}
