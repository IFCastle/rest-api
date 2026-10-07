<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\Bootloader\BootloaderExecutor;
use IfCastle\Application\Environment\SystemEnvironmentInterface;
use IfCastle\DI\ConfigMutable;
use IfCastle\ServiceManager\ServiceLocatorInterface;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\RequestContext;

class BootloaderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testWarmUpCompilesRoutesAfterTheEnvironmentIsBuilt(): void
    {
        $services = $this->buildSystemEnvironment();
        $executor = new BootloaderExecutor(new ConfigMutable(), 'server');
        $context = $executor->getBootloaderContext();
        $context->enabledWarmUp();
        $context->getSystemEnvironmentBootBuilder()->bindObject(
            ServiceLocatorInterface::class,
            $services->resolveDependency(ServiceLocatorInterface::class)
        );
        new Bootloader()->buildBootloader($executor);
        $executor->defineStartApplicationHandler(function (SystemEnvironmentInterface $environment): void {
            $this->systemEnvironment = $environment;
            $this->assertNull($environment->findDependency(CompiledRouteCollection::class));
        });

        try {
            $executor->executePlan();
            $compiled = $this->systemEnvironment->resolveDependency(CompiledRouteCollection::class);
            $this->assertInstanceOf(CompiledRouteCollection::class, $compiled);
            $route = new CompiledUrlMatcher($compiled->collection, new RequestContext())
                ->match('/base/some-method/warmed');
            $this->assertSame('someService', $route['_service']);
            $this->assertSame('warmed', $route['id']);
        } finally {
            $executor->dispose();
            $services->dispose();
        }
    }
}
