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
use IfCastle\Protocol\Exceptions\MethodNotAllowed;
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
            throw new UnexpectedValueType('$response', $response, HttpResponseMutableInterface::class)->markAsFatal();
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
        $isJson                     = false === $result instanceof ContentTypeAwareInterface;

        if ($isJson) {
            $this->applyMimeType($response);
        } else {
            $this->applyMimeType($response, $result->getContentType());
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

        // A scalar is a complete JSON document too: "hello" must reach the client quoted.
        if (\is_array($result) || ($isJson && ($result === null || \is_scalar($result)))) {
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

        throw new HttpException([
            'template'      => 'Response has not allowed type. Got: {type}. Expected: {expected}',
            'code'          => 500,
            'type'          => \get_debug_type($result),
            'expected'      => 'ResultInterface|ContainerSerializableInterface|ArraySerializableInterface|ReadableStreamInterface',
        ])->markAsFatal();

    }

    /**
     * @param array<mixed>|scalar|null $result
     *
     * @throws \JsonException
     */
    protected function encodeResult(array|string|int|float|bool|null $result): string
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

        // RFC 9110: a 405 lists the methods the resource accepts.
        if ($error instanceof MethodNotAllowed) {
            $response->setHeader('Allow', \implode(', ', $error->getAdditionalData()[Router::ALLOWED_METHODS] ?? []));
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

    /**
     * Sets Content-Type as one value: JSON in UTF-8 by default, or $mimeType with $charset when given.
     */
    protected function applyMimeType(HttpResponseMutableInterface $response, ?string $mimeType = null, ?string $charset = null): void
    {
        if ($mimeType === null) {
            $mimeType               = HeadersInterface::MIME_APPLICATION_JSON;
            $charset              ??= 'utf-8';
        }

        $response->setHeader(
            HeadersInterface::CONTENT_TYPE, $charset === null ? $mimeType : $mimeType . '; charset=' . $charset
        );
    }
}
