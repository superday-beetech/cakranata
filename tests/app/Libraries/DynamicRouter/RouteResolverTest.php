<?php

declare(strict_types=1);

namespace Tests\App\Libraries\DynamicRouter;

use App\Libraries\DynamicRouter\MethodPolicy;
use App\Libraries\DynamicRouter\RouteResolver;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Test\CIUnitTestCase;

final class RouteResolverTest extends CIUnitTestCase
{
    public function testGetCollectionResolvesIndexView(): void
    {
        $resolver = $this->resolver('GET', ['ProductController::indexView']);

        $params = [];
        $result = $resolver->resolveControllerAndMethod('product', null, $params);

        $this->assertSame(['ProductController', 'indexView', []], $result);
    }

    public function testGetNumericIdentifierPreservesIdForShowView(): void
    {
        $resolver = $this->resolver('GET', ['ProductController::showView']);

        $params = [];
        $result = $resolver->resolveControllerAndMethod('product', '42', $params);

        $this->assertSame(['ProductController', 'showView', ['42']], $result);
    }

    public function testPostCollectionResolvesCreateAction(): void
    {
        $resolver = $this->resolver('POST', ['ProductController::createAction']);

        $params = [];
        $result = $resolver->resolveControllerAndMethod('product', null, $params);

        $this->assertSame(['ProductController', 'createAction', []], $result);
    }

    public function testPutNumericIdentifierPreservesIdForUpdateAction(): void
    {
        $resolver = $this->resolver('PUT', ['ProductController::updateAction']);

        $params = [];
        $result = $resolver->resolveControllerAndMethod('product', '42', $params);

        $this->assertSame(['ProductController', 'updateAction', ['42']], $result);
    }

    public function testLegacyExplicitMethodConventionRemainsAvailable(): void
    {
        $resolver = $this->resolver('GET', ['ProductController::searchView']);

        $params = [];
        $result = $resolver->resolveControllerAndMethod('product', 'search', $params);

        $this->assertSame(['ProductController', 'searchView', []], $result);
    }

    public function testCustomActionSupportsIdentifierBeforeAction(): void
    {
        $resolver = $this->resolver('POST', ['ProductController::approveAction']);

        $params = ['approve'];
        $result = $resolver->resolveControllerAndMethod('product', '42', $params);

        $this->assertSame(['ProductController', 'approveAction', ['42']], $result);
    }

    public function testCustomActionSupportsActionBeforeIdentifier(): void
    {
        $resolver = $this->resolver('POST', ['ProductController::approveAction']);

        $params = ['42'];
        $result = $resolver->resolveControllerAndMethod('product', 'approve', $params);

        $this->assertSame(['ProductController', 'approveAction', ['42']], $result);
    }

    public function testUnknownWordIsNotGuessedAsRestIdentifier(): void
    {
        $resolver = $this->resolver('GET', ['ProductController::showView']);

        $this->expectException(PageNotFoundException::class);

        $params = [];
        $resolver->resolveControllerAndMethod('product', 'unknown', $params);
    }

    private function resolver(string $verb, array $discoveredMethods): RouteResolver
    {
        $request = $this->createMock(IncomingRequest::class);
        $request->method('getMethod')->willReturn($verb);
        $request->method('isAJAX')->willReturn(false);

        return new RouteResolver(
            $request,
            new MethodPolicy($discoveredMethods),
            ['ProductController' => 'Modules\\Pharmacy\\Controllers\\ProductController'],
            'ProductController'
        );
    }
}
