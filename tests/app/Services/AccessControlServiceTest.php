<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Services\AccessControlService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class AccessControlServiceTest extends CIUnitTestCase
{
    protected $db;
    private AccessControlService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect('tests');
        $this->db->initialize();

        $this->createSchema();

        $this->db->transBegin();

        $this->service = new AccessControlService($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            if ($this->db->transStatus() !== false) {
                $this->db->transRollback();
            }

            $this->db->close();
        }

        parent::tearDown();
    }

    private function createSchema(): void
    {
        $this->db->query('
            CREATE TABLE IF NOT EXISTS db_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(100) NOT NULL,
                active INTEGER DEFAULT 1,
                deleted_at DATETIME NULL
            )
        ');

        $this->db->query('
            CREATE TABLE IF NOT EXISTS db_auth_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL
            )
        ');

        $this->db->query('
            CREATE TABLE IF NOT EXISTS db_auth_groups_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                "group" VARCHAR(100) NOT NULL
            )
        ');

        $this->db->query('
            CREATE TABLE IF NOT EXISTS db_auth_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL
            )
        ');

        $this->db->query('
            CREATE TABLE IF NOT EXISTS db_auth_permissions_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                permission VARCHAR(100) NOT NULL
            )
        ');

        $this->db->query('
            CREATE TABLE IF NOT EXISTS db_auth_groups_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                permission_id INTEGER NOT NULL
            )
        ');
    }

    public function testPermissionKosongDitolak(): void
    {
        $userId = $this->createUser();

        $this->assertFalse(
            $this->service->hasAllPermissions($userId, [])
        );

        $this->assertFalse(
            $this->service->hasAllPermissionsNoByPass($userId, [])
        );
    }

    public function testSuperadminTanpaPermissionTetapDitolak(): void
    {
        $userId = $this->createUser();

        $this->createGroupMembership($userId, 'superadmin');

        $this->assertFalse(
            $this->service->hasAllPermissions(
                $userId,
                ['test.permission.required']
            )
        );

        $this->assertFalse(
            $this->service->hasPermission(
                $userId,
                'test.permission.required'
            )
        );
    }

    public function testSemuaPermissionWajibHarusDimiliki(): void
    {
        $userId = $this->createUser();

        $this->grantDirectPermission(
            $userId,
            'test.permission.one'
        );

        $this->assertTrue(
            $this->service->hasAllPermissions(
                $userId,
                ['test.permission.one']
            )
        );

        $this->assertFalse(
            $this->service->hasAllPermissions(
                $userId,
                [
                    'test.permission.one',
                    'test.permission.two',
                ]
            )
        );
    }

    public function testInputPermissionTidakValidDitolak(): void
    {
        $userId = $this->createUser();

        $this->grantDirectPermission(
            $userId,
            'test.permission.one'
        );

        $this->assertFalse(
            $this->service->hasAllPermissions(
                $userId,
                ['test.permission.one', '']
            )
        );

        $this->assertFalse(
            $this->service->hasAllPermissions(
                $userId,
                ['test.permission.one', null]
            )
        );
    }

    public function testPenggunaTidakAktifDitolak(): void
    {
        $userId = $this->createUser(deleted: true);

        $this->grantDirectPermission(
            $userId,
            'test.permission.one'
        );

        $this->assertFalse(
            $this->service->hasAllPermissions(
                $userId,
                ['test.permission.one']
            )
        );
    }

    public function testPermissionDariGrupDikenali(): void
    {
        $userId = $this->createUser();

        $groupId = $this->createGroup('operator');

        $permissionId = $this->createPermission(
            'test.group.permission'
        );

        $this->addUserToGroup($userId, 'operator');

        $this->db->table('auth_groups_permissions')->insert([
            'group_id' => $groupId,
            'permission_id' => $permissionId,
        ]);

        $this->assertTrue(
            $this->service->hasPermission(
                $userId,
                'test.group.permission'
            )
        );
    }

    public function testUserBiasaTanpaPermissionDitolak(): void
    {
        $userId = $this->createUser();

        $this->createGroupMembership($userId, 'operator');

        $this->assertFalse(
            $this->service->hasPermission(
                $userId,
                'test.permission.required'
            )
        );
    }

    private function createUser(bool $deleted = false): int
    {
        $this->db->table('users')->insert([
            'username' => 'test_' . bin2hex(random_bytes(6)),
            'active' => 1,
            'deleted_at' => $deleted
                ? date('Y-m-d H:i:s')
                : null,
        ]);

        return (int) $this->db->insertID();
    }

    private function createGroup(string $groupName): int
    {
        $this->db->table('auth_groups')->insert([
            'name' => $groupName,
            'description' => 'Test group',
        ]);

        return (int) $this->db->insertID();
    }

    private function createPermission(string $permission): int
    {
        $this->db->table('auth_permissions')->insert([
            'name' => $permission,
            'description' => 'Test permission',
        ]);

        return (int) $this->db->insertID();
    }

    private function grantDirectPermission(
        int $userId,
        string $permission
    ): void {
        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $userId,
            'permission' => $permission,
        ]);
    }

    private function createGroupMembership(
        int $userId,
        string $groupName
    ): void {
        $this->createGroup($groupName);
        $this->addUserToGroup($userId, $groupName);
    }

    private function addUserToGroup(
        int $userId,
        string $groupName
    ): void {
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => $groupName,
        ]);
    }
}
