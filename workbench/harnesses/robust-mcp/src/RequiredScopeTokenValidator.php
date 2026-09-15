<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Server\Transport\Http\OAuth\AuthorizationResult;
use Mcp\Server\Transport\Http\OAuth\AuthorizationTokenValidatorInterface;

/** Enforces server-wide OAuth scopes after cryptographic token validation. */
final class RequiredScopeTokenValidator implements AuthorizationTokenValidatorInterface
{
    /** @param non-empty-list<string> $requiredScopes */
    public function __construct(
        private readonly AuthorizationTokenValidatorInterface $inner,
        private readonly array $requiredScopes,
    ) {
        if ([] === $this->requiredScopes) {
            throw new \InvalidArgumentException('At least one OAuth scope must be required.');
        }
    }

    public function validate(string $accessToken): AuthorizationResult
    {
        $result = $this->inner->validate($accessToken);
        if (!$result->isAllowed()) {
            return $result;
        }

        $attributes = $result->getAttributes();
        $tokenScopes = $attributes['oauth.scopes'] ?? [];
        if (!is_array($tokenScopes)) {
            $tokenScopes = [];
        }
        $tokenScopes = array_values(array_filter($tokenScopes, 'is_string'));

        foreach ($this->requiredScopes as $required) {
            if (!in_array($required, $tokenScopes, true)) {
                return AuthorizationResult::forbidden(
                    'insufficient_scope',
                    'The access token does not contain every scope required by this MCP resource.',
                    $this->requiredScopes,
                );
            }
        }

        return $result;
    }
}
