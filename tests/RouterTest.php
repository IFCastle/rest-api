<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

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
}
