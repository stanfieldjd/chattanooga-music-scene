<?php

declare(strict_types=1);

require __DIR__ . '/evidence-journal.php';

function evidence_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function evidence_assert(bool $condition, string $message): void
{
    if (!$condition) {
        evidence_fail($message);
    }
}

$read = new CMSA_Concentric_Evidence_Journal(['id' => 'read-1', 'operation' => 'site.inspect', 'mutating' => false]);
$read->recordRoute(1, 'wordpress');
$read->recordExecution(['wordpress_version' => '7.1']);
$read->verify(true, ['wordpress_version' => '7.1']);
evidence_assert($read->status() === 'complete', 'Verified read did not complete.');
evidence_assert($read->verifyChain(), 'Read evidence chain failed validation.');

$mutation = new CMSA_Concentric_Evidence_Journal(['id' => 'mut-1', 'operation' => 'plugin.update', 'mutating' => true]);
$mutation->recordRoute(1, 'wordpress');
$blocked = false;
try {
    $mutation->recordExecution(['version' => '2.0.0']);
} catch (LogicException $e) {
    $blocked = true;
}
evidence_assert($blocked, 'Mutation executed without before-state capture.');

$mutation->captureBefore(['version' => '1.0.0', 'active' => true]);
$mutation->recordExecution(['version' => '2.0.0']);
$mutation->verify(true, ['version' => '2.0.0', 'active' => true]);
evidence_assert($mutation->status() === 'complete', 'Verified mutation did not complete.');
evidence_assert($mutation->verifyChain(), 'Mutation evidence chain failed validation.');

$rollback = new CMSA_Concentric_Evidence_Journal(['id' => 'mut-2', 'operation' => 'plugin.update', 'mutating' => true]);
$rollback->captureBefore(['version' => '1.0.0', 'active' => true]);
$rollback->recordRoute(1, 'wordpress');
$rollback->recordExecution(['version' => '2.0.0']);
$rollback->verify(false, ['fatal' => true]);
evidence_assert($rollback->status() === 'needs_rollback', 'Failed verification did not require rollback.');
$rollback->recordRollback(['version' => '1.0.0', 'active' => true]);
evidence_assert($rollback->status() === 'rolled_back', 'Rollback was not recorded as final recovery state.');
evidence_assert($rollback->verifyChain(), 'Rollback evidence chain failed validation.');

$tampered = new CMSA_Concentric_Evidence_Journal(['id' => 'tamper-1', 'operation' => 'setting.update', 'mutating' => true]);
$tampered->captureBefore(['value' => 'old']);
$tampered->recordRoute(1, 'wordpress');
$tampered->recordExecution(['value' => 'new']);
$tampered->verify(true, ['value' => 'new']);
evidence_assert($tampered->verifyChain(), 'Untampered chain did not validate.');
$tampered->tamperForProbe(1, 'type', 'forged_before_state');
evidence_assert(!$tampered->verifyChain(), 'Tampered evidence chain was not detected.');

mt_srand(13092026);
for ($i = 0; $i < 5000; ++$i) {
    $mutating = (bool)mt_rand(0, 1);
    $pass = (bool)mt_rand(0, 1);
    $journal = new CMSA_Concentric_Evidence_Journal([
        'id' => 'fuzz-' . $i,
        'operation' => $mutating ? 'setting.update' : 'site.inspect',
        'mutating' => $mutating,
    ]);
    if ($mutating) {
        $journal->captureBefore(['n' => $i]);
    }
    $journal->recordRoute(1, 'wordpress');
    $journal->recordExecution(['n' => $i + 1]);
    $journal->verify($pass, ['n' => $i + 1]);

    if ($mutating && !$pass) {
        evidence_assert($journal->status() === 'needs_rollback', 'Fuzz mutation failure escaped rollback state.');
        $journal->recordRollback(['n' => $i]);
        evidence_assert($journal->status() === 'rolled_back', 'Fuzz rollback did not settle as rolled_back.');
    } elseif ($pass) {
        evidence_assert($journal->status() === 'complete', 'Fuzz verified task did not complete.');
    } else {
        evidence_assert($journal->status() === 'failed', 'Fuzz failed read had wrong status.');
    }
    evidence_assert($journal->verifyChain(), 'Fuzz evidence chain failed validation at iteration ' . $i . '.');
}

echo 'concentric-evidence-journal: PASS directed=4 fuzz_iterations=5000 hash_chain=verified rollback_state=verified' . PHP_EOL;
