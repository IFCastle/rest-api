<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Mocks;

use IfCastle\Async\ReadableStreamInterface;
use IfCastle\Protocol\HeadersMutableTrait;
use IfCastle\Protocol\Http\HttpResponseMutableInterface;

class HttpResponse implements HttpResponseMutableInterface
{
    use HeadersMutableTrait;

    protected int $statusCode = 200;

    protected string $reasonPhrase = 'OK';

    protected string|ReadableStreamInterface $body = '';

    #[\Override]
    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;

        return $this;
    }

    #[\Override]
    public function setReasonPhrase(string $reason): static
    {
        $this->reasonPhrase = $reason;

        return $this;
    }

    #[\Override]
    public function setBody(string|ReadableStreamInterface $body): static
    {
        $this->body = $body;

        return $this;
    }

    #[\Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    #[\Override]
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    #[\Override]
    public function getBody(): string|ReadableStreamInterface
    {
        return $this->body;
    }

    #[\Override]
    public function getProtocolName(): string
    {
        return 'HTTP';
    }

    #[\Override]
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    #[\Override]
    public function getProtocolRole(): string
    {
        return 'server';
    }
}
