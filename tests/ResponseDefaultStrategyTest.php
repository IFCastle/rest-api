<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

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
        $this->assertEquals('application/json', $response->getHeader('Content-Type')[0], 'Content-Type is not equal to application/json');
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
        $this->assertEquals('application/json', $response->getHeader('Content-Type')[0], 'Content-Type is not equal to application/json');
        $this->assertEquals('{"message":"Internal server error","code":500}', $response->getBody(), 'Body is not equal to {"message":"Internal server error","code":500}');
    }
}
