<?php

declare(strict_types=1);

require __DIR__ . '/wp-cli-planner.php';

function cli_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function cli_assert(bool $condition, string $message): void
{
    if (!$condition) {
        cli_fail($message);
    }
}

$path = '/home/example/site';
$update = CMSA_Concentric_WP_CLI_Planner::plan('plugin.update', ['slug' => 'events-manager'], $path);
cli_assert($update === ['wp', 'plugin', 'update', 'events-manager', '--path=/home/example/site'], 'Plugin update argv is not deterministic.');

$rollback = CMSA_Concentric_WP_CLI_Planner::plan('plugin.rollback', ['slug' => 'events-manager', 'version' => '7.4.3'], $path);
cli_assert($rollback === ['wp', 'plugin', 'install', 'events-manager', '--version=7.4.3', '--force', '--path=/home/example/site'], 'Plugin rollback argv is not deterministic.');

$malicious = [
    'events-manager;rm -rf /',
    '$(touch /tmp/pwned)',
    '../plugin',
    'plugin name',
    "plugin\n--allow-root",
    '--allow-root',
];
foreach ($malicious as $slug) {
    $rejected = false;
    try {
        CMSA_Concentric_WP_CLI_Planner::plan('plugin.update', ['slug' => $slug], $path);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    cli_assert($rejected, 'Malicious plugin slug was accepted: ' . json_encode($slug));
}

foreach (['shell.exec', 'wp.eval', 'database.query', 'core.download', 'user.create'] as $operation) {
    $rejected = false;
    try {
        CMSA_Concentric_WP_CLI_Planner::plan($operation, [], $path);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    cli_assert($rejected, 'Unknown WP-CLI operation was accepted: ' . $operation);
}

$badVersions = ['7.4.3;id', '$(id)', '../7.4.3', "7.4.3\n--allow-root", ''];
foreach ($badVersions as $version) {
    $rejected = false;
    try {
        CMSA_Concentric_WP_CLI_Planner::plan('plugin.rollback', ['slug' => 'events-manager', 'version' => $version], $path);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    cli_assert($rejected, 'Malicious version was accepted: ' . json_encode($version));
}

mt_srand(16092026);
$alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789-._';
for ($i = 0; $i < 10000; ++$i) {
    $length = mt_rand(1, 30);
    $slug = '';
    for ($j = 0; $j < $length; ++$j) {
        $slug .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
    }
    if (!preg_match('/^[a-z0-9]/', $slug)) {
        $slug = 'p' . $slug;
    }
    $argv = CMSA_Concentric_WP_CLI_Planner::plan('plugin.deactivate', ['slug' => $slug], $path);
    cli_assert(count($argv) === 5, 'Planner changed argv shape during fuzzing.');
    cli_assert($argv[0] === 'wp' && $argv[1] === 'plugin' && $argv[2] === 'deactivate', 'Planner changed fixed command prefix.');
    foreach ($argv as $arg) {
        cli_assert(!str_contains($arg, ';') && !str_contains($arg, '$(') && !str_contains($arg, "\n"), 'Planner emitted shell metacharacter in fuzzing.');
    }
}

echo 'concentric-wp-cli-planner: PASS fixed_operations=4 malicious_inputs_rejected=16 fuzz_iterations=10000 shell_strings=none' . PHP_EOL;
