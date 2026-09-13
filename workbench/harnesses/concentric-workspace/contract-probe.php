<?php

declare(strict_types=1);

require __DIR__ . '/workspace-router.php';
require __DIR__ . '/capability-contracts.php';

function contract_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        contract_fail($message);
    }
}

function contract_state(): array
{
    return [
        'wordpress_boots' => true,
        'wordpress_executor_available' => true,
        'wordpress_capabilities' => ['site.inspect', 'post.update', 'event.update', 'plugin.update', 'plugin.deactivate', 'plugin.rollback', 'database.restore', 'private-ui.update'],
        'wp_cli_available' => true,
        'wp_cli_capabilities' => ['post.update', 'plugin.update', 'plugin.deactivate', 'plugin.rollback', 'database.restore', 'private-ui.update'],
        'host_recovery_available' => true,
        'host_capabilities' => ['post.update', 'plugin.update', 'plugin.rollback', 'database.restore', 'private-ui.update'],
        'database_recovery_available' => true,
        'database_capabilities' => ['post.update', 'plugin.update', 'database.restore', 'private-ui.update'],
        'browser_available' => true,
        'browser_capabilities' => ['post.update', 'plugin.update', 'database.restore', 'private-ui.update'],
        'snapshot_available' => true,
    ];
}

$unknowns = ['shell.exec', 'php.eval', 'sql.raw', 'filesystem.delete-anything'];
foreach ($unknowns as $operation) {
    $rejected = false;
    try {
        CMSA_Concentric_Capability_Contracts::normalize(['operation' => $operation, 'authorized' => true]);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    contract_assert($rejected, 'Unknown capability was accepted: ' . $operation);
}

$state = contract_state();
$state['wordpress_boots'] = false;
$post = CMSA_Concentric_Capability_Contracts::normalize(['operation' => 'post.update', 'authorized' => true]);
$postResult = CMSA_Concentric_Workspace_Router::route($post, $state);
contract_assert(($postResult['status'] ?? '') === 'blocked', 'Post update escaped to an outer ring when WordPress was unavailable.');

$state = contract_state();
$state['wordpress_boots'] = false;
$plugin = CMSA_Concentric_Capability_Contracts::normalize(['operation' => 'plugin.update', 'authorized' => true]);
$pluginResult = CMSA_Concentric_Workspace_Router::route($plugin, $state);
contract_assert(($pluginResult['ring'] ?? null) === 2, 'Plugin update did not fall back only to its allowed WP-CLI ring.');

$state = contract_state();
$state['wordpress_boots'] = false;
$state['wp_cli_available'] = false;
$rollback = CMSA_Concentric_Capability_Contracts::normalize(['operation' => 'plugin.rollback', 'authorized' => true]);
$rollbackResult = CMSA_Concentric_Workspace_Router::route($rollback, $state);
contract_assert(($rollbackResult['ring'] ?? null) === 3, 'Plugin rollback did not use its allowed host recovery ring.');

$state = contract_state();
$db = CMSA_Concentric_Capability_Contracts::normalize(['operation' => 'database.restore', 'authorized' => true]);
$dbResult = CMSA_Concentric_Workspace_Router::route($db, $state);
contract_assert(($dbResult['ring'] ?? null) === 4, 'Database restore escaped its dedicated recovery ring.');

$state = contract_state();
$ui = CMSA_Concentric_Capability_Contracts::normalize(['operation' => 'private-ui.update', 'authorized' => true]);
$uiResult = CMSA_Concentric_Workspace_Router::route($ui, $state);
contract_assert(($uiResult['ring'] ?? null) === 5, 'UI-only capability escaped the browser-only contract.');

mt_srand(14092026);
$catalog = CMSA_Concentric_Capability_Contracts::catalog();
$operations = array_keys($catalog);
for ($i = 0; $i < 5000; ++$i) {
    $operation = $operations[array_rand($operations)];
    $task = CMSA_Concentric_Capability_Contracts::normalize(['operation' => $operation, 'authorized' => true]);
    $state = contract_state();
    $state['wordpress_boots'] = (bool)mt_rand(0, 1);
    $state['wordpress_executor_available'] = (bool)mt_rand(0, 1);
    $state['wp_cli_available'] = (bool)mt_rand(0, 1);
    $state['host_recovery_available'] = (bool)mt_rand(0, 1);
    $state['database_recovery_available'] = (bool)mt_rand(0, 1);
    $state['browser_available'] = (bool)mt_rand(0, 1);
    $result = CMSA_Concentric_Workspace_Router::route($task, $state);
    if (($result['status'] ?? '') === 'routed') {
        contract_assert(in_array($result['ring'], $task['allowed_rings'], true), 'Router escaped capability contract at iteration ' . $i . '.');
    }
}

echo 'concentric-capability-contracts: PASS unknowns=4 fuzz_iterations=5000 contract_escape=none' . PHP_EOL;
