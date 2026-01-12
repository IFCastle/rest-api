<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\Console\ConsoleLoggerInterface;
use IfCastle\Application\RequestEnvironment\RequestEnvironmentInterface;
use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\Http\HttpResponseMutableInterface;
use IfCastle\TypeDefinitions\ResultInterface;
use Psr\Log\LoggerInterface;

class ErrorDefaultStrategy
{
    public function __invoke(RequestEnvironmentInterface $requestEnvironment): void
    {
        $response                   = $requestEnvironment->getResponse();

        if ($response !== null) {
            return;
        }

        $response                   = $requestEnvironment->getResponseFactory()->createResponse();

        if ($response instanceof HttpResponseMutableInterface) {
            $response->setHeader(HeadersInterface::CONTENT_TYPE, 'text/plain');
            $response->setStatusCode(ResponseDefaultStrategy::SERVER_ERROR['code']);
            $response->setBody(ResponseDefaultStrategy::SERVER_ERROR['message']);
            $requestEnvironment->defineResponse($response);
        }

        $resultContainer            = $requestEnvironment->findDependency(ResultInterface::class, returnThrowable: true);

        if ($resultContainer instanceof ResultInterface && ($error = $resultContainer->getError()) !== null) {
            $requestEnvironment->findDependency(LoggerInterface::class)?->error($error);
            $requestEnvironment->findDependency(ConsoleLoggerInterface::class)?->error($error);
        } elseif ($resultContainer instanceof \Throwable) {
            $requestEnvironment->findDependency(LoggerInterface::class)?->error($resultContainer);
            $requestEnvironment->findDependency(ConsoleLoggerInterface::class)?->error($resultContainer);
        }
    }
}
