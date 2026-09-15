<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Mcp\Server\Transport\Http\OAuth\JwksProvider;
use Mcp\Server\Transport\Http\OAuth\JwtTokenValidator;
use Mcp\Server\Transport\Http\OAuth\OidcDiscovery;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

final class OAuthRuntimeFactory
{
    public function create(OAuthConfiguration $configuration): OAuthRuntime
    {
        $configuration->prepareForServing();

        $psr17 = new Psr17Factory();
        $nativeClient = HttpClient::create([
            'timeout' => 5.0,
            'max_duration' => 8.0,
            'max_redirects' => 0,
        ]);
        $httpClient = new Psr18Client($nativeClient, $psr17, $psr17);

        $cache = new Psr16Cache(
            new FilesystemAdapter(
                namespace: 'robust_mcp_oauth',
                defaultLifetime: $configuration->cacheTtl,
                directory: $configuration->cacheDirectory,
            ),
        );

        $discovery = new OidcDiscovery(
            httpClient: $httpClient,
            requestFactory: $httpClient,
            cache: $cache,
            cacheTtl: $configuration->cacheTtl,
            metadataPolicy: new SafeOidcDiscoveryMetadataPolicy(),
        );
        $jwksProvider = new JwksProvider(
            discovery: $discovery,
            httpClient: $httpClient,
            requestFactory: $httpClient,
            cache: $cache,
            cacheTtl: $configuration->cacheTtl,
        );

        $jwtValidator = new JwtTokenValidator(
            issuer: $configuration->issuer,
            audience: $configuration->audiences,
            jwksProvider: $jwksProvider,
            jwksUri: $configuration->jwksUri,
            algorithms: ['RS256', 'RS384', 'RS512'],
            scopeClaim: 'scope',
        );

        $metadata = new ProtectedResourceMetadata(
            authorizationServers: [$configuration->issuer],
            scopesSupported: $configuration->scopes,
            resource: $configuration->resource,
            resourceName: 'Chattanooga Robust MCP',
            extra: [
                'bearer_methods_supported' => ['header'],
            ],
            metadataPaths: $configuration->metadataPaths(),
        );

        return new OAuthRuntime(
            validator: new RequiredScopeTokenValidator($jwtValidator, $configuration->scopes),
            metadata: $metadata,
        );
    }
}
