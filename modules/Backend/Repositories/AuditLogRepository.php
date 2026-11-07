<?php

declare(strict_types=1);

namespace Modules\Backend\Repositories;

use Config\Database;

class AuditLogRepository
{
    public function paginate(array $filters, int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        $countQuery = $this->buildQuery($filters);
        $total      = $countQuery->countAllResults();

        $rows = $this->buildQuery($filters)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get($perPage, $offset)
            ->getResultArray();

        return [
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => (int) ceil($total / $perPage),
        ];
    }

    private function buildQuery(array $filters)
    {
        $builder = Database::connect()->table('audit_logs');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $builder->groupStart()
                ->like('username', $search)
                ->orLike('user_email', $search)
                ->orLike('module', $search)
                ->orLike('action', $search)
                ->orLike('event', $search)
                ->orLike('description', $search)
                ->orLike('entity_type', $search)
                ->orLike('entity_id', $search)
                ->groupEnd();
        }

        if ($filters['module'] !== '') {
            $builder->where('module', $filters['module']);
        }

        if ($filters['action'] !== '') {
            $builder->where('action', $filters['action']);
        }

        if ($filters['date_from'] !== '') {
            $builder->where(
                'created_at >=',
                $filters['date_from'] . ' 00:00:00'
            );
        }

        if ($filters['date_to'] !== '') {
            $builder->where(
                'created_at <=',
                $filters['date_to'] . ' 23:59:59'
            );
        }

        return $builder;
    }
}
