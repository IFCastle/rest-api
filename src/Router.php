<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\RequestEnvironment\RequestEnvironmentInterface;
use IfCastle\DesignPatterns\ExecutionPlan\StagePointer;
use IfCastle\DesignPatterns\Handler\WeakStaticHandler;
use IfCastle\DesignPatterns\Interceptor\InterceptorInterface;
use IfCastle\DesignPatterns\Interceptor\InterceptorPipeline;
use IfCastle\DesignPatterns\Interceptor\InterceptorRegistryInterface;
use IfCastle\DI\Exceptions\DependencyNotFound;
use IfCastle\Exceptions\UnexpectedValueType;
use IfCastle\Protocol\Exceptions\ParseException;
use IfCastle\Protocol\HeadersInterface;
use IfCastle\Protocol\Http\HttpRequestForm;
use IfCastle\Protocol\Http\HttpRequestInterface;
use IfCastle\ServiceManager\CommandDescriptorInterface;
use IfCastle\ServiceManager\ServiceLocatorInterface;
use IfCastle\TypeDefinitions\FromEnv;
use IfCastle\TypeDefinitions\FunctionDescriptorInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\RequestContext;

class Router implements RouterInterface
{
    protected CompiledRouteCollection|null $routeCollection = null;

    /**
     * @var InterceptorInterface<object>[]|null
     */
    protected array|null $interceptors = null;

    public function __invoke(RequestEnvironmentInterface $requestEnvironment): StagePointer|null
    {
        if ($requestEnvironment->hasDependency(CommandDescriptorInterface::class)) {
            return new StagePointer(breakCurrent: true);
        }

        $httpRequest                = $requestEnvironment->findDependency(HttpRequestInterface::class);

        if ($httpRequest === null) {
            return null;
        }

        if ($this->routeCollection === null) {
            $this->buildRouteCollection($requestEnvironment);
        }

        $attributes                 = (new CompiledUrlMatcher($this->routeCollection->collection, $this->defineRequestContext($httpRequest)))
            ->match($httpRequest->getUri()->getPath());

        if (empty($attributes['_service']) || empty($attributes['_method'])) {
            return null;
        }

        $requestEnvironment->set(CommandDescriptorInterface::class, new CommandDescriptor(
            serviceName: $attributes['_service'],
            methodName: $attributes['_method'],
            extractParameters: new WeakStaticHandler(static fn(self $self) => $self->extractParameters($requestEnvironment, $attributes), $this)
        ));

        return new StagePointer(breakCurrent: true);
    }

    protected function buildRouteCollection(RequestEnvironmentInterface $requestEnvironment): void
    {
        $routerCollection           = $requestEnvironment->findDependency(CompiledRouteCollection::class);

        if ($routerCollection instanceof CompiledRouteCollection) {
            $this->routeCollection  = $routerCollection;
            return;
        }

        $builder                    = new RouteCollectionBuilder();
        $builder($requestEnvironment->getSystemEnvironment());

        $this->routeCollection      = $requestEnvironment->resolveDependency(CompiledRouteCollection::class);
    }

