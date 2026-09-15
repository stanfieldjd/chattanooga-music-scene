<?php

declare(strict_types=1);

use Chattanooga\RobustMcp\LockAwareFileSessionStore;
use Chattanooga\RobustMcp\SessionLockCoordinator;

require __DIR__ . '/vendor/autoload.php';

$mode = $argv[1] ?? '';
$directory = $argv[2] ?? '';
if ('' === $mode || '' === $directory) {
    fwrite(STDERR, "usage error\n");
    exit(2);
}

$locks = new SessionLockCoordinator($directory);

if ('hold' === $mode) {
    $sessionId = (string) ($argv[3] ?? '');
    $milliseconds = (int) ($argv[4] ?? 0);
    $marker = (string) ($argv[5] ?? '');
    if ($milliseconds < 1 || $milliseconds > 10000 || '' === $marker) {
        fwrite(STDERR, "invalid hold arguments\n");
        exit(2);
    }
    $lease = $locks->acquire($sessionId, 1000);
    if (null === $lease) {
        fwrite(STDERR, "could not acquire session lock\n");
        exit(3);
    }
    file_put_contents($marker, "locked\n", LOCK_EX);
    usleep($milliseconds * 1000);
    $lease->release();
    exit(0);
}

if ('gc' === $mode) {
    $ttl = (int) ($argv[3] ?? 0);
    if ($ttl < 1) {
        fwrite(STDERR, "invalid ttl\n");
        exit(2);
    }
    $store = new LockAwareFileSessionStore($directory, $ttl, $locks);
    $deleted = array_map(static fn ($id): string => $id->toRfc4122(), $store->gc());
    sort($deleted, SORT_STRING);
    echo json_encode($deleted, JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

fwrite(STDERR, "unknown mode\n");
exit(2);
