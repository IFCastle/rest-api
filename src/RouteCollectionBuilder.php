<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\DI\ContainerMutableInterface;
use IfCastle\Exceptions\LogicalException;
use IfCastle\RestApi\Rest as RouteAttribute;
use IfCastle\ServiceManager\ServiceLocatorInterface;
use IfCastle\TypeDefinitions\FunctionDescriptorInterface;
use IfCastle\TypeDefinitions\StringableInterface;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouteCompiler;

class RouteCollectionBuilder
{
    public function __invoke(ContainerMutableInterface $systemEnvironment): void
    {
        $routeCollection            = $systemEnvironment->findDependency(CompiledRouteCollection::class);

        if ($routeCollection instanceof CompiledRouteCollection) {
            return;
        }

        $systemEnvironment->set(
            CompiledRouteCollection::class, $this->compile(
                $this->buildRouteCollection($systemEnvironment->resolveDependency(ServiceLocatorInterface::class))
            )
        );
    }

    protected function compile(RouteCollection $routeCollection): CompiledRouteCollection
    {
        return new CompiledRouteCollection(new CompiledUrlMatcherDumper($routeCollection)->getCompiledRoutes());
    }

    protected function buildRouteCollection(ServiceLocatorInterface $serviceLocator): RouteCollection
    {
        $routeCollection            = new RouteCollection();

        foreach ($serviceLocator->getServiceDescriptorList() as $serviceName => $serviceDescriptor) {
            try {
                $groupRoute         = $serviceDescriptor->findAttribute(RouteAttribute::class);

                foreach ($serviceDescriptor->getServiceMethods() as $methodDescriptor) {
                    $routeAttribute = $methodDescriptor->findAttribute(RouteAttribute::class);

                    if ($routeAttribute instanceof RouteAttribute === false) {
                        continue;
                    }

                    $routeCollection->add(
                        $methodDescriptor->getName(),
                        $this->defineRoute($routeAttribute, $groupRoute, $methodDescriptor, $serviceName)
                    );
                }

            } catch (\Throwable) {
                // ignore
            }
        }

        return $routeCollection;
    }

    /**
     * @throws LogicalException
     */
    protected function defineRoute(
        RouteAttribute $routeAttribute,
        RouteAttribute|null $groupRoute,
        FunctionDescriptorInterface $methodDescriptor,
        string $serviceName
    ): Route {
        $path                       = $routeAttribute->path;
        $defaults                   = $routeAttribute->defaults;
        $requirements               = $routeAttribute->requirements;
        $options                    = $routeAttribute->options;
        $host                       = $routeAttribute->host;
        $schemes                    = $routeAttribute->schemes;
        $methods                    = $routeAttribute->methods;
        $condition                  = $routeAttribute->condition;

        // Inherit group route attributes
        if ($groupRoute instanceof RouteAttribute) {
            $path                   = $groupRoute->path . $path;
            $defaults               = \array_merge($groupRoute->defaults, $defaults);
            $requirements           = \array_merge($groupRoute->requirements, $requirements);
            $options                = \array_merge($groupRoute->options, $options);
            $host                   = $groupRoute->host ?? $host;
            $schemes                = $groupRoute->schemes !== [] ? $groupRoute->schemes : $schemes;
            $methods                = $groupRoute->methods !== [] ? $groupRoute->methods : $methods;
            $condition              = $groupRoute->condition ?? $condition;
        }

        $route                      = new Route(
            $path,
            $defaults,
            $requirements,
            $options,
            $host,
            $schemes,
            $methods,
            $condition
        );

        $parameters                 = RouteCompiler::compile($route)->getVariables();
        $requirements               = [];
        $founded                    = [];

        foreach ($methodDescriptor->getArguments() as $parameter) {

            $name                   = $parameter->getName();

            if (\in_array($name, $parameters, true) === false) {
                continue;
            }

            if (false === $parameter instanceof StringableInterface) {
                throw new LogicalException([
                    'template'      => 'Route parameter {parameter} for {service}->{method} must implement StringableInterface',
                    'parameter'     => $name,
                    'service'       => $serviceName,
                    'method'        => $methodDescriptor->getFunctionName(),
                ]);
            }

            if (($pattern = $parameter->getPattern()) !== null) {
                $requirements[$name] = $pattern;
            }

            $founded[]              = $name;
        }

        if (\count($founded) !== \count($parameters)) {
            throw new LogicalException([
                'template'          => 'Route parameters {parameters} for {service}->{method} are not defined in the method arguments',
                'parameters'        => \implode(', ', \array_diff($parameters, $founded)),
                'service'           => $serviceName,
                'method'            => $methodDescriptor->getFunctionName(),
            ]);
        }

        $route->setRequirements($requirements);

        // add information about service and method
        $route->addDefaults([
            '_service'              => $serviceName,
            '_method'               => $methodDescriptor->getFunctionName(),
        ]);

        return $route;
    }
}
