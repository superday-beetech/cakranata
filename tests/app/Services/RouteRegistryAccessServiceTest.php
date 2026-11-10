<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Services\AccessControlService;
use App\Services\RouteRegistryAccessService;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;

final class RouteRegistryAccessServiceTest extends CIUnitTestCase
{
    protected $db;
    private AccessControlService $accessControl;
    private RouteRegistryAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->createMock(BaseConnection::class);
        $this->accessControl = $this->createMock(
            AccessControlService::class
        );

        $this->service = new RouteRegistryAccessService(
            $this->db,
            $this->accessControl
        );
    }

    public function testFindAllowedRouteMenolakEndpointKosong(): void
    {
        $this->db->expects($this->never())->method('table');

        $this->assertNull(
            $this->service->findAllowedRoute('  ', 'GET')
        );
    }

    public function testAllowsHttpVerbMenolakEndpointKosong(): void
    {
        $this->db->expects($this->never())->method('table');

        $this->assertFalse(
            $this->service->allowsHttpVerb('', 'GET')
        );
    }

    public function testAllowsMenolakIdTidakValid(): void
    {
        $this->accessControl
            ->expects($this->never())
            ->method('isActiveUser');

        $this->assertFalse($this->service->allows(0, 1));
        $this->assertFalse($this->service->allows(1, 0));
        $this->assertFalse($this->service->allows(-1, 1));
    }

    public function testAllowsMenolakPenggunaTidakAktif(): void
    {
        $this->accessControl
            ->expects($this->once())
            ->method('isActiveUser')
            ->with(10)
            ->willReturn(false);

        $this->db->expects($this->never())->method('table');

        $this->assertFalse($this->service->allows(10, 5));
    }

    public function testInspectEndpointMengembalikanDataSaatTidakDitemukan(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $result = $this->createMock(BaseResult::class);

        $this->db->expects($this->once())
            ->method('table')
            ->with('route_registry')
            ->willReturn($builder);

        $builder->expects($this->once())
            ->method('where')
            ->with('endpoint', 'missing')
            ->willReturnSelf();

        $builder->expects($this->once())
            ->method('get')
            ->willReturn($result);

        $result->expects($this->once())
            ->method('getRowArray')
            ->willReturn(null);

        $this->assertSame([
            'found' => false,
            'endpoint' => 'missing',
            'http_verb' => 'GET',
            'message' => 'Endpoint belum terdaftar.',
        ], $this->service->inspectEndpoint(' missing ', 'get'));
    }

    public function testAllowsMenolakRouteTanpaPermission(): void
    {
        $this->accessControl
            ->expects($this->once())
            ->method('isActiveUser')
            ->with(10)
            ->willReturn(true);

        $builder = $this->createMock(BaseBuilder::class);
        $result = $this->createMock(BaseResult::class);

        $this->db->expects($this->once())
            ->method('table')
            ->with('route_registry_permissions')
            ->willReturn($builder);

        $builder->method('select')
            ->with('permission')
            ->willReturnSelf();

        $builder->method('where')
            ->with('route_id', 5)
            ->willReturnSelf();

        $builder->expects($this->once())
            ->method('get')
            ->willReturn($result);

        $result->expects($this->once())
            ->method('getResultArray')
            ->willReturn([]);

        $this->accessControl
            ->expects($this->never())
            ->method('hasAllPermissions');

        $this->assertFalse($this->service->allows(10, 5));
    }

    public function testAllowsMemeriksaSemuaPermissionRoute(): void
    {
        $this->accessControl
            ->expects($this->once())
            ->method('isActiveUser')
            ->with(10)
            ->willReturn(true);

        $builder = $this->createMock(BaseBuilder::class);
        $result = $this->createMock(BaseResult::class);

        $this->db->expects($this->once())
            ->method('table')
            ->with('route_registry_permissions')
            ->willReturn($builder);

        $builder->method('select')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($result);

        $result->method('getResultArray')->willReturn([
            ['permission' => 'reports.view'],
            ['permission' => 'reports.export'],
        ]);

        $this->accessControl
            ->expects($this->once())
            ->method('hasAllPermissions')
            ->with(10, ['reports.view', 'reports.export'])
            ->willReturn(true);

        $this->assertTrue($this->service->allows(10, 5));
    }

    public function testAllowsMenolakPermissionYangTidakTerpenuhi(): void
    {
        $this->accessControl
            ->expects($this->once())
            ->method('isActiveUser')
            ->with(10)
            ->willReturn(true);

        $builder = $this->createMock(BaseBuilder::class);
        $result = $this->createMock(BaseResult::class);

        $this->db->expects($this->once())
            ->method('table')
            ->with('route_registry_permissions')
            ->willReturn($builder);

        $builder->method('select')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($result);

        $result->method('getResultArray')->willReturn([
            ['permission' => 'reports.view'],
        ]);

        $this->accessControl
            ->expects($this->once())
            ->method('hasAllPermissions')
            ->with(10, ['reports.view'])
            ->willReturn(false);

        $this->assertFalse($this->service->allows(10, 5));
    }

    public function testAllowsHttpVerbMenormalisasiEndpointDanVerb(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $result = $this->createMock(BaseResult::class);

        $this->db->expects($this->once())
            ->method('table')
            ->with('route_registry AS rr')
            ->willReturn($builder);

        $builder->method('select')->willReturnSelf();
        $builder->method('join')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($result);

        $result->method('getRowArray')->willReturn([
            'id' => 5,
            'endpoint' => 'reports',
        ]);

        $this->assertTrue(
            $this->service->allowsHttpVerb(' reports ', ' get ')
        );
    }

    public function testFindAllowedRouteMenggunakanCache(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $result = $this->createMock(BaseResult::class);

        $this->db->expects($this->once())
            ->method('table')
            ->with('route_registry AS rr')
            ->willReturn($builder);

        $builder->method('select')->willReturnSelf();
        $builder->method('join')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($result);

        $result->expects($this->once())
            ->method('getRowArray')
            ->willReturn([
                'id' => 5,
                'endpoint' => 'reports',
            ]);

        $first = $this->service->findAllowedRoute('reports', 'GET');
        $second = $this->service->findAllowedRoute('reports', 'GET');

        $this->assertSame($first, $second);
        $this->assertSame(5, $second['id']);
    }
}
