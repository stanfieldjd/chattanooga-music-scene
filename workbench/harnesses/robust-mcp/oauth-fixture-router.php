<?php

declare(strict_types=1);

$issuer = getenv('OAUTH_FIXTURE_ISSUER');
$jwksFile = getenv('OAUTH_FIXTURE_JWKS_FILE');
$counterFile = getenv('OAUTH_FIXTURE_COUNTER_FILE');

if (false === $issuer || '' === trim($issuer) || false === $jwksFile || '' === trim($jwksFile)) {
    http_response_code(500);
    echo 'fixture configuration error';
    return;
}

$issuer = rtrim(trim($issuer), '/');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (false !== $counterFile && '' !== trim($counterFile)) {
    @file_put_contents($counterFile, $path . "\n", FILE_APPEND | LOCK_EX);
}

header('Cache-Control: no-store');

if ('/.well-known/oauth-authorization-server' === $path || '/.well-known/openid-configuration' === $path) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'issuer' => $issuer,
        'authorization_endpoint' => $issuer . '/authorize',
        'token_endpoint' => $issuer . '/token',
        'jwks_uri' => $issuer . '/jwks',
        'code_challenge_methods_supported' => ['S256'],
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    return;
}

if ('/jwks' === $path) {
    if (!is_readable($jwksFile)) {
        http_response_code(500);
        echo 'missing jwks';
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    readfile($jwksFile);
    return;
}

if ('/authorize' === $path || '/token' === $path) {
    http_response_code(501);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'not_implemented_fixture_endpoint'], JSON_THROW_ON_ERROR);
    return;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR);
