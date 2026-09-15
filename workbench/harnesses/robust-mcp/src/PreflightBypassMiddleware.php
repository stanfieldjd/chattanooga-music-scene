<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Allows CORS preflight to reach the transport without requiring credentials. */
final class PreflightBypassMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly MiddlewareInterface $inner)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ('OPTIONS' === strtoupper($request->getMethod())) {
            return $handler->handle($request);
        }

        return $this->inner->process($request, $handler);
    }
}
