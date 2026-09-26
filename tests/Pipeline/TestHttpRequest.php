<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use IfCastle\Async\ReadableStreamInterface;
use IfCastle\Protocol\FileContainerInterface;
use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\HeadersTrait;
use IfCastle\Protocol\Http\HttpRequestForm;
use IfCastle\Protocol\Http\HttpRequestInterface;
use League\Uri\Http;
use Psr\Http\Message\UriInterface;

/**
 * An HTTP request built in memory: request parameters come from the query string,
 * the body is a plain string, and there are no cookies, forms or uploaded files.
 */
final class TestHttpRequest implements HttpRequestInterface
{
    use HeadersTrait;

    private readonly UriInterface $uri;

    /**
     * @var array<string, mixed>
     */
    private readonly array $parameters;

    /**
     * @param array<string, string[]> $headers
     */
    public function __construct(
        private readonly string $method,
        string                  $uri,
        array                   $headers    = [],
        private readonly string $body       = ''
    ) {
        $this->uri                  = Http::new($uri);
        $this->headers              = $headers;

        \parse_str($this->uri->getQuery(), $parameters);
        $this->parameters           = $parameters;
    }

    /**
     * GET with a JSON content type and an empty body, the form the Router parses today.
     */
    public static function get(string $uri): self
    {
        return new self('GET', $uri, [HeadersInterface::CONTENT_TYPE => [HeadersInterface::MIME_APPLICATION_JSON]]);
    }

    #[\Override]
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    #[\Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[\Override]
    public function getCookies(): array
    {
        return [];
    }

    #[\Override]
    public function getBodySize(): int
    {
        return \strlen($this->body);
    }

    #[\Override]
    public function getBody(): string
    {
        return $this->body;
    }

    #[\Override]
    public function getBodyStream(): ?ReadableStreamInterface
    {
        return null;
    }

    #[\Override]
    public function retrieveRequestForm(): HttpRequestForm|null
    {
        return null;
    }

    #[\Override]
    public function getRequestParameters(): array
    {
        return $this->parameters;
    }

    #[\Override]
    public function getRequestParameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    #[\Override]
    public function requestParameters(string ...$names): array
    {
        return \array_intersect_key($this->parameters, \array_flip($names));
    }

    #[\Override]
    public function requestParametersWithNull(string ...$names): array
    {
        $result                     = [];

        foreach ($names as $name) {
            $result[$name]          = $this->parameters[$name] ?? null;
        }

        return $result;
    }

    #[\Override]
    public function isRequestParametersExist(string ...$names): bool
    {
        foreach ($names as $name) {
            if (false === \array_key_exists($name, $this->parameters)) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function isRequestParametersDefined(string ...$names): bool
    {
        foreach ($names as $name) {
            if (($this->parameters[$name] ?? null) === null) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function getUploadedFiles(): array
    {
        return [];
    }

    #[\Override]
    public function getUploadedFile(string $name): ?FileContainerInterface
    {
        return null;
    }

    #[\Override]
    public function hasUploadedFile(string $name): bool
    {
        return false;
    }
}
