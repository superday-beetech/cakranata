<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Tabulator\TabulatorConfig;
use App\Core\Tabulator\TabulatorQueryProviderInterface;
use App\Core\Tabulator\TabulatorRequest;
use App\Core\Tabulator\TabulatorResult;
use CodeIgniter\Database\BaseBuilder;
use Config\Database;
use InvalidArgumentException;

final class TabulatorQueryService
{
    public function execute(
        TabulatorQueryProviderInterface $provider,
        TabulatorRequest $request,
        TabulatorConfig $config
    ): TabulatorResult {
        $dataBuilder = $provider->buildDataQuery();
        $countRows = $provider->buildCountQuery();

        $this->applyConditions($dataBuilder, $request, $config);
        $this->applyConditions($countRows, $request, $config);

        $db = Database::connect();

        $countRow = $countRows->get()->getRowArray();

        if ($countRow === null || ! isset($countRow['total'])) {
            throw new \RuntimeException(
                'Query total Tabulator gagal menghasilkan total.'
            );
        }

        $total = (int) $countRow['total'];

        $this->applySorting($dataBuilder, $request, $config);

        $rows = $dataBuilder
            ->limit($request->size(), $request->offset())
            ->get()
            ->getResultArray();

        return new TabulatorResult(
            data: $rows,
            total: $total,
            page: $request->page(),
            size: $request->size(),
        );
    }



    /**
     * Mengambil seluruh hasil yang cocok dengan pencarian dan filter,
     * tanpa pagination, untuk kebutuhan export.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportRows(
        TabulatorQueryProviderInterface $provider,
        TabulatorRequest $request,
        TabulatorConfig $config,
        int $maxRows = 50000
    ): array {
        if ($maxRows < 1) {
            throw new InvalidArgumentException(
                'Batas export harus lebih besar dari nol.'
            );
        }

        $dataBuilder = $provider->buildDataQuery();
        $countRows = $provider->buildCountQuery();

        // Samakan kondisi export dengan tabel yang sedang dilihat.
        $this->applyConditions($dataBuilder, $request, $config);
        $this->applyConditions($countRows, $request, $config);

        $db = Database::connect();

        $countRow = $countRows->get()->getRowArray();

        if ($countRow === null || ! isset($countRow['total'])) {
            throw new \RuntimeException(
                'Gagal menghitung jumlah data untuk export.'
            );
        }

        $total = (int) $countRow['total'];

        if ($total > $maxRows) {
            throw new InvalidArgumentException(
                sprintf(
                    'Export berisi %d baris. Batas per permintaan adalah %d baris.',
                    $total,
                    $maxRows
                )
            );
        }

        // Ikuti sorting aktif, tetapi abaikan page dan size.
        $this->applySorting($dataBuilder, $request, $config);

        return $dataBuilder->get()->getResultArray();
    }

    private function applyConditions(
        BaseBuilder $builder,
        TabulatorRequest $request,
        TabulatorConfig $config
    ): void {
        $this->applySearch($builder, $request, $config);
        $this->applyFilters($builder, $request, $config);
    }

    private function applySearch(
        BaseBuilder $builder,
        TabulatorRequest $request,
        TabulatorConfig $config
    ): void {
        $search = $request->search();

        if ($search === '') {
            return;
        }

        $columns = $config->searchableColumns();

        if ($columns === []) {
            throw new InvalidArgumentException(
                'Tidak ada kolom pencarian yang diizinkan.'
            );
        }

        $builder->groupStart();

        foreach ($columns as $column) {
            $builder->orLike($column, $search);
        }

        $builder->groupEnd();
    }

    private function applyFilters(
        BaseBuilder $builder,
        TabulatorRequest $request,
        TabulatorConfig $config
    ): void {
        foreach ($request->filters() as $filter) {
            $field = $filter['field'];
            $type = $filter['type'];
            $value = $filter['value'];

            if (! $config->allowsFilter($field)) {
                throw new InvalidArgumentException(
                    'Kolom filter tidak diizinkan.'
                );
            }

            $column = $config->column($field);

            switch ($type) {
                case '=':
                    $value === null
                        ? $builder->where($column, null)
                        : $builder->where($column, $value);
                    break;

                case '!=':
                case '<>':
                    $value === null
                        ? $builder->where($column . ' IS NOT NULL', null, false)
                        : $builder->where($column . ' !=', $value);
                    break;

                case '>':
                case '>=':
                case '<':
                case '<=':
                    $builder->where($column . ' ' . $type, $value);
                    break;

                case 'like':
                    $this->requireScalar($value);
                    $builder->like($column, (string) $value);
                    break;

                case 'in':
                    $this->requireList($value);
                    $builder->whereIn($column, $value);
                    break;

                case 'not in':
                    $this->requireList($value);
                    $builder->whereNotIn($column, $value);
                    break;

                default:
                    throw new InvalidArgumentException(
                        'Operator filter belum didukung.'
                    );
            }
        }
    }

    private function applySorting(
        BaseBuilder $builder,
        TabulatorRequest $request,
        TabulatorConfig $config
    ): void {
        $sorters = $request->sorters();

        if ($sorters === []) {
            // Urutan default hanya jika pengguna belum memilih sorting.
            $builder->orderBy('g.name', 'ASC');

            return;
        }

        foreach ($sorters as $sorter) {
            $field = $sorter['field'];

            if (! $config->allowsSort($field)) {
                throw new InvalidArgumentException(
                    'Kolom sorting tidak diizinkan.'
                );
            }

            $direction = strtolower($sorter['dir']);

            if (! in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException(
                    'Arah sorting tidak valid.'
                );
            }

            $builder->orderBy(
                $config->column($field),
                $direction
            );
        }
    }

    private function requireScalar(mixed $value): void
    {
        if (! is_scalar($value)) {
            throw new InvalidArgumentException(
                'Filter membutuhkan nilai tunggal.'
            );
        }
    }

    private function requireList(mixed $value): void
    {
        if (
            ! is_array($value)
            || $value === []
            || count($value) > 100
        ) {
            throw new InvalidArgumentException(
                'Daftar filter tidak valid.'
            );
        }

        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                throw new InvalidArgumentException(
                    'Nilai filter tidak valid.'
                );
            }
        }
    }
}
