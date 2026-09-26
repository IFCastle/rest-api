<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use IfCastle\Application\ApplicationInterface;
use IfCastle\Application\Bootloader\Builder\BootloaderBuilderInMemory;
use IfCastle\Application\Environment\PublicEnvironmentInterface;
use IfCastle\Application\Environment\SystemEnvironmentInterface;
use IfCastle\Application\RequestEnvironment\RequestEnvironment;
use IfCastle\Application\RequestEnvironment\RequestPlanInterface;
use IfCastle\Application\Runner;
use IfCastle\Protocol\Http\HttpRequestInterface;
use IfCastle\Protocol\Http\HttpResponseInterface;
use IfCastle\Protocol\Http\ResponseMutable;
use IfCastle\Protocol\ResponseFactoryInterface;
use IfCastle\Protocol\ResponseInterface;
use IfCastle\RestApi\Bootloader as RestApiBootloader;
use IfCastle\TrueAsync\Bootloader as TrueAsyncBootloader;
use PHPUnit\Framework\Assert;
use Psr\Log\LoggerInterface;

use function Async\await;
use function Async\spawn;

/**
 * A booted IFCastle application with rest-api and the TrueAsync engine, and no socket.
 *
 * Each request gets its own RequestEnvironment and goes through the application's RequestPlan,
 * the way a web server engine hands it over. The application directory is a temporary
 * directory removed by dispose().
 */
final class HttpPipeline
{
    private ?Runner $runner         = null;

    private string $appDir;

    private SystemEnvironmentInterface $systemEnvironment;

    public function __construct()
    {
        $this->appDir               = \sys_get_temp_dir() . '/ifcastle-pipeline-' . \bin2hex(\random_bytes(6));
        // ApplicationAbstract refuses to start without a vendor directory.
        \mkdir($this->appDir . '/vendor', 0o777, true);

        $this->runner               = new Runner($this->appDir, 'test', PipelineApplication::class)
            ->defineBootloaderBuilder(new BootloaderBuilderInMemory($this->appDir, 'test', [], [
                TrueAsyncBootloader::class,
                RestApiBootloader::class,
                PipelineBootloader::class,
            ]));

        $application                = $this->runner->run();
        $this->failOnCriticalLog();

        Assert::assertInstanceOf(ApplicationInterface::class, $application);
        $this->systemEnvironment    = $application->getSystemEnvironment();
    }

    /**
     * @return list<string> records written to the application logger, "level: message"
     */
    public function logRecords(): array
    {
        $logger                     = $this->systemEnvironment->resolveDependency(LoggerInterface::class);
        Assert::assertInstanceOf(CollectingLogger::class, $logger);

        return $logger->records;
    }

    public function handle(HttpRequestInterface $request): HttpResponseInterface
    {
        $environment                = $this->systemEnvironment->findDependency(PublicEnvironmentInterface::class)
                                      ?? $this->systemEnvironment;

        $requestEnvironment         = new RequestEnvironment($request, $environment);
        $requestEnvironment->set(HttpRequestInterface::class, $request);
        $requestEnvironment->set(ResponseFactoryInterface::class, new class implements ResponseFactoryInterface {
            #[\Override]
            public function createResponse(
                ?string $protocolName       = null,
                ?string $protocolVersion    = null,
                ?string $protocolRole       = null
            ): ResponseInterface {
                return new ResponseMutable($protocolName, $protocolVersion, $protocolRole);
            }
        });

        try {
            $environment->setRequestEnvironment($requestEnvironment);
            $this->systemEnvironment->resolveDependency(RequestPlanInterface::class)->executePlan($requestEnvironment);
            $response               = $requestEnvironment->getResponse();
        } finally {
            $requestEnvironment->dispose();
        }

        Assert::assertInstanceOf(HttpResponseInterface::class, $response);

        return $response;
    }

    /**
     * Handles every request in its own coroutine and returns the responses in request order.
     *
     * @return list<HttpResponseInterface>
     */
    public function handleConcurrently(HttpRequestInterface ...$requests): array
    {
        $coroutines                 = [];

        foreach ($requests as $request) {
            $coroutines[]           = spawn($this->handle(...), $request);
        }

        return \array_map(static fn($coroutine) => await($coroutine), $coroutines);
    }

    public function dispose(): void
    {
        $this->runner?->dispose();
        $this->runner               = null;

        foreach (['/vendor', '/logs/critical.log', '/logs'] as $path) {
            $path                   = $this->appDir . $path;

            if (\is_dir($path)) {
                \rmdir($path);
            } elseif (\is_file($path)) {
                \unlink($path);
            }
        }

        \rmdir($this->appDir);
    }

    /**
     * Runner does not rethrow a failure after the application object exists; it writes the
     * failure to logs/critical.log instead.
     */
    private function failOnCriticalLog(): void
    {
        $criticalLog                = $this->appDir . '/logs/critical.log';

        if (\is_file($criticalLog)) {
            Assert::fail('The application failed to boot: ' . \file_get_contents($criticalLog));
        }
    }
}
