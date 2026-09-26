<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;
use IfCastle\ServiceManager\RepositoryStorages\RepositoryReaderInterface;
use Psr\Log\LoggerInterface;

/**
 * Registers PipelineService as the only service of the test application, a CollectingLogger as
 * its logger, and RequestPathRecorder in its request plan.
 */
final class PipelineBootloader implements BootloaderInterface, RepositoryReaderInterface
{
    public const string SERVICE_NAME = 'pipelineService';

    private const array SERVICES    = [
        self::SERVICE_NAME          => [
            'class'                 => PipelineService::class,
            'isActive'              => true,
        ],
    ];

    #[\Override]
    public function buildBootloader(BootloaderExecutorInterface $bootloaderExecutor): void
    {
        $bootloaderExecutor->getBootloaderContext()->getSystemEnvironmentBootBuilder()
                           ->bindObject(RepositoryReaderInterface::class, $this)
                           ->bindObject(LoggerInterface::class, new CollectingLogger());

        $recorder                   = new RequestPathRecorder();

        $bootloaderExecutor->getBootloaderContext()->getRequestEnvironmentPlan()
                           ->addBeforeHandleHandler($recorder->record(...))
                           ->addAfterResponseHandler($recorder->probe(...));
    }

    #[\Override]
    public function getServicesConfig(): array
    {
        return self::SERVICES;
    }

    #[\Override]
    public function findServiceConfig(string $serviceName): array|null
    {
        return self::SERVICES[$serviceName] ?? null;
    }
}
