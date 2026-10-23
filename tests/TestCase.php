<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\Environment\SystemEnvironment;
use IfCastle\Application\Environment\SystemEnvironmentInterface;
use IfCastle\Application\RequestEnvironment\RequestEnvironment;
use IfCastle\DI\Resolver;
use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\Http\HttpRequestInterface;
use IfCastle\Protocol\ResponseFactoryInterface;
use IfCastle\RestApi\Mocks\HttpResponse;
use IfCastle\ServiceManager\DescriptorRepository;
use IfCastle\ServiceManager\RepositoryStorages\RepositoryReaderInterface;
use IfCastle\ServiceManager\ServiceDescriptorBuilderByReflection;
use IfCastle\ServiceManager\ServiceLocator;
use IfCastle\ServiceManager\ServiceLocatorInterface;
use IfCastle\TypeDefinitions\Resolver\ExplicitTypeResolver;
use Psr\Http\Message\UriInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected SystemEnvironmentInterface|null $systemEnvironment = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->systemEnvironment?->dispose();
        $this->systemEnvironment = null;
    }

    protected function assignResponseFactory(RequestEnvironment $requestEnvironment): void
    {
        $responseFactory            = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn(new HttpResponse());
        $requestEnvironment->set(ResponseFactoryInterface::class, $responseFactory);
    }

    protected function buildRequestEnvironment(
        string $url,
        string $method              = 'GET',
        string $contentType         = 'application/json',
        string $body                = ''
    ): RequestEnvironment {
        $systemEnvironment          = $this->buildSystemEnvironment();
        $this->systemEnvironment    = $systemEnvironment;

        $httpRequest                = $this->createMock(HttpRequestInterface::class);
        $httpRequest->method('getMethod')->willReturn($method);

        $uri                        = $this->createMock(UriInterface::class);

        $uri->method('getPath')->willReturn($url);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getScheme')->willReturn('http');
        $uri->method('getPort')->willReturn(80);
        $uri->method('getQuery')->willReturn('some-query');

        $httpRequest->method('getUri')->willReturn($uri);

        $httpRequest->method('getHeader')->with(HeadersInterface::CONTENT_TYPE)->willReturn([$contentType]);
        $httpRequest->method('getBody')->willReturn($body);
        $httpRequest->method('getBodySize')->willReturn(\strlen($body));


        $env                        = new RequestEnvironment($httpRequest, parentContainer: $systemEnvironment);
        $env->set(HttpRequestInterface::class, $httpRequest);

        $this->assignResponseFactory($env);

        return $env;
    }

    /**
     * @param string|array<string, string> $serviceClasses one service class, registered as someService, or classes by
     *     service name
     */
    protected function buildSystemEnvironment(string|array $serviceClasses = SomeService::class): SystemEnvironmentInterface
    {
        $serviceClasses             = \is_array($serviceClasses) ? $serviceClasses : ['someService' => $serviceClasses];
        $serviceConfigs             = \array_map(
            static fn(string $class): array => ['class' => $class, 'isActive' => true],
            $serviceClasses
        );

        $repositoryReader           = $this->createMock(RepositoryReaderInterface::class);
        $repositoryReader->method('getServicesConfig')->willReturn($serviceConfigs);
        $repositoryReader->method('findServiceConfig')->willReturnCallback(
            static fn(string $serviceName): ?array => $serviceConfigs[$serviceName] ?? null
        );

        $container                  = [];
        $descriptorRepository       = new DescriptorRepository(
            $repositoryReader,
            new ExplicitTypeResolver(),
            new ServiceDescriptorBuilderByReflection()
        );

        $container[DescriptorRepository::class] = $descriptorRepository;
        $container[ServiceLocatorInterface::class] = new ServiceLocator($descriptorRepository);

        return new SystemEnvironment(new Resolver(), $container);
    }
}
