<?php

declare(strict_types=1);

namespace Modules\Organization\Config;

class MembershipRoles
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const SUPERVISOR = 'supervisor';
    public const STAFF = 'staff';
    public const MEMBER = 'member';
    public const VIEWER = 'viewer';

    public const ALL = [
        self::OWNER,
        self::ADMIN,
        self::MANAGER,
        self::SUPERVISOR,
        self::STAFF,
        self::MEMBER,
        self::VIEWER,
    ];

    public const LABELS = [
        self::OWNER => 'Pemilik',
        self::ADMIN => 'Administrator',
        self::MANAGER => 'Manajer',
        self::SUPERVISOR => 'Supervisor',
        self::STAFF => 'Staf',
        self::MEMBER => 'Anggota',
        self::VIEWER => 'Pengamat',
    ];
}
