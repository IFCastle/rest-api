<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Protocol\Exceptions\HttpException;
use IfCastle\Protocol\Exceptions\ServiceUnavailable;
use IfCastle\TypeDefinitions\Result;
use IfCastle\TypeDefinitions\ResultInterface;
use IfCastle\TypeDefinitions\Value\ValueJson;

class ResponseDefaultStrategyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testResponseSuccess(): void
    {
        $requestEnvironment         = $this->buildRequestEnvironment('/base/some-method/some-string');

        $requestEnvironment->set(ResultInterface::class, new Result(result: new ValueJson(['id' => 'some-id', 'name' => 'some-name'])));

        $responseDefaultStrategy    = new ResponseDefaultStrategy();

        $responseDefaultStrategy($requestEnvironment);

        $response                   = $requestEnvironment->getResponse();

        $this->assertNotNull($response, 'Response not found');
        $this->assertEquals(200, $response->getStatusCode(), 'Status code is not equal to 200');
        $this->assertSame(['application/json; charset=utf-8'], $response->getHeader('Content-Type'), 'Content-Type is not one JSON value in UTF-8');
        $this->assertEquals('{"id":"some-id","name":"some-name"}', $response->getBody(), 'Body is not equal to {"id":"some-id","name":"some-name"}');
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testResponseError(): void
    {
        $requestEnvironment         = $this->buildRequestEnvironment('/base/some-method/some-string');

        $requestEnvironment->set(ResultInterface::class, new Result(error: new \Exception('Some error')));

        $responseDefaultStrategy    = new ResponseDefaultStrategy();

        $responseDefaultStrategy($requestEnvironment);

        $response                   = $requestEnvironment->getResponse();

        $this->assertNotNull($response, 'Response not found');
        $this->assertEquals(500, $response->getStatusCode(), 'Status code is not equal to 500');
        $this->assertSame(['application/json; charset=utf-8'], $response->getHeader('Content-Type'), 'Content-Type is not one JSON value in UTF-8');
        $this->assertEquals('{"message":"Internal server error","code":500}', $response->getBody(), 'Body is not equal to {"message":"Internal server error","code":500}');
    }

    /**
     * A service that returns a failed Result reaches the error response through the success path,
     * which has already set Content-Type once.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testFailedResultOfAServiceKeepsOneContentType(): void
    {
        $requestEnvironment         = $this->buildRequestEnvironment('/base/some-method/some-string');

        $requestEnvironment->set(
            ResultInterface::class, new Result(result: new Result(error: new HttpException('gone', 404)))
        );

        new ResponseDefaultStrategy()($requestEnvironment);

        $response                   = $requestEnvironment->getResponse();

        $this->assertSame(404, $response?->getStatusCode());
        $this->assertSame(['application/json; charset=utf-8'], $response->getHeader('Content-Type'));
    }

    /**
     * An HTTP error without a reason phrase of its own leaves the phrase to the server, which
     * knows the standard one for the status: "Internal server error" on a 404 is wrong.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testHttpErrorWithoutAReasonPhraseSetsNone(): void
    {
        $requestEnvironment         = $this->buildRequestEnvironment('/base/some-method/some-string');

        $requestEnvironment->set(ResultInterface::class, new Result(error: new HttpException('gone', 404)));

        new ResponseDefaultStrategy()($requestEnvironment);

        $response                   = $requestEnvironment->getResponse();

        $this->assertSame(404, $response?->getStatusCode());
        $this->assertSame('', $response->getReasonPhrase());
    }

    /** @return array<string, array{\Throwable, int, list<string>}> an error, its status and its Retry-After values */
    public static function retryDelays(): array
    {
        return [
            'a 503 with a retry delay'      => [new ServiceUnavailable(retryAfter: 3), 503, ['3']],
            'a 503 without a retry delay'   => [new ServiceUnavailable(), 503, []],
            'not a 503'                     => [new \Exception('Some error'), 500, []],
        ];
    }

    /** @param list<string> $retryAfterHeader */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    #[\PHPUnit\Framework\Attributes\DataProvider('retryDelays')]
    public function testServiceUnavailableNamesItsRetryDelayInAHeader(
        \Throwable $error,
        int $status,
        array $retryAfterHeader
    ): void {
        $requestEnvironment         = $this->buildRequestEnvironment('/base/some-method/some-string');
        $requestEnvironment->set(ResultInterface::class, new Result(error: $error));

        new ResponseDefaultStrategy()($requestEnvironment);

        $response                   = $requestEnvironment->getResponse();
        $this->assertSame($status, $response?->getStatusCode());
        $this->assertSame($retryAfterHeader, $response->getHeader('Retry-After'));
    }
}
