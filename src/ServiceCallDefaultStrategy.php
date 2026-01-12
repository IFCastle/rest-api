<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\RequestEnvironment\RequestEnvironmentInterface;
use IfCastle\Async\ReadableStreamInterface;
use IfCastle\DI\Exceptions\DependencyNotFound;
use IfCastle\Exceptions\UnexpectedValueType;
use IfCastle\ServiceManager\CommandDescriptorInterface;
use IfCastle\ServiceManager\ExecutorInterface;
use IfCastle\ServiceManager\ServiceLocatorInterface;
use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\Result;
use IfCastle\TypeDefinitions\ResultInterface;
use IfCastle\TypeDefinitions\Value\ValueContainer;
use IfCastle\TypeDefinitions\Value\ValueContainerInterface;

final class ServiceCallDefaultStrategy
{
    /**
     * @throws DependencyNotFound
     * @throws UnexpectedValueType
     */
    public function __invoke(RequestEnvironmentInterface $requestEnvironment): void
    {
        $commandDescriptor          = $requestEnvironment->findDependency(CommandDescriptorInterface::class);

        if ($commandDescriptor instanceof CommandDescriptorInterface === false) {
            return;
        }

        $executor                   = $requestEnvironment->resolveDependency(ExecutorInterface::class);

        if ($executor instanceof ExecutorInterface === false) {
            throw new UnexpectedValueType('$executor', $executor, ExecutorInterface::class);
        }

        try {
            $result                 = $executor->executeCommand($commandDescriptor);
        } catch (\Throwable $exception) {
            $requestEnvironment->set(ResultInterface::class, new Result(error: $exception));
            return;
        }

        //
        // The Result can be a ReadableStreamInterface,
        //
        if ($result instanceof ReadableStreamInterface) {
            $requestEnvironment->set(ResultInterface::class, new Result(result: $result));
            return;
        }

        //
        // otherwise, it should be a ValueContainerInterface
        //
        if (false === $result instanceof ValueContainerInterface) {
            $result                 = new ValueContainer($result, $this->extractReturnType($requestEnvironment, $commandDescriptor));
        }

        $requestEnvironment->set(ResultInterface::class, new Result($result));
    }

    private function extractReturnType(RequestEnvironmentInterface $requestEnvironment, CommandDescriptorInterface $commandDescriptor): DefinitionInterface
    {
        $serviceLocator             = $requestEnvironment->resolveDependency(ServiceLocatorInterface::class);

        if ($serviceLocator instanceof ServiceLocatorInterface === false) {
            throw new UnexpectedValueType('$serviceLocator', $serviceLocator, ServiceLocatorInterface::class);
        }

        return $serviceLocator->getServiceDescriptor($commandDescriptor->getServiceName())
                              ->getServiceMethod($commandDescriptor->getMethodName())
                              ->getReturnType();
    }
}
