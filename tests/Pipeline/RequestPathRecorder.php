<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use IfCastle\Application\RequestEnvironment\RequestEnvironmentInterface;
use IfCastle\Protocol\Http\HttpRequestInterface;
use IfCastle\Protocol\Http\HttpResponseMutableInterface;

/**
 * Stores the request path in the request environment before the service runs, and after the
 * response copies the path of the environment that SystemEnvironment::getRequestEnvironment()
 * returns into the FOUND_HEADER response header. The two paths differ when concurrent requests
 * share one environment slot.
 */
final class RequestPathRecorder
{
    public const string KEY         = 'requestPath';

    public const string FOUND_HEADER = 'X-Found-Request-Path';

    public function record(RequestEnvironmentInterface $requestEnvironment): void
    {
        $httpRequest                = $requestEnvironment->resolveDependency(HttpRequestInterface::class);

        if ($httpRequest instanceof HttpRequestInterface) {
            $requestEnvironment->set(self::KEY, $httpRequest->getUri()->getPath());
        }
    }

    public function probe(RequestEnvironmentInterface $requestEnvironment): void
    {
        $found                      = $requestEnvironment->getSystemEnvironment()->getRequestEnvironment();
        $response                   = $requestEnvironment->getResponse();

        if ($response instanceof HttpResponseMutableInterface) {
            $response->setHeader(self::FOUND_HEADER, (string) $found?->findDependency(self::KEY));
        }
    }
}
