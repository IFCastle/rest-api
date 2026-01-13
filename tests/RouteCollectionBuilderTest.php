<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\RequestContext;

class RouteCollectionBuilderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testRouter(): void
    {
        $systemEnvironment          = $this->buildSystemEnvironment();
        $routerBuilder              = new RouteCollectionBuilder();

        $routerBuilder($systemEnvironment);

        $compiledRouteCollection    = $systemEnvironment->findDependency(CompiledRouteCollection::class);

        $this->assertInstanceOf(CompiledRouteCollection::class, $compiledRouteCollection, 'CompiledRouteCollection not found');

        $compiledUrlMatcher         = new CompiledUrlMatcher($compiledRouteCollection->collection, new RequestContext());

        $result                     = $compiledUrlMatcher->match('/base/some-method/some-string');

        $this->assertIsArray($result, 'Route not found');
        $this->assertArrayHasKey('_route', $result, 'Route not found');
        $this->assertEquals('someMethod', $result['_route'], 'Route not found');
        $this->assertArrayHasKey('id', $result, 'Parameter id is not found');
        $this->assertEquals('some-string', $result['id'], 'Parameter id is not equal to some-string');

        // Check _service parameter and _method parameter
        $this->assertArrayHasKey('_service', $result, 'Parameter _service is not found');
        $this->assertEquals('someService', $result['_service'], 'Parameter _service is not equal to someService');
        $this->assertArrayHasKey('_method', $result, 'Parameter _method is not found');
        $this->assertEquals('someMethod', $result['_method'], 'Parameter _method is not equal to someMethod');

        // Test UUID parameter
        $result                     = $compiledUrlMatcher->match('/base/method-with-uuid/123e4567-e89b-12d3-a456-426614174000');

        $this->assertIsArray($result, 'Route not found');
        $this->assertArrayHasKey('_route', $result, 'Route not found');
        $this->assertEquals('methodWithUuid', $result['_route'], 'Route not found');
        $this->assertArrayHasKey('uuid', $result, 'Parameter uuid is not found');
        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $result['uuid'], 'Parameter uuid is not equal to 123e4567-e89b-12d3-a456-426614174000');
    }
}
