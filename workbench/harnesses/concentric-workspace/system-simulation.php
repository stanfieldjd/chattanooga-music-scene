<?php

declare(strict_types=1);

require __DIR__ . '/workspace-router.php';
require __DIR__ . '/capability-contracts.php';
require __DIR__ . '/evidence-journal.php';

function sim_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function sim_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sim_fail($message);
    }
}

function sim_state(string $operation): array
{
    $all = ['site.inspect', 'post.update', 'event.update', 'plugin.update', 'plugin.deactivate', 'plugin.rollback', 'database.restore', 'private-ui.update'];
    return [
        'wordpress_boots' => (bool)mt_rand(0, 1),
        'wordpress_executor_available' => (bool)mt_rand(0, 1),
        'wordpress_capabilities' => mt_rand(0, 4) ? $all : [],
        'wp_cli_available' => (bool)mt_rand(0, 1),
        'wp_cli_capabilities' => mt_rand(0, 4) ? $all : [],
        'host_recovery_available' => (bool)mt_rand(0, 1),
        'host_capabilities' => mt_rand(0, 4) ? $all : [],
        'database_recovery_available' => (bool)mt_rand(0, 1),
        'database_capabilities' => mt_rand(0, 4) ? $all : [],
        'browser_available' => (bool)mt_rand(0, 1),
        'browser_capabilities' => mt_rand(0, 4) ? $all : [],
        'snapshot_available' => (bool)mt_rand(0, 1),
    ];
}

mt_srand(15092026);
$catalog = CMSA_Concentric_Capability_Contracts::catalog();
$operations = array_keys($catalog);
$counts = [
    'blocked' => 0,
    'complete' => 0,
    'failed' => 0,
    'rolled_back' => 0,
];

for ($i = 0; $i < 20000; ++$i) {
    $operation = $operations[array_rand($operations)];
    $authorized = mt_rand(0, 19) !== 0;
    $stateConflict = mt_rand(0, 24) === 0;
    $task = CMSA_Concentric_Capability_Contracts::normalize([
        'operation' => $operation,
        'authorized' => $authorized,
        'state_conflict' => $stateConflict,
    ]);
    $state = sim_state($operation);
    $route = CMSA_Concentric_Workspace_Router::route($task, $state);

    if (($route['status'] ?? '') !== 'routed') {
        ++$counts['blocked'];
        sim_assert(!isset($route['ring']), 'Blocked task unexpectedly exposed an executor ring.');
        continue;
    }

    sim_assert($authorized, 'Unauthorized task was routed.');
    sim_assert(!$stateConflict, 'Stale-state task was routed.');
    sim_assert(in_array($route['ring'], $task['allowed_rings'], true), 'Task escaped its capability contract.');

    $journal = new CMSA_Concentric_Evidence_Journal([
        'id' => 'sim-' . $i,
        'operation' => $operation,
        'mutating' => ($task['mutating'] ?? false) === true,
    ]);

    if (($task['mutating'] ?? false) === true) {
        $journal->captureBefore(['operation' => $operation, 'before_revision' => $i]);
    }
    $journal->recordRoute((int)$route['ring'], (string)$route['executor']);
    $journal->recordExecution(['executor' => $route['executor'], 'simulated_revision' => $i + 1]);

    $verificationPass = mt_rand(0, 9) !== 0;
    $journal->verify($verificationPass, [
        'operation' => $operation,
        'simulated_revision' => $i + 1,
        'healthy' => $verificationPass,
    ]);

    if ($verificationPass) {
        sim_assert($journal->status() === 'complete', 'Verified task did not settle complete.');
        ++$counts['complete'];
    } elseif (($task['mutating'] ?? false) === true) {
        sim_assert($journal->status() === 'needs_rollback', 'Failed mutation did not require rollback.');
        $journal->recordRollback(['operation' => $operation, 'restored_revision' => $i]);
        sim_assert($journal->status() === 'rolled_back', 'Mutation rollback did not settle rolled_back.');
        ++$counts['rolled_back'];
    } else {
        sim_assert($journal->status() === 'failed', 'Failed read did not settle failed.');
        ++$counts['failed'];
    }

    sim_assert($journal->verifyChain(), 'Evidence chain invalid after end-to-end simulation at iteration ' . $i . '.');
}

$total = array_sum($counts);
sim_assert($total === 20000, 'Simulation accounting mismatch.');

echo 'concentric-system-simulation: PASS iterations=20000 blocked=' . $counts['blocked']
    . ' complete=' . $counts['complete']
    . ' failed=' . $counts['failed']
    . ' rolled_back=' . $counts['rolled_back']
    . ' invariant_violations=0' . PHP_EOL;
