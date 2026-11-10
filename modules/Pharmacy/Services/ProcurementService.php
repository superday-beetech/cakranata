<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use Modules\Pharmacy\Models\Master\ProductModel;
use Modules\Pharmacy\Models\Master\SupplierModel;
use Modules\Pharmacy\Models\Procurement\PurchaseOrderItemModel;
use Modules\Pharmacy\Models\Procurement\PurchaseOrderModel;
use RuntimeException;
use Throwable;

class ProcurementService
{
    protected $db;

    protected SupplierModel $supplierModel;
    protected ProductModel $productModel;
    protected PurchaseOrderModel $purchaseOrderModel;
    protected PurchaseOrderItemModel $purchaseOrderItemModel;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');

        $this->supplierModel = new SupplierModel();
        $this->productModel = new ProductModel();

        $this->purchaseOrderModel = new PurchaseOrderModel();
        $this->purchaseOrderItemModel = new PurchaseOrderItemModel();
    }

    /**
     * Create Purchase Order.
     */
    public function createPurchaseOrder(
        array $data,
        int $userId
    ): int {
        $this->validatePurchaseOrder($data);

        $this->db->transBegin();

        try {
            $supplier = $this->supplierModel->find(
                $data['supplier_id']
            );

            if (!$supplier) {
                throw new RuntimeException('Supplier tidak ditemukan.');
            }

            $items = [];
            $subtotal = 0;

            foreach ($data['items'] as $item) {
                $product = $this->productModel->find(
                    $item['product_id']
                );

                if (!$product) {
                    throw new RuntimeException(
                        'Produk ID ' . $item['product_id'] . ' tidak ditemukan.'
                    );
                }

                $quantity = (float) $item['quantity'];
                $unitCost = (float) $item['unit_cost'];
                $discount = (float) ($item['discount_amount'] ?? 0);

                $grossAmount = $quantity * $unitCost;

                if ($discount < 0 || $discount > $grossAmount) {
                    throw new RuntimeException(
                        'Diskon item tidak valid.'
                    );
                }

                $lineSubtotal = $grossAmount - $discount;

                $subtotal += $lineSubtotal;

                $items[] = [
                    'product_id'     => $item['product_id'],
                    'quantity'       => $quantity,
                    'unit_cost'      => $unitCost,
                    'discount_amount' => $discount,
                    'subtotal'       => $lineSubtotal,
                    'notes'          => $item['notes'] ?? null,
                ];
            }

            $discountAmount = (float) (
                $data['discount_amount'] ?? 0
            );

            $taxAmount = (float) (
                $data['tax_amount'] ?? 0
            );

            if (
                $discountAmount < 0 ||
                $discountAmount > $subtotal ||
                $taxAmount < 0
            ) {
                throw new RuntimeException(
                    'Nilai diskon atau pajak tidak valid.'
                );
            }

            $totalAmount = $subtotal
                - $discountAmount
                + $taxAmount;

            $orderId = $this->purchaseOrderModel->insert([
                'order_number'   => $data['order_number'],
                'supplier_id'    => $data['supplier_id'],
                'order_date'     => $data['order_date'],
                'expected_date'  => $data['expected_date'] ?? null,
                'status'         => 'draft',
                'subtotal'       => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount'     => $taxAmount,
                'total_amount'   => $totalAmount,
                'notes'          => $data['notes'] ?? null,
                'created_by'     => $userId,
                'updated_by'     => $userId,
            ]);

            if (!$orderId) {
                throw new RuntimeException(
                    'Gagal menyimpan Purchase Order.'
                );
            }

            foreach ($items as $item) {
                $item['purchase_order_id'] = $orderId;

                if (!$this->purchaseOrderItemModel->insert($item)) {
                    throw new RuntimeException(
                        'Gagal menyimpan detail Purchase Order.'
                    );
                }
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Transaksi Purchase Order gagal.'
                );
            }

            $this->db->transCommit();

            return (int) $orderId;
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    /**
     * Validate Purchase Order payload.
     */
    private function validatePurchaseOrder(array $data): void
    {
        foreach (['order_number', 'supplier_id', 'order_date', 'items'] as $field) {
            if (
                !isset($data[$field]) ||
                (is_string($data[$field]) && trim($data[$field]) === '') ||
                $data[$field] === ''
            ) {
                throw new RuntimeException(
                    "Field {$field} wajib diisi."
                );
            }
        }

        if (!is_numeric($data['supplier_id']) || (int) $data['supplier_id'] <= 0) {
            throw new RuntimeException('Supplier tidak valid.');
        }

        if (!$this->isValidDate($data['order_date'])) {
            throw new RuntimeException('Tanggal order tidak valid.');
        }

        $expectedDate = $data['expected_date'] ?? null;

        if ($expectedDate !== null && $expectedDate !== '') {
            if (!$this->isValidDate($expectedDate)) {
                throw new RuntimeException('Tanggal estimasi tidak valid.');
            }

            if ($expectedDate < $data['order_date']) {
                throw new RuntimeException(
                    'Tanggal estimasi tidak boleh sebelum tanggal order.'
                );
            }
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new RuntimeException(
                'Purchase Order harus memiliki minimal satu item.'
            );
        }

        foreach ($data['items'] as $index => $item) {
            if (!is_array($item)) {
                throw new RuntimeException(
                    'Format item Purchase Order tidak valid.'
                );
            }

            foreach (['product_id', 'quantity', 'unit_cost'] as $field) {
                if (!isset($item[$field]) || !is_numeric($item[$field])) {
                    throw new RuntimeException(
                        "Item " . ($index + 1) . ": {$field} tidak valid."
                    );
                }
            }

            $productId = (float) $item['product_id'];
            $quantity = (float) $item['quantity'];
            $unitCost = (float) $item['unit_cost'];
            $discount = (float) ($item['discount_amount'] ?? 0);

            if (
                $productId <= 0 ||
                floor($productId) !== $productId
            ) {
                throw new RuntimeException(
                    'Product ID tidak valid.'
                );
            }

            if ($quantity <= 0 || $unitCost < 0) {
                throw new RuntimeException(
                    'Quantity harus positif dan harga tidak boleh negatif.'
                );
            }

            if (!is_numeric($item['discount_amount'] ?? 0) || $discount < 0) {
                throw new RuntimeException(
                    'Diskon item tidak valid.'
                );
            }
        }

        $discountAmount = $data['discount_amount'] ?? 0;
        $taxAmount = $data['tax_amount'] ?? 0;

        if (
            !is_numeric($discountAmount) ||
            !is_numeric($taxAmount) ||
            (float) $discountAmount < 0 ||
            (float) $taxAmount < 0
        ) {
            throw new RuntimeException(
                'Nilai diskon atau pajak tidak valid.'
            );
        }
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
    /**
     * Update Purchase Order.
     * Hanya PO draft yang dapat diubah.
     */
    public function updatePurchaseOrder(
        int $orderId,
        array $data,
        int $userId
    ): bool {
        $this->validatePurchaseOrder($data);

        $this->db->transBegin();

        try {
            // Lock header agar tidak berubah bersamaan dengan proses lain.
            $order = $this->db->query(
                'SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE',
                [$orderId]
            )->getRowArray();

            if (!$order) {
                throw new RuntimeException(
                    'Purchase Order tidak ditemukan.'
                );
            }

            if ($order['status'] !== 'draft') {
                throw new RuntimeException(
                    'Hanya Purchase Order berstatus draft yang dapat diubah.'
                );
            }

            // PO yang sudah memiliki penerimaan barang tidak boleh diedit.
            $receiptExists = $this->db->table('goods_receipts')
                ->where('purchase_order_id', $orderId)
                ->countAllResults();

            if ($receiptExists > 0) {
                throw new RuntimeException(
                    'Purchase Order sudah memiliki Goods Receipt dan tidak dapat diubah.'
                );
            }

            $supplier = $this->supplierModel->find(
                (int) $data['supplier_id']
            );

            if (!$supplier) {
                throw new RuntimeException(
                    'Supplier tidak ditemukan.'
                );
            }

            $items = [];
            $subtotal = 0;

            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];

                $product = $this->productModel->find($productId);

                if (!$product) {
                    throw new RuntimeException(
                        'Produk ID ' . $productId . ' tidak ditemukan.'
                    );
                }

                $quantity = (float) $item['quantity'];
                $unitCost = (float) $item['unit_cost'];
                $discount = (float) ($item['discount_amount'] ?? 0);

                $grossAmount = $quantity * $unitCost;

                if ($discount < 0 || $discount > $grossAmount) {
                    throw new RuntimeException(
                        'Diskon item tidak valid.'
                    );
                }

                $lineSubtotal = $grossAmount - $discount;
                $subtotal += $lineSubtotal;

                $items[] = [
                    'purchase_order_id' => $orderId,
                    'product_id'        => $productId,
                    'quantity'          => $quantity,
                    'unit_cost'         => $unitCost,
                    'discount_amount'   => $discount,
                    'subtotal'          => $lineSubtotal,
                    'notes'             => $item['notes'] ?? null,
                ];
            }

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);

            if (
                $discountAmount < 0 ||
                $discountAmount > $subtotal ||
                $taxAmount < 0
            ) {
                throw new RuntimeException(
                    'Nilai diskon atau pajak tidak valid.'
                );
            }

            $totalAmount = $subtotal - $discountAmount + $taxAmount;

            $updated = $this->purchaseOrderModel->update($orderId, [
                'order_number'    => $data['order_number'],
                'supplier_id'     => (int) $data['supplier_id'],
                'order_date'      => $data['order_date'],
                'expected_date'   => $data['expected_date'] ?? null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount'      => $taxAmount,
                'total_amount'    => $totalAmount,
                'notes'           => $data['notes'] ?? null,
                'updated_by'      => $userId,
            ]);

            if ($updated === false) {
                throw new RuntimeException(
                    'Gagal memperbarui Purchase Order.'
                );
            }

            // Ganti seluruh detail item dengan data terbaru.
            $this->db->table('purchase_order_items')
                ->where('purchase_order_id', $orderId)
                ->delete();

            foreach ($items as $item) {
                if (!$this->db->table('purchase_order_items')->insert($item)) {
                    throw new RuntimeException(
                        'Gagal memperbarui detail Purchase Order.'
                    );
                }
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Transaksi perubahan Purchase Order gagal.'
                );
            }

            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Delete Purchase Order.
     * Hanya PO draft tanpa Goods Receipt yang dapat dihapus.
     */
    public function deletePurchaseOrder(int $orderId): bool
    {
        $this->db->transBegin();

        try {
            $order = $this->db->query(
                'SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE',
                [$orderId]
            )->getRowArray();

            if (!$order) {
                throw new RuntimeException(
                    'Purchase Order tidak ditemukan.'
                );
            }

            if ($order['status'] !== 'draft') {
                throw new RuntimeException(
                    'Hanya Purchase Order berstatus draft yang dapat dihapus.'
                );
            }

            $receiptExists = $this->db->table('goods_receipts')
                ->where('purchase_order_id', $orderId)
                ->countAllResults();

            if ($receiptExists > 0) {
                throw new RuntimeException(
                    'Purchase Order sudah memiliki Goods Receipt dan tidak dapat dihapus.'
                );
            }

            $this->db->table('purchase_order_items')
                ->where('purchase_order_id', $orderId)
                ->delete();

            $this->db->table('purchase_orders')
                ->where('id', $orderId)
                ->delete();

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menghapus Purchase Order.'
                );
            }

            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}


/**
 *
 * $data = [
    'order_number' => 'PO-2026-0001',
    'supplier_id'  => 1,
    'order_date'   => date('Y-m-d'),
    'expected_date'=> date('Y-m-d', strtotime('+3 days')),

    'discount_amount' => 10000,
    'tax_amount'      => 0,

    'items' => [
        [
            'product_id' => 1,
            'quantity'   => 10,
            'unit_cost'  => 25000,
        ],
        [
            'product_id' => 2,
            'quantity'   => 5,
            'unit_cost'  => 40000,
        ],
    ],
];

$service = new ProcurementService();

$orderId = $service->createPurchaseOrder(
    $data,
    auth()->id()
);
 */
