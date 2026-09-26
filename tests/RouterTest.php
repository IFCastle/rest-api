<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Protocol\Exceptions\BadRequest;
use IfCastle\Protocol\Exceptions\ParseException;
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
