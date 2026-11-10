<?php

declare(strict_types=1);

namespace Tests\App\Libraries\DynamicRouter;

use App\Libraries\DynamicRouter\AccessChecker;
use App\Libraries\DynamicRouter\AuthenticationResolver;
use App\Services\AccessControlService;
use App\Services\RouteRegistryAccessService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use PHPUnit\Framework\MockObject\MockObject;

final class AccessCheckerTest extends CIUnitTestCase
{
    /** @var RequestInterface&MockObject */
    private $request;

    /** @var AuthenticationResolver&MockObject */
    private $resolver;

    /** @var RouteRegistryAccessService&MockObject */
    private $registry;

    /** @var AccessControlService&MockObject */
    private $accessControl;

    private AccessChecker $checker;

    private array $routeInfo = [];

    private bool $routeAllowed = false;

    private bool $userIsActive = true;

    private bool $userIsSuperadmin = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routeInfo = [];
        $this->routeAllowed = false;
        $this->userIsActive = true;
        $this->userIsSuperadmin = false;

        $this->request = $this->createMock(RequestInterface::class);
        $this->request
            ->method('getMethod')
            ->willReturn('GET');

        $this->resolver = $this->getMockBuilder(AuthenticationResolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAuthenticatedUser',
                'isAuthenticated',
                'isApiRequest',
            ])
            ->getMock();

        $this->registry = $this->getMockBuilder(RouteRegistryAccessService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'inspectEndpoint',
                'allows',
            ])
            ->getMock();

        $this->registry
            ->method('inspectEndpoint')
            ->willReturnCallback(
                fn(string $endpoint, string $verb): array => $this->routeInfo
            );

        $this->registry
            ->method('allows')
            ->willReturnCallback(
                fn(int $userId, int $routeId): bool => $this->routeAllowed
            );

        // Buat mock terlebih dahulu sebelum mengonfigurasi metodenya.
        $this->accessControl = $this->getMockBuilder(AccessControlService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'isActiveUser',
                'isSuperadmin',
            ])
            ->getMock();

        $this->accessControl
            ->method('isActiveUser')
            ->willReturnCallback(
                fn(int $userId): bool => $this->userIsActive
            );

        $this->accessControl
            ->method('isSuperadmin')
            ->willReturnCallback(
                fn(int $userId): bool => $this->userIsSuperadmin
            );

        Services::injectMock('routeRegistryAccess', $this->registry);
        Services::injectMock('accessControl', $this->accessControl);

        $this->checker = new AccessChecker(
            $this->request,
            $this->resolver,
            ['login']
        );
    }

    protected function tearDown(): void
    {
        Services::resetSingle('routeRegistryAccess');
        Services::resetSingle('accessControl');

        parent::tearDown();
    }

    public function testEndpointPublikDiizinkanTanpaLogin(): void
    {
        $result = $this->checker->diagnoseAccess('login');

        $this->assertTrue($result['allowed']);
        $this->assertSame('public_endpoint', $result['reason']);
        $this->assertSame(200, $result['status']);
    }

    public function testEndpointKosongDitolak(): void
    {
        $result = $this->checker->diagnoseAccess('');

        $this->assertFalse($result['allowed']);
        $this->assertSame('invalid_endpoint', $result['reason']);
        $this->assertSame(404, $result['status']);
    }

    public function testEndpointTidakTerdaftarDitolak(): void
    {
        $this->routeInfo = ['found' => false];

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('endpoint_not_registered', $result['reason']);
        $this->assertSame(404, $result['status']);
    }

    public function testEndpointBelumDitemukanDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo([
            'is_discovered' => false,
        ]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('endpoint_not_discovered', $result['reason']);
        $this->assertSame(403, $result['status']);
    }

    public function testRouteTidakRoutableDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo([
            'is_routable' => false,
        ]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('route_not_routable', $result['reason']);
    }

    public function testRouteTidakDiizinkanDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo([
            'is_allowed' => false,
        ]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('route_not_allowed', $result['reason']);
    }

    public function testHttpVerbTidakTerdaftarDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo([
            'verb_registered' => false,
        ]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('http_verb_not_allowed', $result['reason']);
        $this->assertSame(405, $result['status']);
    }

    public function testPenggunaBelumLoginDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo();

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn(null);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('authentication_required', $result['reason']);
        $this->assertSame(401, $result['status']);
    }

    public function testIdPenggunaTidakValidDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo();

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 0]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('invalid_user', $result['reason']);
        $this->assertSame(401, $result['status']);
    }

    public function testPermissionDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo();
        $this->routeAllowed = false;

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('permission_denied', $result['reason']);
        $this->assertSame(403, $result['status']);
    }

    public function testPermissionDiberikan(): void
    {
        $this->routeInfo = $this->validRouteInfo();
        $this->routeAllowed = true;

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertTrue($result['allowed']);
        $this->assertSame('permission_granted', $result['reason']);
        $this->assertSame(200, $result['status']);
    }

    public function testAkunTidakAktifDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo();
        $this->userIsActive = false;

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('user_inactive', $result['reason']);
        $this->assertSame(403, $result['status']);
    }

    public function testSuperadminMendapatAksesSesuaiImplementasiSaatIni(): void
    {
        $this->routeInfo = $this->validRouteInfo();
        $this->userIsActive = true;
        $this->userIsSuperadmin = true;

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertTrue($result['allowed']);
        $this->assertSame('superadmin', $result['reason']);
        $this->assertSame(200, $result['status']);
    }

    public function testPermissionRouteKosongDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo([
            'permissions' => [],
        ]);

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('route_permission_not_configured', $result['reason']);
    }

    public function testFormatPermissionTidakValidDitolak(): void
    {
        $this->routeInfo = $this->validRouteInfo([
            'permissions' => 'reports.view',
        ]);

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $result = $this->checker->diagnoseAccess('reports');

        $this->assertFalse($result['allowed']);
        $this->assertSame('invalid_route_permissions', $result['reason']);
        $this->assertSame(500, $result['status']);
    }

    public function testCheckAccessMengembalikanBoolean(): void
    {
        $this->routeInfo = $this->validRouteInfo();
        $this->routeAllowed = true;

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $this->assertTrue($this->checker->checkAccess('reports'));
    }

    public function testIsSuperadminMemerlukanAkunAktif(): void
    {
        $this->userIsActive = false;
        $this->userIsSuperadmin = true;

        $this->resolver
            ->method('getAuthenticatedUser')
            ->willReturn((object) ['id' => 10]);

        $this->assertFalse($this->checker->isSuperadmin());
    }

    private function validRouteInfo(array $overrides = []): array
    {
        return array_replace([
            'found' => true,
            'id' => 5,
            'endpoint' => 'reports',
            'is_discovered' => true,
            'is_routable' => true,
            'is_allowed' => true,
            'verb_registered' => true,
            'permissions' => ['reports.view'],
        ], $overrides);
    }
}
