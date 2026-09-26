<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\Console\ConsoleLoggerInterface;
use IfCastle\Application\RequestEnvironment\RequestEnvironmentInterface;
use IfCastle\Protocol\Exceptions\HttpErrorInterface;
use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\Http\HttpResponseMutableInterface;
use IfCastle\TypeDefinitions\ResultInterface;
use Psr\Log\LoggerInterface;

/**
 * The FINALLY stage of rest-api: logs the request's error unless it carries an HTTP status below 500,
 * and answers 500 when no earlier stage defined a response.
 */
class ErrorDefaultStrategy
{
    public function __invoke(RequestEnvironmentInterface $requestEnvironment): void
    {
        $this->logServerError($requestEnvironment);

        if ($requestEnvironment->getResponse() !== null) {
            return;
        }

        $response                   = $requestEnvironment->getResponseFactory()->createResponse();

        if ($response instanceof HttpResponseMutableInterface) {
            $response->setHeader(HeadersInterface::CONTENT_TYPE, 'text/plain');
            $response->setStatusCode(ResponseDefaultStrategy::SERVER_ERROR['code']);
            $response->setBody(ResponseDefaultStrategy::SERVER_ERROR['message']);
            $requestEnvironment->defineResponse($response);
        }
    }

    private function logServerError(RequestEnvironmentInterface $requestEnvironment): void
    {
        $resultContainer            = $requestEnvironment->findDependency(ResultInterface::class, returnThrowable: true);

        $error                      = match (true) {
            $resultContainer instanceof ResultInterface => $resultContainer->getError(),
            $resultContainer instanceof \Throwable       => $resultContainer,
            default                                     => null,
        };

        // An error without an HTTP status is rendered as 500, so it is the server's fault too.
        if ($error === null || ($error instanceof HttpErrorInterface && $error->getStatusCode() < 500)) {
            return;
        }

        $requestEnvironment->findDependency(LoggerInterface::class)?->error($error);
        $requestEnvironment->findDependency(ConsoleLoggerInterface::class)?->error($error);
    }
}
