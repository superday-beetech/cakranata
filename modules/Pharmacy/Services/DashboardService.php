<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class DashboardService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /**
     * Ringkasan utama dashboard.
     */
    public function getOverview(
        ?string $from = null,
        ?string $to = null
    ): array {
        [$from, $to] = $this->resolvePeriod($from, $to);

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],

            'stock' => $this->getStockSummary(),

            'procurement' => $this->getProcurementSummary($from, $to),

            'distribution' => $this->getDistributionSummary($from, $to),

            'finance' => $this->getFinanceSummary($from, $to),

            'outstanding' => $this->getOutstandingSummary(),

            'returns' => $this->getReturnsSummary($from, $to),

            'expiring_batches' => $this->getExpiringBatches(30),
        ];
    }

    /**
     * Ringkasan stok saat ini.
     */
    public function getStockSummary(): array
    {
        $result = $this->db->query(
            'SELECT
                COUNT(DISTINCT product_id) AS total_products,
                COUNT(*) AS total_batches,
                COALESCE(SUM(quantity_remaining), 0) AS total_quantity,
                COALESCE(SUM(quantity_remaining * unit_cost), 0) AS stock_value
             FROM product_batches
             WHERE status = ?
               AND quantity_remaining > 0',
            ['available']
        )->getRowArray();

        $expired = $this->db->query(
            'SELECT COUNT(*) AS total
             FROM product_batches
             WHERE status = ?
               AND quantity_remaining > 0
               AND expired_at < CURDATE()',
            ['available']
        )->getRowArray();

        return [
            'total_products' => (int) ($result['total_products'] ?? 0),
            'total_batches' => (int) ($result['total_batches'] ?? 0),
            'total_quantity' => (float) ($result['total_quantity'] ?? 0),
            'stock_value' => (float) ($result['stock_value'] ?? 0),
            'expired_batches' => (int) ($expired['total'] ?? 0),
        ];
    }

    /**
     * Ringkasan pembelian berdasarkan tanggal penerimaan.
     */
    public function getProcurementSummary(
        string $from,
        string $to
    ): array {
        $result = $this->db->query(
            'SELECT
                COUNT(*) AS total_receipts,
                COALESCE(SUM(gr.total_quantity), 0) AS total_quantity,
                COALESCE(SUM(gr.total_value), 0) AS total_value
             FROM (
                SELECT
                    g.id,
                    SUM(gi.quantity_received) AS total_quantity,
                    SUM(gi.quantity_received * gi.unit_cost) AS total_value
                FROM goods_receipts g
                INNER JOIN goods_receipt_items gi
                    ON gi.goods_receipt_id = g.id
                WHERE g.status = ?
                  AND g.received_date BETWEEN ? AND ?
                GROUP BY g.id
             ) gr',
            ['posted', $from, $to]
        )->getRowArray();

        $orders = $this->db->query(
            'SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_order_value
             FROM purchase_orders
             WHERE order_date BETWEEN ? AND ?
               AND status != ?',
            [$from, $to, 'cancelled']
        )->getRowArray();

        return [
            'total_receipts' => (int) ($result['total_receipts'] ?? 0),
            'total_received_quantity' => (float) ($result['total_quantity'] ?? 0),
            'total_received_value' => (float) ($result['total_value'] ?? 0),
            'total_orders' => (int) ($orders['total_orders'] ?? 0),
            'total_order_value' => (float) ($orders['total_order_value'] ?? 0),
        ];
    }

    /**
     * Ringkasan distribusi berdasarkan tanggal pengiriman.
     */
    public function getDistributionSummary(
        string $from,
        string $to
    ): array {
        $deliveries = $this->db->query(
            'SELECT
                COUNT(*) AS total_deliveries
             FROM deliveries
             WHERE status = ?
               AND delivery_date BETWEEN ? AND ?',
            ['posted', $from, $to]
        )->getRowArray();

        $items = $this->db->query(
            'SELECT
                COALESCE(SUM(di.quantity_dispatched), 0) AS total_quantity,
                COALESCE(SUM(di.quantity_dispatched * di.unit_price), 0) AS total_value
             FROM delivery_items di
             INNER JOIN deliveries d ON d.id = di.delivery_id
             WHERE d.status = ?
               AND d.delivery_date BETWEEN ? AND ?',
            ['posted', $from, $to]
        )->getRowArray();

        $orders = $this->db->query(
            'SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_order_value
             FROM distribution_orders
             WHERE order_date BETWEEN ? AND ?
               AND status != ?',
            [$from, $to, 'cancelled']
        )->getRowArray();

        return [
            'total_deliveries' => (int) ($deliveries['total_deliveries'] ?? 0),
            'total_dispatched_quantity' => (float) ($items['total_quantity'] ?? 0),
            'total_dispatched_value' => (float) ($items['total_value'] ?? 0),
            'total_orders' => (int) ($orders['total_orders'] ?? 0),
            'total_order_value' => (float) ($orders['total_order_value'] ?? 0),
        ];
    }

    /**
     * Ringkasan arus kas berdasarkan tanggal transaksi.
     */
    public function getFinanceSummary(
        string $from,
        string $to
    ): array {
        $result = $this->db->query(
            'SELECT
                COALESCE(SUM(
                    CASE WHEN transaction_type = ?
                    THEN amount ELSE 0 END
                ), 0) AS cash_in,

                COALESCE(SUM(
                    CASE WHEN transaction_type = ?
                    THEN amount ELSE 0 END
                ), 0) AS cash_out,

                COUNT(*) AS total_transactions

             FROM cash_transactions
             WHERE transaction_date BETWEEN ? AND ?',
            ['in', 'out', $from, $to]
        )->getRowArray();

        $expenses = $this->db->query(
            'SELECT
                COUNT(*) AS total_expenses,
                COALESCE(SUM(amount), 0) AS expense_amount
             FROM expenses
             WHERE expense_date BETWEEN ? AND ?',
            [$from, $to]
        )->getRowArray();

        $cashIn = (float) ($result['cash_in'] ?? 0);
        $cashOut = (float) ($result['cash_out'] ?? 0);

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash_flow' => $cashIn - $cashOut,
            'total_transactions' => (int) ($result['total_transactions'] ?? 0),
            'total_expenses' => (int) ($expenses['total_expenses'] ?? 0),
            'expense_amount' => (float) ($expenses['expense_amount'] ?? 0),
        ];
    }

    /**
     * Ringkasan utang supplier dan piutang pelanggan.
     *
     * Perhitungan hanya mencakup pembayaran yang terhubung
     * dengan PO atau distribution order.
     */
    public function getOutstandingSummary(): array
    {
        $supplier = $this->db->query(
            'SELECT
                COALESCE(SUM(po.total_amount), 0) AS total_amount,
                COALESCE(SUM(COALESCE(paid.total_paid, 0)), 0) AS paid_amount
             FROM purchase_orders po
             LEFT JOIN (
                SELECT purchase_order_id, SUM(amount) AS total_paid
                FROM supplier_payments
                WHERE purchase_order_id IS NOT NULL
                GROUP BY purchase_order_id
             ) paid ON paid.purchase_order_id = po.id
             WHERE po.status != ?',
            ['cancelled']
        )->getRowArray();

        $customer = $this->db->query(
            'SELECT
                COALESCE(SUM(do.total_amount), 0) AS total_amount,
                COALESCE(SUM(COALESCE(paid.total_paid, 0)), 0) AS paid_amount
             FROM distribution_orders do
             LEFT JOIN (
                SELECT distribution_order_id, SUM(amount) AS total_paid
                FROM customer_payments
                WHERE distribution_order_id IS NOT NULL
                GROUP BY distribution_order_id
             ) paid ON paid.distribution_order_id = do.id
             WHERE do.status != ?',
            ['cancelled']
        )->getRowArray();

        $supplierTotal = (float) ($supplier['total_amount'] ?? 0);
        $supplierPaid = (float) ($supplier['paid_amount'] ?? 0);

        $customerTotal = (float) ($customer['total_amount'] ?? 0);
        $customerPaid = (float) ($customer['paid_amount'] ?? 0);

        return [
            'supplier' => [
                'total_amount' => $supplierTotal,
                'paid_amount' => $supplierPaid,
                'outstanding_amount' => max(0, $supplierTotal - $supplierPaid),
            ],

            'customer' => [
                'total_amount' => $customerTotal,
                'paid_amount' => $customerPaid,
                'outstanding_amount' => max(0, $customerTotal - $customerPaid),
            ],
        ];
    }

    /**
     * Ringkasan retur.
     */
    public function getReturnsSummary(
        string $from,
        string $to
    ): array {
        $purchase = $this->db->query(
            'SELECT
                COUNT(*) AS total_returns,
                COALESCE(SUM(total_amount), 0) AS total_value
             FROM purchase_returns
             WHERE status = ?
               AND return_date BETWEEN ? AND ?',
            ['posted', $from, $to]
        )->getRowArray();

        $sales = $this->db->query(
            'SELECT
                COUNT(*) AS total_returns,
                COALESCE(SUM(total_amount), 0) AS total_value
             FROM sales_returns
             WHERE status = ?
               AND return_date BETWEEN ? AND ?',
            ['posted', $from, $to]
        )->getRowArray();

        return [
            'purchase' => [
                'total_returns' => (int) ($purchase['total_returns'] ?? 0),
                'total_value' => (float) ($purchase['total_value'] ?? 0),
            ],

            'sales' => [
                'total_returns' => (int) ($sales['total_returns'] ?? 0),
                'total_value' => (float) ($sales['total_value'] ?? 0),
            ],
        ];
    }

    /**
     * Daftar batch yang akan kedaluwarsa.
     */
    public function getExpiringBatches(int $days = 30): array
    {
        if ($days < 1 || $days > 365) {
            throw new RuntimeException('Rentang kedaluwarsa harus 1 sampai 365 hari.');
        }

        return $this->db->query(
            'SELECT
                pb.id,
                pb.product_id,
                p.sku,
                p.name AS product_name,
                pb.batch_number,
                pb.expired_at,
                pb.quantity_remaining,
                pb.unit_cost,
                (pb.quantity_remaining * pb.unit_cost) AS stock_value
             FROM product_batches pb
             INNER JOIN products p ON p.id = pb.product_id
             WHERE pb.status = ?
               AND pb.quantity_remaining > 0
               AND pb.expired_at IS NOT NULL
               AND pb.expired_at BETWEEN CURDATE()
                   AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY pb.expired_at ASC, p.name ASC',
            ['available', $days]
        )->getResultArray();
    }

    /**
     * Ringkasan transaksi kas per hari.
     * Berguna untuk grafik dashboard.
     */
    public function getCashFlowChart(
        string $from,
        string $to
    ): array {
        return $this->db->query(
            'SELECT
                DATE(transaction_date) AS date,
                COALESCE(SUM(
                    CASE WHEN transaction_type = ?
                    THEN amount ELSE 0 END
                ), 0) AS cash_in,
                COALESCE(SUM(
                    CASE WHEN transaction_type = ?
                    THEN amount ELSE 0 END
                ), 0) AS cash_out
             FROM cash_transactions
             WHERE transaction_date BETWEEN ? AND ?
             GROUP BY DATE(transaction_date)
             ORDER BY DATE(transaction_date) ASC',
            ['in', 'out', $from, $to]
        )->getResultArray();
    }

    /**
     * Ringkasan penjualan per produk.
     */
    public function getTopSellingProducts(
        string $from,
        string $to,
        int $limit = 10
    ): array {
        if ($limit < 1 || $limit > 100) {
            throw new RuntimeException('Limit harus antara 1 sampai 100.');
        }

        return $this->db->query(
            'SELECT
                p.id AS product_id,
                p.sku,
                p.name AS product_name,
                SUM(di.quantity_dispatched) AS total_quantity,
                SUM(di.quantity_dispatched * di.unit_price) AS total_value
             FROM delivery_items di
             INNER JOIN deliveries d ON d.id = di.delivery_id
             INNER JOIN distribution_items oi
                ON oi.id = di.distribution_item_id
             INNER JOIN products p ON p.id = oi.product_id
             WHERE d.status = ?
               AND d.delivery_date BETWEEN ? AND ?
             GROUP BY p.id, p.sku, p.name
             ORDER BY total_quantity DESC
             LIMIT ' . (int) $limit,
            ['posted', $from, $to]
        )->getResultArray();
    }

    /**
     * Validasi dan normalisasi periode laporan.
     */
    private function resolvePeriod(
        ?string $from,
        ?string $to
    ): array {
        $from = $from ?: date('Y-m-01');
        $to = $to ?: date('Y-m-d');

        $fromDate = $this->parseDate($from);
        $toDate = $this->parseDate($to);

        if ($fromDate > $toDate) {
            throw new RuntimeException(
                'Tanggal awal tidak boleh lebih besar dari tanggal akhir.'
            );
        }

        return [$fromDate, $toDate];
    }

    private function parseDate(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('Format tanggal harus YYYY-MM-DD.');
        }

        return $date;
    }
}
