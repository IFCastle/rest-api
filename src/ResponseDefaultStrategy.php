<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\RequestEnvironment\RequestEnvironmentInterface;
use IfCastle\Async\ReadableStreamInterface;
use IfCastle\Exceptions\ClientAvailableInterface;
use IfCastle\Exceptions\LogicalException;
use IfCastle\Exceptions\UnexpectedValueType;
use IfCastle\Protocol\ContentTypeAwareInterface;
use IfCastle\Protocol\Exceptions\HttpErrorInterface;
use IfCastle\Protocol\Exceptions\HttpException;
use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\Http\HttpResponseMutableInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableInterface;
use IfCastle\TypeDefinitions\ResultInterface;
use IfCastle\TypeDefinitions\Value\ContainerSerializableInterface;

class ResponseDefaultStrategy
{
    public const array SERVER_ERROR = ['message' => 'Internal server error', 'code' => 500];

    /**
     * @throws LogicalException
     * @throws UnexpectedValueType
     */
    public function __invoke(RequestEnvironmentInterface $requestEnvironment): void
    {
        $response                   = $requestEnvironment->getResponse();

        if ($response !== null) {
            return;
        }

        $resultContainer            = $requestEnvironment->findDependency(ResultInterface::class, returnThrowable: true);

        if (false === $resultContainer instanceof ResultInterface) {
            throw new LogicalException('ResultInterface is not found in RequestEnvironment or is not an instance of ResultInterface');
        }

        $result                     = $resultContainer->isError() ? $resultContainer->getError() : $resultContainer->getResult();
        $response                   = $requestEnvironment->getResponseFactory()->createResponse();

        if (false === $response instanceof HttpResponseMutableInterface) {
            throw (new UnexpectedValueType('$response', $response, HttpResponseMutableInterface::class))->markAsFatal();
        }

        if ($result instanceof \Throwable) {
            $this->buildErrorResponse($result, $response);
        } else {
            $this->buildResponseByResult($result, $response);
        }

        $requestEnvironment->defineResponse($response);
    }

    protected function buildResponseByResult(mixed $result, HttpResponseMutableInterface $response): void
    {
        if ($result instanceof ContentTypeAwareInterface) {
            $this->applyMimeType($response, $result->getContentType());
        } else {
            $this->applyMimeType($response);
        }

        $response->setStatusCode(200);

        if ($result === null) {
            return;
        }

        $result                     = $this->resolveResult($result);

        if ($result instanceof \Throwable) {
            $this->buildErrorResponse($result, $response);
            return;
        }

        if (\is_array($result)) {
            $result                 = $this->encodeResult($result);
        }

        $response->setBody($result);
    }

    /**
     * @throws HttpException
     */
    protected function resolveResult(mixed $result): mixed
    {
        if ($result instanceof ResultInterface) {

            if ($result->isOk()) {
                $result             = $result->getResult();
            } else {
                return $result->getError();
            }
        }

        if ($result instanceof ContainerSerializableInterface) {
            return $result->containerSerialize();
        }

        if ($result instanceof ArraySerializableInterface) {
            return $result->toArray();
        }

        if ($result instanceof ReadableStreamInterface) {
            return $result;
        }

        throw (new HttpException([
            'template'      => 'Response has not allowed type. Got: {type}. Expected: {expected}',
            'code'          => 500,
            'type'          => \get_debug_type($result),
            'expected'      => 'ResultInterface|ContainerSerializableInterface|ArraySerializableInterface|ReadableStreamInterface',
        ]))->markAsFatal();

    }

    /**
     * @param array<mixed> $result
     *
     * @throws \JsonException
     */
    protected function encodeResult(array $result): string
    {
        return \json_encode($result, JSON_THROW_ON_ERROR);
    }

    protected function buildErrorResponse(\Throwable $error, HttpResponseMutableInterface $response): void
    {
        if ($error instanceof HttpErrorInterface) {
            $response->setStatusCode($error->getStatusCode());
            $response->setReasonPhrase($error->getReasonPhrase() ?? self::SERVER_ERROR['message']);
        } else {
            $response->setStatusCode(500);
        }

        if ($error instanceof ClientAvailableInterface) {
            $errorContainer             = $this->errorContainer($error->clientSerialize());
        } else {
            $errorContainer             = $this->errorContainer(static::SERVER_ERROR);
        }

        $this->applyMimeType($response);

        $response->setBody($this->encodeError($errorContainer, $response));
    }

    /**
     * @param array<mixed> $error
     *
     * @return array<mixed>
     */
    protected function errorContainer(array $error): array
    {
        return $error;
    }

    /**
     * @param array<mixed> $errorContainer
     */
    protected function encodeError(array $errorContainer, HttpResponseMutableInterface $response): string
    {
        try {
            return \json_encode($errorContainer, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $response->setStatusCode(500);
            return \json_encode($this->errorContainer(['message' => 'Json encode error while output', 'code' => 501]));
        }
    }

    protected function applyMimeType(HttpResponseMutableInterface $response, ?string $mimeType = null, ?string $charset = null): void
    {
        $response->setHeader(HeadersInterface::CONTENT_TYPE, $mimeType ?? HeadersInterface::MIME_APPLICATION_JSON);

        if ($mimeType === null && $charset === null) {
            $response->setHeader(HeadersInterface::CONTENT_TYPE, 'charset=utf-8');
        } elseif ($charset !== null) {
            $response->setHeader(HeadersInterface::CONTENT_TYPE, 'charset=' . $charset);
        }
    }
}
