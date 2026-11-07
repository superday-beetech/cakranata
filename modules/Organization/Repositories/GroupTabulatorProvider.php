<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use App\Core\Tabulator\TabulatorQueryProviderInterface;
use CodeIgniter\Database\BaseBuilder;
use Config\Database;

final class GroupTabulatorProvider implements TabulatorQueryProviderInterface
{
    public function buildDataQuery(): BaseBuilder
    {
        return Database::connect()
            ->table('auth_groups g')
            ->select([
                'g.id',
                'g.name',
                'g.description',
                'g.created_at',
                'g.updated_at',
            ])
            ->select(
                '(
                    SELECT COUNT(*)
                    FROM auth_groups_permissions gp
                    WHERE gp.`group` = g.name
                ) AS permission_count',
                false
            )
            ->where('g.name !=', 'superadmin');
        // ->orderBy('g.name', 'ASC');
    }

    public function buildCountQuery(): BaseBuilder
    {
        return Database::connect()
            ->table('auth_groups g')
            ->where('g.name !=', 'superadmin')
            ->select('COUNT(*) AS total', false);
    }
}
