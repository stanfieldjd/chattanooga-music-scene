<?php

declare(strict_types=1);

use Firebase\JWT\JWT;

require __DIR__ . '/vendor/autoload.php';

$issuer = getenv('OAUTH_FIXTURE_ISSUER');
$jwksFile = getenv('OAUTH_FIXTURE_JWKS_FILE');
$counterFile = getenv('OAUTH_FIXTURE_COUNTER_FILE');
$privateKeyFile = getenv('OAUTH_FIXTURE_PRIVATE_KEY');
$stateFile = getenv('OAUTH_FIXTURE_STATE_FILE');
$audience = getenv('OAUTH_FIXTURE_AUDIENCE');
$resource = getenv('OAUTH_FIXTURE_RESOURCE');
$redirectUri = getenv('OAUTH_FIXTURE_REDIRECT_URI');

if (
    false === $issuer || '' === trim($issuer)
    || false === $jwksFile || '' === trim($jwksFile)
    || false === $privateKeyFile || '' === trim($privateKeyFile)
    || false === $stateFile || '' === trim($stateFile)
    || false === $audience || '' === trim($audience)
    || false === $resource || '' === trim($resource)
    || false === $redirectUri || '' === trim($redirectUri)
) {
    http_response_code(500);
    echo 'fixture configuration error';
    return;
}

$issuer = rtrim(trim($issuer), '/');
$audience = trim($audience);
$resource = trim($resource);
$redirectUri = trim($redirectUri);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (false !== $counterFile && '' !== trim($counterFile)) {
    @file_put_contents($counterFile, $method . ' ' . $path . "\n", FILE_APPEND | LOCK_EX);
}

header('Cache-Control: no-store');
header('Pragma: no-cache');

$json = static function (int $status, array $payload): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$base64url = static function (string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
};

$stateTransaction = static function (callable $callback) use ($stateFile): mixed {
    $handle = @fopen($stateFile, 'c+');
    if (false === $handle) {
        throw new RuntimeException('fixture state unavailable');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('fixture state lock unavailable');
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = '' === $raw ? ['codes' => [], 'refresh' => []] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state)) {
            $state = ['codes' => [], 'refresh' => []];
        }
        $state['codes'] = is_array($state['codes'] ?? null) ? $state['codes'] : [];
        $state['refresh'] = is_array($state['refresh'] ?? null) ? $state['refresh'] : [];

        $result = $callback($state);

        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('fixture state truncate failed');
        }
        $encoded = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (false === fwrite($handle, $encoded)) {
            throw new RuntimeException('fixture state write failed');
        }
        fflush($handle);
        flock($handle, LOCK_UN);
        return $result;
    } finally {
        fclose($handle);
    }
};

$issueAccessToken = static function (string $scope, string $subject = 'chatgpt-ci-subject') use ($privateKeyFile, $issuer, $audience): string {
    $privatePem = @file_get_contents($privateKeyFile);
    if (false === $privatePem || '' === trim($privatePem)) {
        throw new RuntimeException('fixture signing key unavailable');
    }

    $now = time();
    return JWT::encode([
        'iss' => $issuer,
        'aud' => $audience,
        'sub' => $subject,
        'client_id' => 'chatgpt-ci-client',
        'scope' => $scope,
        'iat' => $now - 2,
        'nbf' => $now - 2,
        'exp' => $now + 300,
        'jti' => bin2hex(random_bytes(16)),
    ], $privatePem, 'RS256', 'robust-ci-key');
};

if ('/.well-known/oauth-authorization-server' === $path || '/.well-known/openid-configuration' === $path) {
    $json(200, [
        'issuer' => $issuer,
        'authorization_endpoint' => $issuer . '/authorize',
        'token_endpoint' => $issuer . '/token',
        'jwks_uri' => $issuer . '/jwks',
        'code_challenge_methods_supported' => ['S256'],
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'token_endpoint_auth_methods_supported' => ['none'],
        'scopes_supported' => ['mcp:connect', 'offline_access'],
    ]);
}

if ('/jwks' === $path) {
    if (!is_readable($jwksFile)) {
        $json(500, ['error' => 'missing_jwks']);
    }
    header('Content-Type: application/json; charset=utf-8');
    readfile($jwksFile);
    return;
}

