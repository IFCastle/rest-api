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

    public function testUnknownRouteAnswers404(): void
    {
        $response                   = $this->pipeline->handle(TestHttpRequest::get('http://localhost/pipeline/missing'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(404, $this->decode($response)['status'] ?? null);
        $this->assertSame([], $this->pipeline->logRecords());
    }

    public function testWrongMethodAnswers405(): void
    {
        $response                   = $this->pipeline->handle(
            new TestHttpRequest('POST', 'http://localhost/pipeline/echo/abc/0')
        );

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame(['GET'], $response->getHeader('Allow'));
        $this->assertSame([], $this->pipeline->logRecords());
    }

    public function testGetWithoutContentTypeReachesService(): void
    {
        $response                   = $this->pipeline->handle(
            new TestHttpRequest('GET', 'http://localhost/pipeline/echo/abc/0')
        );

        $this->assertSame([], $this->pipeline->logRecords());
        $this->assertSame(['id' => 'abc'], $this->decode($response));
    }

    public function testJsonBodyWithMediaTypeParametersIsParsed(): void
    {
        $response                   = $this->pipeline->handle(new TestHttpRequest(
            'POST',
            'http://localhost/pipeline/sum',
            [HeadersInterface::CONTENT_TYPE => ['application/json; charset=utf-8']],
            '{"a": 2, "b": 3}'
        ));

        $this->assertSame([], $this->pipeline->logRecords());
        $this->assertSame('5', $response->getBody());
    }

    public function testJsonBodyThatIsNotAnObjectAnswers400(): void
    {
        $response                   = $this->pipeline->handle(new TestHttpRequest(
            'POST',
            'http://localhost/pipeline/sum',
            [HeadersInterface::CONTENT_TYPE => [HeadersInterface::MIME_APPLICATION_JSON]],
            '42'
        ));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([], $this->pipeline->logRecords());
    }

    public function testEmptyBodyHasNoParametersWhateverItsContentType(): void
    {
        $response                   = $this->pipeline->handle(new TestHttpRequest(
            'GET', 'http://localhost/pipeline/echo/abc/0', [HeadersInterface::CONTENT_TYPE => ['text/plain']]
        ));

        $this->assertSame([], $this->pipeline->logRecords());
        $this->assertSame(['id' => 'abc'], $this->decode($response));
    }

    public function testJsonResponseHasOneContentTypeValue(): void
    {
        $response                   = $this->pipeline->handle(TestHttpRequest::get('http://localhost/pipeline/echo/abc/0'));

        $this->assertSame(['application/json; charset=utf-8'], $response->getHeader(HeadersInterface::CONTENT_TYPE));
    }

    public function testScalarResultIsSentAsJson(): void
    {
        $response                   = $this->pipeline->handle(TestHttpRequest::get('http://localhost/pipeline/text/hello'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('"hello"', $response->getBody());
    }

    public function testServiceErrorAnswers500AndIsLogged(): void
    {
        $response                   = $this->pipeline->handle(TestHttpRequest::get('http://localhost/pipeline/fail'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertCount(1, $this->pipeline->logRecords());
        $this->assertStringContainsString(PipelineService::FAILURE, $this->pipeline->logRecords()[0]);
    }

    public function testClientVisibleErrorWithoutHttpStatusIsLogged(): void
    {
        $response                   = $this->pipeline->handle(TestHttpRequest::get('http://localhost/pipeline/fail-visibly'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertCount(1, $this->pipeline->logRecords());
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
