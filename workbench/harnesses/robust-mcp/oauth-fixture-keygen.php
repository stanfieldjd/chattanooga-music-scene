<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: oauth-fixture-keygen.php <private-pem> <jwks-json>\n");
    exit(2);
}

$key = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
if (false === $key) {
    throw new RuntimeException('Could not generate RSA fixture key.');
}

$privatePem = '';
if (!openssl_pkey_export($key, $privatePem)) {
    throw new RuntimeException('Could not export RSA fixture private key.');
}
$details = openssl_pkey_get_details($key);
if (!is_array($details) || !isset($details['rsa']['n'], $details['rsa']['e'])) {
    throw new RuntimeException('Could not extract RSA fixture public key details.');
}

$base64url = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

$jwks = [
    'keys' => [[
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => 'robust-ci-key',
        'n' => $base64url($details['rsa']['n']),
        'e' => $base64url($details['rsa']['e']),
    ]],
];

if (false === file_put_contents($argv[1], $privatePem, LOCK_EX)) {
    throw new RuntimeException('Could not write RSA fixture private key.');
}
@chmod($argv[1], 0600);
if (false === file_put_contents(
    $argv[2],
    json_encode($jwks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n",
    LOCK_EX,
)) {
    throw new RuntimeException('Could not write RSA fixture JWKS.');
}
@chmod($argv[2], 0600);

fwrite(STDOUT, "oauth-fixture-keygen: PASS rsa_bits=2048 alg=RS256 kid=robust-ci-key\n");
