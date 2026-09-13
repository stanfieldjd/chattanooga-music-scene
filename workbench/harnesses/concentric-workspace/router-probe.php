<?php

declare(strict_types=1);

require __DIR__ . '/workspace-router.php';

function fail_probe(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_probe(bool $condition, string $message): void
{
    if (!$condition) {
        fail_probe($message);
    }
}

function base_state(): array
{
    return [
        'wordpress_boots' => true,
        'wordpress_executor_available' => true,
        'wordpress_capabilities' => ['post.update', 'plugin.update', 'event.update', 'setting.update'],
        'wp_cli_available' => true,
        'wp_cli_capabilities' => ['post.update', 'plugin.update', 'plugin.deactivate', 'cache.flush'],
        'host_recovery_available' => true,
        'host_capabilities' => ['plugin.rollback', 'file.restore', 'git.restore'],
        'database_recovery_available' => true,
        'database_capabilities' => ['database.restore'],
        'browser_available' => true,
        'browser_capabilities' => ['private-ui.update'],
        'snapshot_available' => true,
    ];
}

$cases = [];

$cases['normal_wordpress'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'post.update',
    'authorized' => true,
    'wordpress_semantics_required' => true,
], base_state());
assert_probe(1 === ($cases['normal_wordpress']['ring'] ?? null), 'Normal post update did not remain in Ring 1.');

$state = base_state();
$state['wordpress_boots'] = false;
$cases['fatal_to_wp_cli'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'plugin.deactivate',
    'authorized' => true,
    'recovery' => true,
], $state);
assert_probe(2 === ($cases['fatal_to_wp_cli']['ring'] ?? null), 'WordPress fatal did not escalate to WP-CLI first.');

$state['wp_cli_available'] = false;
$cases['fatal_to_host'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'plugin.rollback',
    'authorized' => true,
    'recovery' => true,
    'destructive' => true,
], $state);
assert_probe(3 === ($cases['fatal_to_host']['ring'] ?? null), 'Recovery did not escalate to host ring after WP-CLI became unavailable.');

$cases['stale_state_blocks'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'setting.update',
    'authorized' => true,
    'state_conflict' => true,
], base_state());
assert_probe('blocked' === ($cases['stale_state_blocks']['status'] ?? null), 'Stale state was incorrectly escalated.');
assert_probe('stale_expected_state' === ($cases['stale_state_blocks']['reason'] ?? null), 'Stale state returned the wrong block reason.');

$cases['unauthorized_blocks'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'plugin.update',
    'authorized' => false,
], base_state());
assert_probe('authorization_required' === ($cases['unauthorized_blocks']['reason'] ?? null), 'Unauthorized task did not fail closed.');

$state = base_state();
$state['wordpress_capabilities'] = [];
$state['wp_cli_capabilities'] = [];
$state['host_capabilities'] = [];
$state['database_capabilities'] = [];
$cases['ui_last_resort'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'private-ui.update',
    'authorized' => true,
    'ui_fallback_allowed' => true,
], $state);
assert_probe(5 === ($cases['ui_last_resort']['ring'] ?? null), 'UI-only task did not route to browser as last resort.');

$state = base_state();
$state['wordpress_boots'] = false;
$state['wp_cli_available'] = false;
$state['host_recovery_available'] = false;
$cases['database_recovery'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'database.restore',
    'authorized' => true,
    'recovery' => true,
    'database_recovery' => true,
    'destructive' => true,
], $state);
assert_probe(4 === ($cases['database_recovery']['ring'] ?? null), 'Explicit database restore did not route to database recovery ring.');

$state['snapshot_available'] = false;
$cases['database_without_snapshot'] = CMSA_Concentric_Workspace_Router::route([
    'operation' => 'database.restore',
    'authorized' => true,
    'recovery' => true,
    'database_recovery' => true,
    'destructive' => true,
], $state);
assert_probe('snapshot_required' === ($cases['database_without_snapshot']['reason'] ?? null), 'Destructive database recovery ran without snapshot.');

mt_srand(9132026);
for ($i = 0; $i < 10000; ++$i) {
    $state = base_state();
    $capability = 'plugin.update';
    $state['wordpress_capabilities'] = mt_rand(0, 1) ? [$capability] : [];
    $state['wp_cli_capabilities'] = mt_rand(0, 1) ? [$capability] : [];
    $state['host_capabilities'] = mt_rand(0, 1) ? [$capability] : [];
    $state['browser_capabilities'] = mt_rand(0, 1) ? [$capability] : [];
    $state['wordpress_boots'] = (bool)mt_rand(0, 1);
    $state['wordpress_executor_available'] = (bool)mt_rand(0, 1);
    $state['wp_cli_available'] = (bool)mt_rand(0, 1);
    $state['host_recovery_available'] = (bool)mt_rand(0, 1);
    $state['browser_available'] = (bool)mt_rand(0, 1);

    $task = [
        'operation' => $capability,
        'authorized' => true,
        'recovery' => true,
        'ui_fallback_allowed' => true,
    ];

    $expected = null;
    if ($state['wordpress_boots'] && $state['wordpress_executor_available'] && in_array($capability, $state['wordpress_capabilities'], true)) {
        $expected = 1;
    } elseif ($state['wp_cli_available'] && in_array($capability, $state['wp_cli_capabilities'], true)) {
        $expected = 2;
    } elseif ($state['host_recovery_available'] && in_array($capability, $state['host_capabilities'], true)) {
        $expected = 3;
    } elseif ($state['browser_available'] && in_array($capability, $state['browser_capabilities'], true)) {
        $expected = 5;
    }

    $result = CMSA_Concentric_Workspace_Router::route($task, $state);
    $actual = $result['ring'] ?? null;
    assert_probe($expected === $actual, 'Property test detected authority escalation drift at iteration ' . $i . '.');
}

echo 'concentric-workspace-router: PASS cases=' . count($cases) . ' property_iterations=10000 policy=least_authority' . PHP_EOL;