    protected function defineRequestContext(HttpRequestInterface $httpRequest): RequestContext
    {
        $uri                        = $httpRequest->getUri();

        return new RequestContext(
            baseUrl: $uri->getPath(),
            method: $httpRequest->getMethod(),
            host: $uri->getHost(),
            scheme: $uri->getScheme(),
            httpPort: $uri->getPort(),
            queryString: $uri->getQuery()
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     *
     * @throws ParseException
     * @throws DependencyNotFound
     * @throws UnexpectedValueType
     */
    protected function extractParameters(RequestEnvironmentInterface $requestEnvironment, array $attributes): array
    {
        $serviceName               = $attributes['_service'] ?? '';
        $methodName                = $attributes['_method'] ?? '';

        $httpRequest               = $requestEnvironment->resolveDependency(HttpRequestInterface::class);

        if (false === $httpRequest instanceof HttpRequestInterface) {
            throw new UnexpectedValueType('$httpRequest', $httpRequest, HttpRequestInterface::class);
        }

        if ($this->interceptors === null) {
            $this->buildInterceptors($requestEnvironment);
        }

        $methodDescriptor          = $requestEnvironment->findDependency(ServiceLocatorInterface::class)
                                                        ->getServiceDescriptor($serviceName)
                                                        ->getServiceMethod($methodName);

        if (false === $methodDescriptor instanceof FunctionDescriptorInterface) {
            throw new UnexpectedValueType('$methodDescriptor', $methodDescriptor, FunctionDescriptorInterface::class);
        }

        //
        // Before we parse the request parameters,
        // we need to determine whether the called method requires the raw request data.
        // If it does, we won’t do anything!
        //

        $requestParameter           = null;
        $requestBody                = null;

        foreach ($methodDescriptor->getArguments() as $parameter) {
            if ($parameter->getTypeName() === HttpRequestInterface::class) {
                $requestParameter   = $parameter->getName();
                break;
            }

            if (($requestBody = $parameter->findAttribute(RequestBody::class)) !== null) {
                break;
            }
        }

        if ($requestParameter !== null) {
            return [$requestParameter => $httpRequest];
        }

        $contentType                = $httpRequest->getHeader(HeadersInterface::CONTENT_TYPE)[0] ?? '';

        if ($requestBody instanceof RequestBody) {
            // validate content type
            if ($requestBody->mimeTypes !== []
               && false === \in_array($contentType, $requestBody->mimeTypes, true)) {
                throw new ParseException([
                    'template'      => 'Invalid content type "{contentType}" for {service}->{method}. Expected: {expected}',
                    'contentType'   => $httpRequest->getHeader(HeadersInterface::CONTENT_TYPE)[0] ?? '',
                    'service'       => $serviceName,
                    'method'        => $methodName,
                    'expected'      => \implode(', ', $requestBody->mimeTypes),
                ]);
            }

            if (\in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true)) {
                // Parse form data
                $parameters         = $this->parseParameters($httpRequest);
            } else {
                // Using only gets parameters from URL
                $parameters         = $httpRequest->getRequestParameters();
            }
        } else {
            $parameters             = $this->parseParameters($httpRequest);
        }

        $normalizedParameters       = [];

        foreach ($methodDescriptor->getArguments() as $parameter) {
            $name                   = $parameter->getName();

            //
            // Apply interceptors if exists
            //
            if ($this->interceptors !== []) {
                $result             = (new InterceptorPipeline(
                    $this, [$parameter, $methodDescriptor, $parameters, $attributes, $requestEnvironment], ...$this->interceptors)
                )->getResult();

                if ($result !== null) {
                    $normalizedParameters[$name] = $result;
                    continue;
                }
            }

            if ($parameter->findAttribute(FromEnv::class) !== null) {
                continue;
            }

            if ($parameter->findAttribute(RequestBody::class) !== null) {
                $normalizedParameters[$name] = $httpRequest->getBody();
                continue;
            }

            if (($fromHeader = $parameter->findAttribute(FromHeader::class)) !== null) {
                $normalizedParameters[$name] = $httpRequest->getHeader($fromHeader->name ?? $name);
                continue;
            }

            if (($fromGet = $parameter->findAttribute(FromGet::class)) !== null) {
                $normalizedParameters[$name] = $httpRequest->getRequestParameter($fromGet->name ?? $name);
                continue;
            }

            // Resolve parameter by type
            $result                 = match ($parameter->getTypeName()) {
                UriInterface::class => $httpRequest->getUri(),
                HttpRequestInterface::class, HeadersInterface::class => $httpRequest,
                HttpRequestForm::class => $httpRequest->retrieveRequestForm(),
                default             => null,
            };

            if ($result !== null) {
                $normalizedParameters[$name] = $result;
                continue;
            }

            // First, we check if the parameter is in the router parameters
            if (\array_key_exists($name, $attributes)) {
                $normalizedParameters[$name] = $attributes[$name];
            } elseif (\array_key_exists($name, $parameters)) {
                // Then we check if the parameter is in the request parameters
                $normalizedParameters[$name] = $parameters[$name];
            }
        }

        return $normalizedParameters;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ParseException
     */
    protected function parseParameters(HttpRequestInterface $httpRequest): array
    {
        // Try to parse parameters from the request
        $contentType                = $httpRequest->getHeader(HeadersInterface::CONTENT_TYPE)[0] ?? '';

        if ($contentType === 'application/json') {

            $body                   = $httpRequest->getBody();

            if ($body === '') {
                return [];
            }

            try {
                $parameters         = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new ParseException('Failed to parse JSON request body', 0, $exception);
            }

            return $parameters;
        }

        if (\in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data', true])) {

            $form                   = $httpRequest->retrieveRequestForm();

            if ($form === null) {
                throw new ParseException('Failed to parse form data: no form data found');
            }

            $json                   = $form->post['json'] ?? '';
            $parameters             = [];

            if ($json !== '') {
                try {
                    $parameters     = \json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw new ParseException('Failed to parse JSON parameter', 0, $exception);
                }
            }

            // Mix files to json parameters
            return \array_merge($parameters, $form->files);
        }

        throw new ParseException('Failed to parse request parameters: unknown content type');
    }

    protected function buildInterceptors(RequestEnvironmentInterface $requestEnvironment): void
    {
        $this->interceptors      = [];

        $interceptors               = $requestEnvironment->getSystemEnvironment()->findDependency(InterceptorRegistryInterface::class)
                                                        ?->resolveInterceptors(ExtractParameterInterface::class);

        if ($interceptors !== null) {
            $this->interceptors  = $interceptors;
        }
    }
}