if ('/authorize' === $path) {
    if ('GET' !== $method) {
        $json(405, ['error' => 'method_not_allowed']);
    }

    $clientId = (string) ($_GET['client_id'] ?? '');
    $requestedRedirect = (string) ($_GET['redirect_uri'] ?? '');
    $responseType = (string) ($_GET['response_type'] ?? '');
    $challenge = (string) ($_GET['code_challenge'] ?? '');
    $challengeMethod = (string) ($_GET['code_challenge_method'] ?? '');
    $scope = trim((string) ($_GET['scope'] ?? ''));
    $requestedResource = (string) ($_GET['resource'] ?? '');
    $requestState = (string) ($_GET['state'] ?? '');

    $scopeTokens = preg_split('/\s+/', $scope) ?: [];
    $scopeTokens = array_values(array_unique(array_filter($scopeTokens, static fn (string $item): bool => '' !== $item)));

    if ('chatgpt-ci-client' !== $clientId || $redirectUri !== $requestedRedirect || 'code' !== $responseType) {
        $json(400, ['error' => 'invalid_request']);
    }
    if ('S256' !== $challengeMethod || 1 !== preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $challenge)) {
        $json(400, ['error' => 'invalid_request', 'error_description' => 'PKCE S256 is required.']);
    }
    if ($resource !== $requestedResource) {
        $json(400, ['error' => 'invalid_target']);
    }
    if (!in_array('mcp:connect', $scopeTokens, true)) {
        $json(400, ['error' => 'invalid_scope']);
    }

    $code = $base64url(random_bytes(32));
    $codeHash = hash('sha256', $code);
    $stateTransaction(static function (array &$fixtureState) use ($codeHash, $challenge, $scopeTokens, $requestedRedirect, $clientId, $resource): void {
        $fixtureState['codes'][$codeHash] = [
            'challenge' => $challenge,
            'scope' => implode(' ', $scopeTokens),
            'redirect_uri' => $requestedRedirect,
            'client_id' => $clientId,
            'resource' => $resource,
            'expires_at' => time() + 120,
        ];
    });

    $query = ['code' => $code];
    if ('' !== $requestState) {
        $query['state'] = $requestState;
    }
    header('Location: ' . $redirectUri . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

if ('/token' === $path) {
    if ('POST' !== $method) {
        $json(405, ['error' => 'method_not_allowed']);
    }
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ('application/x-www-form-urlencoded' !== $contentType) {
        $json(415, ['error' => 'invalid_request']);
    }

    parse_str((string) file_get_contents('php://input'), $form);
    $grantType = (string) ($form['grant_type'] ?? '');
    $clientId = (string) ($form['client_id'] ?? '');
    if ('chatgpt-ci-client' !== $clientId) {
        $json(401, ['error' => 'invalid_client']);
    }

    if ('authorization_code' === $grantType) {
        $code = (string) ($form['code'] ?? '');
        $verifier = (string) ($form['code_verifier'] ?? '');
        $requestedRedirect = (string) ($form['redirect_uri'] ?? '');
        if ('' === $code || 1 !== preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier)) {
            $json(400, ['error' => 'invalid_grant']);
        }
        $codeHash = hash('sha256', $code);
        $challenge = $base64url(hash('sha256', $verifier, true));

        $record = $stateTransaction(static function (array &$fixtureState) use ($codeHash, $challenge, $requestedRedirect, $clientId, $resource): ?array {
            $record = $fixtureState['codes'][$codeHash] ?? null;
            if (!is_array($record)
                || ($record['expires_at'] ?? 0) < time()
                || !hash_equals((string) ($record['challenge'] ?? ''), $challenge)
                || ($record['redirect_uri'] ?? '') !== $requestedRedirect
                || ($record['client_id'] ?? '') !== $clientId
                || ($record['resource'] ?? '') !== $resource
            ) {
                return null;
            }
            unset($fixtureState['codes'][$codeHash]);
            return $record;
        });
        if (null === $record) {
            $json(400, ['error' => 'invalid_grant']);
        }

        $scope = (string) ($record['scope'] ?? 'mcp:connect');
        $payload = [
            'access_token' => $issueAccessToken($scope),
            'token_type' => 'Bearer',
            'expires_in' => 300,
            'scope' => $scope,
        ];

        if (in_array('offline_access', preg_split('/\s+/', $scope) ?: [], true)) {
            $refreshToken = $base64url(random_bytes(48));
            $refreshHash = hash('sha256', $refreshToken);
            $stateTransaction(static function (array &$fixtureState) use ($refreshHash, $scope, $clientId, $resource): void {
                $fixtureState['refresh'][$refreshHash] = [
                    'scope' => $scope,
                    'client_id' => $clientId,
                    'resource' => $resource,
                    'expires_at' => time() + 3600,
                ];
            });
            $payload['refresh_token'] = $refreshToken;
        }

        $json(200, $payload);
    }

    if ('refresh_token' === $grantType) {
        $refreshToken = (string) ($form['refresh_token'] ?? '');
        $requestedResource = (string) ($form['resource'] ?? $resource);
        if ('' === $refreshToken || $resource !== $requestedResource) {
            $json(400, ['error' => 'invalid_grant']);
        }
        $refreshHash = hash('sha256', $refreshToken);

        $record = $stateTransaction(static function (array &$fixtureState) use ($refreshHash, $clientId, $resource): ?array {
            $record = $fixtureState['refresh'][$refreshHash] ?? null;
            if (!is_array($record)
                || ($record['expires_at'] ?? 0) < time()
                || ($record['client_id'] ?? '') !== $clientId
                || ($record['resource'] ?? '') !== $resource
            ) {
                return null;
            }
            unset($fixtureState['refresh'][$refreshHash]);
            return $record;
        });
        if (null === $record) {
            $json(400, ['error' => 'invalid_grant']);
        }

        $scope = (string) ($record['scope'] ?? 'mcp:connect');
        $rotatedRefresh = $base64url(random_bytes(48));
        $rotatedHash = hash('sha256', $rotatedRefresh);
        $stateTransaction(static function (array &$fixtureState) use ($rotatedHash, $scope, $clientId, $resource): void {
            $fixtureState['refresh'][$rotatedHash] = [
                'scope' => $scope,
                'client_id' => $clientId,
                'resource' => $resource,
                'expires_at' => time() + 3600,
            ];
        });

        $json(200, [
            'access_token' => $issueAccessToken($scope),
            'refresh_token' => $rotatedRefresh,
            'token_type' => 'Bearer',
            'expires_in' => 300,
            'scope' => $scope,
        ]);
    }

    $json(400, ['error' => 'unsupported_grant_type']);
}

$json(404, ['error' => 'not_found']);
