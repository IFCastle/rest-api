<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Protocol\Exceptions\BadRequest;
use IfCastle\Protocol\Exceptions\ParseException;
use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\Http\HttpRequestForm;
use IfCastle\Protocol\Http\HttpRequestInterface;
use IfCastle\ServiceManager\CommandDescriptorInterface;

class RouterTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testRouter(): void
    {
        $requestEnvironment         = $this->buildRequestEnvironment('/base/some-method/some-string');
        $routerDefaultStrategy      = new Router();

        $routerDefaultStrategy($requestEnvironment);

        $command                    = $requestEnvironment->findDependency(CommandDescriptorInterface::class);

        $this->assertNotNull($command, 'Command not found');
        $this->assertEquals('someService', $command->getServiceName(), 'Service name is not equal to someService');
        $this->assertEquals('someMethod', $command->getMethodName(), 'Method name is not equal to someMethod');
        $this->assertEquals(['id' => 'some-string'], $command->getParameters(), 'Parameters are not equal');
    }

    /**
     * A server that parses a form keeps its parts rather than its bytes, so the raw body is empty
     * and its size 0 (TrueAsync\HttpServer, or a chunked upload with no Content-Length).
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testFormParametersDoNotDependOnTheRawBody(): void
    {
        $requestEnvironment         = $this->buildRequestEnvironment(
            '/base/method-with-uuid/0b8f3c1e-2d4a-4f6b-9c7d-1e2f3a4b5c6d',
            'GET',
            HeadersInterface::MIME_MULTIPART_FORM_DATA . '; boundary=b'
        );

        $httpRequest                = $requestEnvironment->findDependency(HttpRequestInterface::class);
        $httpRequest->method('retrieveRequestForm')
                    ->willReturn(new HttpRequestForm(post: ['json' => '{"extraParameter":"from the form"}']));

        $router                     = new Router();
        $router($requestEnvironment);
        $command                    = $requestEnvironment->findDependency(CommandDescriptorInterface::class);

        $this->assertSame('from the form', $command->getParameters()['extraParameter'] ?? null);
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testUnknownContentTypeIsRejected(): void
    {
        $requestEnvironment         = $this->buildRequestEnvironment(
            '/base/some-method/some-string', 'GET', 'text/plain', 'plain text'
        );

        // CommandDescriptor holds the router weakly, so the router must outlive getParameters().
        $router                     = new Router();
        $router($requestEnvironment);
        $command                    = $requestEnvironment->findDependency(CommandDescriptorInterface::class);

        try {
            $command->getParameters();
            $this->fail('BadRequest was not thrown');
        } catch (BadRequest $exception) {
            $this->assertStringContainsString('unknown content type', (string) $exception->getDetail());
            $this->assertInstanceOf(ParseException::class, $exception->getPrevious());
        }
    }
}
