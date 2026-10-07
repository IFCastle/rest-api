<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;

final class Bootloader implements BootloaderInterface
{
    #[\Override]
    public function buildBootloader(BootloaderExecutorInterface $bootloaderExecutor): void
    {
        $router                     = new Router();

        $bootloaderExecutor->getBootloaderContext()->getRequestEnvironmentPlan()
                                                   ->addDispatchHandler($router)
                                                   ->addExecuteHandler(new ServiceCallDefaultStrategy())
                                                   ->addResponseHandler(new ResponseDefaultStrategy())
                                                   ->addFinallyHandler(new ErrorDefaultStrategy());

        $context                    = $bootloaderExecutor->getBootloaderContext();
        if ($context->isWarmUpEnabled()) {
            $bootloaderExecutor->addWarmUpOperation(static function () use ($context): void {
                $environment        = $context->getSystemEnvironment()
                    ?? throw new \LogicException('Route warm-up requires the system environment');
                new RouteCollectionBuilder()($environment);
            });
        }

        $bootloaderExecutor->getBootloaderContext()->getSystemEnvironmentBootBuilder()
                                                   ->bindObject(RouterInterface::class, $router);
    }
}
