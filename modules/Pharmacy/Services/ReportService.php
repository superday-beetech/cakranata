<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;

class ReportService
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /**
     * Ringkasan stok per produk.
     *
     * Filters:
     * - keyword
     * - product_id
     * - category_id
     * - active_only
     * - page
     * - per_page
     */


    public function getStockSummary(array $filters = []): array
    {
        $this->validateDateFilters($filters);

        $dateFrom = $filters['date_from'] ?? '1970-01-01';
        $dateTo = $filters['date_to'] ?? date('Y-m-d');

        if ($dateFrom > $dateTo) {
            throw new InvalidArgumentException(
                'Tanggal mulai tidak boleh melebihi tanggal akhir.'
            );
        }

        $from = $this->db->escape($dateFrom . ' 00:00:00');
        $to = $this->db->escape($dateTo . ' 23:59:59');

        $builder = $this->db->table('products p');

        $builder->select("
        p.id AS product_id,
        p.sku,
        p.barcode,
        p.name AS product_name,
        p.category_id,
        c.name AS category_name,
        p.unit_id,
        u.name AS unit_name,
        u.abbreviation AS unit_abbreviation,
        p.purchase_price,
        p.selling_price,
        p.is_active,

        COALESCE((
            SELECT SUM(sm.quantity_in - sm.quantity_out)
            FROM stock_movements sm
            WHERE sm.product_id = p.id
              AND sm.movement_date < {$from}
        ), 0) AS opening_stock,

        COALESCE((
            SELECT SUM(sm.quantity_in)
            FROM stock_movements sm
            WHERE sm.product_id = p.id
              AND sm.movement_date BETWEEN {$from} AND {$to}
        ), 0) AS total_in,

        COALESCE((
            SELECT SUM(sm.quantity_out)
            FROM stock_movements sm
            WHERE sm.product_id = p.id
              AND sm.movement_date BETWEEN {$from} AND {$to}
        ), 0) AS total_out,

        COALESCE((
            SELECT SUM(sm.quantity_in - sm.quantity_out)
            FROM stock_movements sm
            WHERE sm.product_id = p.id
              AND sm.movement_date <= {$to}
        ), 0) AS closing_stock,

        (
            COALESCE((
                SELECT SUM(sm.quantity_in - sm.quantity_out)
                FROM stock_movements sm
                WHERE sm.product_id = p.id
                  AND sm.movement_date <= {$to}
            ), 0) * p.purchase_price
        ) AS stock_value,

        (
            SELECT COUNT(*)
            FROM product_batches pb
            WHERE pb.product_id = p.id
              AND pb.quantity_remaining > 0
              AND pb.expired_at < " . $this->db->escape($dateTo) . "
        ) AS expired_batch_count,

        (
            SELECT COUNT(*)
            FROM product_batches pb
            WHERE pb.product_id = p.id
              AND pb.quantity_remaining > 0
              AND pb.expired_at >= " . $this->db->escape($dateTo) . "
              AND pb.expired_at <= DATE_ADD(
                  " . $this->db->escape($dateTo) . ",
                  INTERVAL 30 DAY
              )
        ) AS expiring_batch_count,

        (
            SELECT COUNT(*)
            FROM product_batches pb
            WHERE pb.product_id = p.id
        ) AS batch_count
    ", false);

        $builder->join('categories c', 'c.id = p.category_id', 'left');
        $builder->join('units u', 'u.id = p.unit_id', 'left');

        if (!empty($filters['product_id'])) {
            $builder->where('p.id', (int) $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $builder->where('p.category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);

            $builder->groupStart()
                ->like('p.name', $keyword)
                ->orLike('p.sku', $keyword)
                ->orLike('p.barcode', $keyword)
                ->groupEnd();
        }

        if (!empty($filters['active_only'])) {
            $builder->where('p.is_active', 1);
        }

        $builder->orderBy('p.name', 'ASC');

        // Ambil hasil seluruh produk yang sesuai filter.
        $allItems = $builder->get()->getResultArray();

        $summary = [
            'product_count' => count($allItems),
            'opening_stock' => 0.0,
            'total_in' => 0.0,
            'total_out' => 0.0,
            'closing_stock' => 0.0,
            'stock_value' => 0.0,
            'expired_batch_count' => 0,
            'expiring_batch_count' => 0,
        ];

        foreach ($allItems as $item) {
            $summary['opening_stock'] += (float) $item['opening_stock'];
            $summary['total_in'] += (float) $item['total_in'];
            $summary['total_out'] += (float) $item['total_out'];
            $summary['closing_stock'] += (float) $item['closing_stock'];
            $summary['stock_value'] += (float) $item['stock_value'];
            $summary['expired_batch_count'] += (int) $item['expired_batch_count'];
            $summary['expiring_batch_count'] += (int) $item['expiring_batch_count'];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(5000, max(1, (int) ($filters['per_page'] ?? 20)));

        $total = count($allItems);
        $items = array_slice(
            $allItems,
            ($page - 1) * $perPage,
            $perPage
        );

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'summary' => $summary,
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }


    public function getStockSummaryOld(array $filters = []): array
    {
        $builder = $this->db->table('products p');

        $builder->select("
            p.id AS product_id,
            p.sku,
            p.barcode,
            p.name AS product_name,
            p.category_id,
            c.name AS category_name,
            p.unit_id,
            u.name AS unit_name,
            u.abbreviation AS unit_abbreviation,
            p.purchase_price,
            p.selling_price,
            p.is_active,
            COUNT(DISTINCT pb.id) AS batch_count,
            COALESCE(SUM(pb.received_quantity), 0) AS total_received,
            COALESCE(SUM(pb.quantity_remaining), 0) AS total_stock,
            COALESCE(
                SUM(pb.quantity_remaining * pb.unit_cost),
                0
            ) AS stock_value,
            SUM(
                CASE
                    WHEN pb.expired_at < CURDATE()
                    AND pb.quantity_remaining > 0
                    THEN 1 ELSE 0
                END
            ) AS expired_batch_count,
            SUM(
                CASE
                    WHEN pb.expired_at >= CURDATE()
                    AND pb.expired_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND pb.quantity_remaining > 0
                    THEN 1 ELSE 0
                END
            ) AS expiring_batch_count
        ", false);

        $builder->join('categories c', 'c.id = p.category_id', 'left');
        $builder->join('units u', 'u.id = p.unit_id', 'left');
        $builder->join(
            'product_batches pb',
            'pb.product_id = p.id',
            'left'
        );

        if (!empty($filters['product_id'])) {
            $builder->where('p.id', (int) $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $builder->where('p.category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);

            $builder->groupStart()
                ->like('p.name', $keyword)
                ->orLike('p.sku', $keyword)
                ->orLike('p.barcode', $keyword)
                ->groupEnd();
        }

        if (!empty($filters['active_only'])) {
            $builder->where('p.is_active', 1);
        }

        $builder->groupBy('p.id');
        $builder->orderBy('p.name', 'ASC');

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($filters['per_page'] ?? 50)));

        $countBuilder = clone $builder;
        $total = count($countBuilder->get()->getResultArray());

        $items = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Kartu stok suatu produk.
     *
     * Filters:
     * - product_id (wajib)
     * - batch_id (opsional)
     * - date_from
     * - date_to
     * - page
     * - per_page
     */
    public function getStockCard(array $filters = []): array
    {
        $productId = (int) ($filters['product_id'] ?? 0);

        if ($productId <= 0) {
            throw new InvalidArgumentException(
                'Product ID wajib diisi untuk laporan kartu stok.'
            );
        }

        $this->validateDateFilters($filters);

        $product = $this->db->table('products p')
            ->select('
                p.id,
                p.sku,
                p.name,
                u.name AS unit_name,
                u.abbreviation AS unit_abbreviation
            ')
            ->join('units u', 'u.id = p.unit_id', 'left')
            ->where('p.id', $productId)
            ->get()
            ->getRowArray();

        if (!$product) {
            throw new InvalidArgumentException('Produk tidak ditemukan.');
        }

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $batchId = (int) ($filters['batch_id'] ?? 0);

        // Saldo awal sebelum tanggal mulai.
        $openingBuilder = $this->db->table('stock_movements')
            ->select("
                COALESCE(SUM(quantity_in), 0)
                - COALESCE(SUM(quantity_out), 0) AS opening_stock
            ", false)
            ->where('product_id', $productId);

        if ($batchId > 0) {
            $openingBuilder->where('batch_id', $batchId);
        }

        if ($dateFrom) {
            $openingBuilder->where(
                'movement_date <',
                $dateFrom . ' 00:00:00'
            );
        }

        $openingRow = $openingBuilder->get()->getRowArray();
        $openingStock = (float) ($openingRow['opening_stock'] ?? 0);

        // Detail pergerakan stok dalam periode laporan.
        $builder = $this->db->table('stock_movements sm');

        $builder->select("
            sm.id,
            sm.product_id,
            sm.batch_id,
            sm.movement_date,
            sm.movement_type,
            sm.reference_type,
            sm.reference_id,
            sm.quantity_in,
            sm.quantity_out,
            sm.unit_cost,
            sm.notes,
            pb.batch_number,
            pb.expired_at
        ");

        $builder->join(
            'product_batches pb',
            'pb.id = sm.batch_id',
            'left'
        );

        $builder->where('sm.product_id', $productId);

        if ($batchId > 0) {
            $builder->where('sm.batch_id', $batchId);
        }

        if ($dateFrom) {
            $builder->where(
                'sm.movement_date >=',
                $dateFrom . ' 00:00:00'
            );
        }

        if ($dateTo) {
            $nextDate = date(
                'Y-m-d',
                strtotime($dateTo . ' +1 day')
            );

            $builder->where(
                'sm.movement_date <',
                $nextDate . ' 00:00:00'
            );
        }
        $builder->orderBy('sm.movement_date', 'ASC');
        $builder->orderBy('sm.id', 'ASC');

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(500, max(1, (int) ($filters['per_page'] ?? 100)));

        // Ambil seluruh pergerakan untuk menghitung saldo berjalan
        // secara konsisten sebelum melakukan pagination.
        $allItems = $builder->get()->getResultArray();

        $totalIn = 0.0;
        $totalOut = 0.0;
        $runningStock = $openingStock;

        foreach ($allItems as &$item) {
            $quantityIn = (float) $item['quantity_in'];
            $quantityOut = (float) $item['quantity_out'];

            $totalIn += $quantityIn;
            $totalOut += $quantityOut;

            $runningStock += $quantityIn - $quantityOut;

            $item['balance'] = $runningStock;
        }

        unset($item);

        $total = count($allItems);
        $offset = ($page - 1) * $perPage;

        return [
            'product' => $product,
            'summary' => [
                'opening_stock' => $openingStock,
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'closing_stock' => $runningStock,
            ],
            'items' => array_slice($allItems, $offset, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Laporan batch dan kedaluwarsa.
     *
     * Filters:
     * - keyword
     * - product_id
     * - category_id
     * - expiry_status: all|expired|expiring|safe
     * - days: batas hari untuk expiring
     * - stock_only
     * - page
     * - per_page
     */
    public function getBatchExpiry(array $filters = []): array
    {
        $this->validateDateFilters($filters);

        $builder = $this->db->table('product_batches pb');

        $builder->select("
            pb.id AS batch_id,
            pb.product_id,
            p.sku,
            p.name AS product_name,
            c.name AS category_name,
            u.abbreviation AS unit,
            pb.goods_receipt_item_id,
            pb.batch_number,
            pb.expired_at,
            pb.received_quantity,
            pb.quantity_remaining,
            pb.unit_cost,
            pb.status,
            DATEDIFF(pb.expired_at, CURDATE()) AS days_until_expiry,
            CASE
                WHEN pb.expired_at < CURDATE() THEN 'expired'
                WHEN pb.expired_at <= DATE_ADD(
                    CURDATE(),
                    INTERVAL " . (int) ($filters['days'] ?? 90) . " DAY
                ) THEN 'expiring'
                ELSE 'safe'
            END AS expiry_status
        ", false);

        $builder->join('products p', 'p.id = pb.product_id');
        $builder->join('categories c', 'c.id = p.category_id', 'left');
        $builder->join('units u', 'u.id = p.unit_id', 'left');

        if (!empty($filters['product_id'])) {
            $builder->where('pb.product_id', (int) $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $builder->where('p.category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);

            $builder->groupStart()
                ->like('p.name', $keyword)
                ->orLike('p.sku', $keyword)
                ->orLike('pb.batch_number', $keyword)
                ->groupEnd();
        }

        if (!empty($filters['stock_only'])) {
            $builder->where('pb.quantity_remaining >', 0);
        }

        $days = max(1, min(3650, (int) ($filters['days'] ?? 90)));
        $expiryStatus = $filters['expiry_status'] ?? 'all';

        if ($expiryStatus === 'expired') {
            $builder->where('pb.expired_at <', date('Y-m-d'));
        } elseif ($expiryStatus === 'expiring') {
            $builder->where('pb.expired_at >=', date('Y-m-d'));
            $builder->where(
                'pb.expired_at <=',
                date('Y-m-d', strtotime("+{$days} days"))
            );
        } elseif ($expiryStatus === 'safe') {
            $builder->where(
                'pb.expired_at >',
                date('Y-m-d', strtotime("+{$days} days"))
            );
        }

        $builder->orderBy('pb.expired_at', 'ASC');
        $builder->orderBy('p.name', 'ASC');

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($filters['per_page'] ?? 50)));

        $countBuilder = clone $builder;
        $total = count($countBuilder->get()->getResultArray());

        $items = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            'filters' => [
                'expiry_status' => $expiryStatus,
                'days' => $days,
            ],
        ];
    }

    private function validateDateFilters(array $filters): void
    {
        foreach (['date_from', 'date_to'] as $field) {
            if (
                !empty($filters[$field])
                && !$this->isValidDate((string) $filters[$field])
            ) {
                throw new InvalidArgumentException(
                    "Format {$field} harus YYYY-MM-DD."
                );
            }
        }

        if (
            !empty($filters['date_from'])
            && !empty($filters['date_to'])
            && $filters['date_from'] > $filters['date_to']
        ) {
            throw new InvalidArgumentException(
                'Tanggal mulai tidak boleh melebihi tanggal akhir.'
            );
        }
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date;
    }
}
