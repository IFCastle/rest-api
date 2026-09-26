<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\Http\HttpResponseInterface;
use PHPUnit\Framework\TestCase;

class HttpPipelineTest extends TestCase
{
    private HttpPipeline|null $pipeline = null;

    #[\Override]
    protected function setUp(): void
    {
        $this->pipeline             = new HttpPipeline();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->pipeline?->dispose();
        $this->pipeline             = null;
    }

    public function testRequestReachesServiceAndReturnsJson(): void
    {
        $response                   = $this->pipeline->handle(TestHttpRequest::get('http://localhost/pipeline/echo/abc/0'));

        $this->assertSame([], $this->pipeline->logRecords());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains(HeadersInterface::MIME_APPLICATION_JSON, $response->getHeader(HeadersInterface::CONTENT_TYPE));
        $this->assertSame(['id' => 'abc'], $this->decode($response));
    }

    public function testConcurrentRequestsGetTheirOwnResponses(): void
    {
        // The first request waits longest, so all three are inside the pipeline at once.
        $responses                  = $this->pipeline->handleConcurrently(
            TestHttpRequest::get('http://localhost/pipeline/echo/first/30'),
            TestHttpRequest::get('http://localhost/pipeline/echo/second/10'),
            TestHttpRequest::get('http://localhost/pipeline/echo/third/0'),
        );

        $this->assertSame(
            [['id' => 'first'], ['id' => 'second'], ['id' => 'third']],
            \array_map($this->decode(...), $responses)
        );
    }

    /**
     * @return array<mixed>
     */
    private function decode(HttpResponseInterface $response): array
    {
        $body                       = $response->getBody();
        $this->assertIsString($body);

        return \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
