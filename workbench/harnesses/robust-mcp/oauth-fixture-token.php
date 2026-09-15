<?php

declare(strict_types=1);

use Firebase\JWT\JWT;

require __DIR__ . '/vendor/autoload.php';

if ($argc < 6 || $argc > 8) {
    fwrite(STDERR, "Usage: oauth-fixture-token.php <private-pem> <issuer> <audience> <scope> <ttl-seconds> [subject] [kid]\n");
    exit(2);
}

$privatePem = @file_get_contents($argv[1]);
if (false === $privatePem || '' === trim($privatePem)) {
    throw new RuntimeException('Fixture private key is unavailable.');
}

$issuer = $argv[2];
$audience = $argv[3];
$scope = $argv[4];
$ttl = filter_var($argv[5], FILTER_VALIDATE_INT);
if (false === $ttl || $ttl < -86400 || $ttl > 86400) {
    throw new InvalidArgumentException('Fixture token TTL must be between -86400 and 86400 seconds.');
}

$now = time();
$subject = $argv[6] ?? 'chatgpt-ci-subject';
$kid = $argv[7] ?? 'robust-ci-key';
$payload = [
    'iss' => $issuer,
    'aud' => $audience,
    'sub' => $subject,
    'client_id' => 'chatgpt-ci-client',
    'scope' => $scope,
    'iat' => $now - 5,
    'nbf' => $now - 5,
    'exp' => $now + $ttl,
    'jti' => bin2hex(random_bytes(16)),
];

fwrite(STDOUT, JWT::encode($payload, $privatePem, 'RS256', $kid));
