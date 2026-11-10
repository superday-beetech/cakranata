<?php

declare(strict_types=1);

namespace App\Services\Api;

use CodeIgniter\Database\BaseBuilder;

class ApiQueryBuilder
{
    public function applySearch(
        BaseBuilder $builder,
        string $search,
        array $columns
    ): BaseBuilder {
        $search = trim($search);

        if ($search === '' || $columns === []) {
            return $builder;
        }

        $builder->groupStart();

        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $builder->like($column, $search);
            } else {
                $builder->orLike($column, $search);
            }
        }

        $builder->groupEnd();

        return $builder;
    }

    public function applySort(
        BaseBuilder $builder,
        string $sort,
        string $order,
        array $allowedColumns,
        string $defaultSort = 'id',
        string $defaultOrder = 'asc'
    ): BaseBuilder {
        if (! in_array($sort, $allowedColumns, true)) {
            $sort = $defaultSort;
        }

        $order = strtolower($order);

        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = $defaultOrder;
        }

        return $builder->orderBy($sort, $order);
    }

    public function applyPagination(
        BaseBuilder $builder,
        int $page,
        int $perPage
    ): BaseBuilder {
        $offset = ($page - 1) * $perPage;

        return $builder->limit(
            $perPage,
            $offset
        );
    }
}
