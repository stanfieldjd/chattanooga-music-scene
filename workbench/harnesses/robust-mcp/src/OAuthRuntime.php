<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Server\Transport\Http\OAuth\AuthorizationTokenValidatorInterface;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;

final class OAuthRuntime
{
    public function __construct(
        public readonly AuthorizationTokenValidatorInterface $validator,
        public readonly ProtectedResourceMetadata $metadata,
    ) {
    }
}
