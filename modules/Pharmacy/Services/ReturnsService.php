<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class ReturnsService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /**
     * Membuat draft retur pembelian.
     *
     * Format items:
     * [
     *   [
     *      'goods_receipt_item_id' => 1,
     *      'batch_id' => 1,
     *      'quantity' => 2,
     *      'notes' => 'Kemasan rusak',
     *   ],
     * ]
     */

    public function createPurchaseReturn(
        array $data,
        int $userId
    ): int {
        $this->validateHeader($data, [
            'return_number',
            'goods_receipt_id',
            'return_date',
            'items',
        ]);

        $this->db->transBegin();

        try {
            $receiptId = (int) $data['goods_receipt_id'];

            // 1. Kunci dan validasi penerimaan.
            $receipt = $this->db->query(
                'SELECT * FROM goods_receipts
             WHERE id = ?
             FOR UPDATE',
                [$receiptId]
            )->getRowArray();

            if (!$receipt || $receipt['status'] !== 'posted') {
                throw new RuntimeException(
                    'Penerimaan barang tidak ditemukan atau belum diposting.'
                );
            }

            // 2. Kunci PO asal.
            $purchaseOrder = $this->db->query(
                'SELECT * FROM purchase_orders
             WHERE id = ?
             FOR UPDATE',
                [(int) $receipt['purchase_order_id']]
            )->getRowArray();

            if (!$purchaseOrder) {
                throw new RuntimeException(
                    'Purchase Order asal tidak ditemukan.'
                );
            }

            $supplierId = (int) $purchaseOrder['supplier_id'];

            // 3. Siapkan detail retur.
            $items = $this->preparePurchaseReturnItems(
                $receiptId,
                $data['items']
            );

            if (!$items) {
                throw new RuntimeException(
                    'Detail retur pembelian kosong.'
                );
            }

            // 4. Agregasikan kuantitas berdasarkan batch.
            $batchQuantities = [];

            foreach ($items as $item) {
                $batchId = (int) $item['batch_id'];
                $quantity = (float) $item['quantity'];

                if ($batchId <= 0 || $quantity <= 0) {
                    throw new RuntimeException(
                        'Batch dan kuantitas retur tidak valid.'
                    );
                }

                $batchQuantities[$batchId] =
                    ($batchQuantities[$batchId] ?? 0) + $quantity;
            }

            // 5. Validasi batch dan riwayat retur yang sudah posted.
            ksort($batchQuantities);

            foreach ($batchQuantities as $batchId => $quantity) {
                $batch = $this->db->query(
                    'SELECT pb.*
                 FROM product_batches pb
                 INNER JOIN goods_receipt_items gri
                    ON gri.id = pb.goods_receipt_item_id
                 WHERE pb.id = ?
                   AND gri.goods_receipt_id = ?
                 FOR UPDATE',
                    [
                        $batchId,
                        $receiptId,
                    ]
                )->getRowArray();

                if (!$batch) {
                    throw new RuntimeException(
                        'Batch tidak ditemukan atau bukan bagian dari penerimaan ini.'
                    );
                }

                $previousReturn = $this->db->query(
                    'SELECT COALESCE(SUM(pri.quantity), 0) AS total
                 FROM purchase_return_items pri
                 INNER JOIN purchase_returns pr
                    ON pr.id = pri.purchase_return_id
                 WHERE pri.batch_id = ?
                   AND pr.status = ?',
                    [
                        $batchId,
                        'posted',
                    ]
                )->getRowArray();

                $alreadyReturned = (float) ($previousReturn['total'] ?? 0);
                $receivedQuantity = (float) $batch['received_quantity'];

                if (
                    $alreadyReturned + $quantity
                    > $receivedQuantity + 0.0001
                ) {
                    throw new RuntimeException(
                        'Jumlah retur melebihi jumlah batch yang diterima.'
                    );
                }
            }

            // 6. Hitung total nilai retur.
            $totalAmount = array_sum(
                array_column($items, 'subtotal')
            );

            // 7. Simpan header.
            $this->db->table('purchase_returns')->insert([
                'return_number' => $data['return_number'],
                'supplier_id' => $supplierId,
                'goods_receipt_id' => $receiptId,
                'return_date' => $data['return_date'],
                'status' => 'draft',
                'reason' => $data['reason'] ?? null,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $returnId = (int) $this->db->insertID();

            if ($returnId <= 0) {
                throw new RuntimeException(
                    'Gagal membuat dokumen retur pembelian.'
                );
            }

            // 8. Simpan detail.
            foreach ($items as $item) {
                $item['purchase_return_id'] = $returnId;

                $this->db->table('purchase_return_items')->insert($item);
            }

            $this->completeTransaction();

            return $returnId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }


    /**
     * Posting retur pembelian.
     * Mengurangi stok batch dan mencatat stock movement keluar.
     */

    public function postPurchaseReturn(
        int $returnId,
        int $userId
    ): array {
        $this->db->transBegin();

        try {
            // 1. Kunci dokumen retur.
            $return = $this->db->query(
                'SELECT * FROM purchase_returns
             WHERE id = ?
             FOR UPDATE',
                [$returnId]
            )->getRowArray();

            if (!$return || $return['status'] !== 'draft') {
                throw new RuntimeException(
                    'Retur pembelian tidak ditemukan atau bukan draft.'
                );
            }

            // 2. Kunci dokumen penerimaan asal.
            $receipt = $this->db->query(
                'SELECT * FROM goods_receipts
             WHERE id = ?
             FOR UPDATE',
                [(int) $return['goods_receipt_id']]
            )->getRowArray();

            if (!$receipt || $receipt['status'] !== 'posted') {
                throw new RuntimeException(
                    'Penerimaan barang asal tidak ditemukan atau belum diposting.'
                );
            }

            // 3. Ambil dan kunci detail retur.
            $items = $this->db->query(
                'SELECT * FROM purchase_return_items
             WHERE purchase_return_id = ?
             ORDER BY batch_id ASC, id ASC
             FOR UPDATE',
                [$returnId]
            )->getResultArray();

            if (!$items) {
                throw new RuntimeException('Detail retur pembelian kosong.');
            }

            $totalReturned = 0.0;

            foreach ($items as $item) {
                $receiptItemId = (int) $item['goods_receipt_item_id'];
                $batchId = (int) $item['batch_id'];
                $quantity = (float) $item['quantity'];

                if ($quantity <= 0) {
                    throw new RuntimeException(
                        'Jumlah retur harus lebih besar dari nol.'
                    );
                }

                // 4. Pastikan detail penerimaan berasal dari receipt retur.
                $receiptItem = $this->db->query(
                    'SELECT * FROM goods_receipt_items
                 WHERE id = ?
                   AND goods_receipt_id = ?
                 FOR UPDATE',
                    [
                        $receiptItemId,
                        (int) $receipt['id'],
                    ]
                )->getRowArray();

                if (!$receiptItem) {
                    throw new RuntimeException(
                        'Detail retur tidak sesuai dengan penerimaan asal.'
                    );
                }

                // 5. Kunci batch.
                $batch = $this->db->query(
                    'SELECT * FROM product_batches
                 WHERE id = ?
                   AND goods_receipt_item_id = ?
                 FOR UPDATE',
                    [
                        $batchId,
                        $receiptItemId,
                    ]
                )->getRowArray();

                if (!$batch) {
                    throw new RuntimeException(
                        'Batch tidak ditemukan atau tidak sesuai dengan detail penerimaan.'
                    );
                }

                if ($batch['status'] !== 'available') {
                    throw new RuntimeException(
                        'Batch tidak tersedia untuk retur pembelian.'
                    );
                }

                // 6. Validasi jumlah retur terdahulu pada batch yang sama.
                $previousReturn = $this->db->query(
                    'SELECT COALESCE(SUM(pri.quantity), 0) AS total
                 FROM purchase_return_items pri
                 INNER JOIN purchase_returns pr
                    ON pr.id = pri.purchase_return_id
                 WHERE pri.batch_id = ?
                   AND pr.status = ?
                   AND pr.id != ?',
                    [
                        $batchId,
                        'posted',
                        $returnId,
                    ]
                )->getRowArray();

                $alreadyReturned = (float) ($previousReturn['total'] ?? 0);
                $receivedQuantity = (float) $batch['received_quantity'];

                if (
                    $alreadyReturned + $quantity
                    > $receivedQuantity + 0.0001
                ) {
                    throw new RuntimeException(
                        'Jumlah retur melebihi jumlah batch yang diterima.'
                    );
                }

                // 7. Validasi stok tersisa.
                $available = (float) $batch['quantity_remaining'];

                if ($quantity > $available + 0.0001) {
                    throw new RuntimeException(
                        'Stok batch tidak mencukupi untuk retur pembelian.'
                    );
                }

                // 8. Kurangi stok batch.
                $newBalance = $available - $quantity;

                $this->db->table('product_batches')
                    ->where('id', $batchId)
                    ->where('quantity_remaining >=', $quantity)
                    ->update([
                        'quantity_remaining' => $newBalance,
                    ]);

                if ($this->db->affectedRows() !== 1) {
                    throw new RuntimeException(
                        'Gagal memperbarui stok batch.'
                    );
                }

                // 9. Catat stock movement.
                $this->insertStockMovement([
                    'product_id' => (int) $batch['product_id'],
                    'batch_id' => $batchId,
                    'movement_date' => $return['return_date'],
                    'movement_type' => 'out',
                    'reference_type' => 'purchase_return',
                    'reference_id' => $returnId,
                    'quantity_in' => 0,
                    'quantity_out' => $quantity,
                    'unit_cost' => (float) $item['unit_cost'],
                    'notes' => 'Purchase Return ' . $return['return_number'],
                    'created_by' => $userId,
                ]);

                $totalReturned += $quantity;
            }

            // 10. Posting dokumen retur.
            $this->db->table('purchase_returns')
                ->where('id', $returnId)
                ->update([
                    'status' => 'posted',
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->completeTransaction();

            return [
                'return_id' => $returnId,
                'status' => 'posted',
                'total_items' => count($items),
                'total_returned_quantity' => $totalReturned,
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }


    /**
     * Membuat draft retur penjualan.
     *
     * Format items:
     * [
     *   [
     *      'delivery_item_id' => 1,
     *      'quantity' => 1,
     *      'restockable' => true,
     *      'condition_notes' => 'Kemasan masih baik',
     *   ],
     * ]
     */
    public function createSalesReturn(
        array $data,
        int $userId
    ): int {
        $this->validateHeader($data, [
            'return_number',
            'delivery_id',
            'return_date',
            'items',
        ]);

        $this->db->transBegin();

        try {
            $deliveryId = (int) $data['delivery_id'];

            $delivery = $this->db->query(
                'SELECT * FROM deliveries WHERE id = ? FOR UPDATE',
                [$deliveryId]
            )->getRowArray();

            if (!$delivery || $delivery['status'] !== 'posted') {
                throw new RuntimeException(
                    'Pengiriman tidak ditemukan atau belum diposting.'
                );
            }

            $order = $this->db->table('distribution_orders')
                ->where('id', (int) $delivery['distribution_order_id'])
                ->get()
                ->getRowArray();

            if (!$order) {
                throw new RuntimeException('Pesanan distribusi asal tidak ditemukan.');
            }

            $items = $this->prepareSalesReturnItems(
                $deliveryId,
                $data['items']
            );

            $totalAmount = array_sum(array_column($items, 'subtotal'));

            $this->db->table('sales_returns')->insert([
                'return_number' => $data['return_number'],
                'customer_id' => (int) $order['customer_id'],
                'delivery_id' => $deliveryId,
                'return_date' => $data['return_date'],
                'status' => 'draft',
                'reason' => $data['reason'] ?? null,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $returnId = (int) $this->db->insertID();

            foreach ($items as $item) {
                $item['sales_return_id'] = $returnId;

                $this->db->table('sales_return_items')->insert($item);
            }

            $this->completeTransaction();

            return $returnId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Posting retur penjualan.
     *
     * Barang hanya masuk kembali ke stok tersedia jika restockable = true.
     */

    public function postSalesReturn(
        int $returnId,
        int $userId
    ): array {
        $this->db->transBegin();

        try {
            $return = $this->db->query(
                'SELECT * FROM sales_returns WHERE id = ? FOR UPDATE',
                [$returnId]
            )->getRowArray();

            if (!$return || $return['status'] !== 'draft') {
                throw new RuntimeException(
                    'Retur penjualan tidak ditemukan atau bukan draft.'
                );
            }

            $items = $this->db->query(
                'SELECT * FROM sales_return_items
             WHERE sales_return_id = ?
             ORDER BY delivery_item_id ASC
             FOR UPDATE',
                [$returnId]
            )->getResultArray();

            if (!$items) {
                throw new RuntimeException('Detail retur penjualan kosong.');
            }

            $restockedQuantity = 0.0;

            foreach ($items as $item) {
                $deliveryItemId = (int) $item['delivery_item_id'];
                $batchId = (int) $item['batch_id'];
                $quantity = (float) $item['quantity'];

                if ($quantity <= 0) {
                    throw new RuntimeException(
                        'Jumlah retur harus lebih besar dari nol.'
                    );
                }

                // 1. Kunci detail pengiriman asal.
                // Lock ini juga membantu mencegah retur bersamaan
                // melebihi jumlah barang yang pernah dikirim.
                $deliveryItem = $this->db->query(
                    'SELECT * FROM delivery_items
                 WHERE id = ?
                   AND delivery_id = ?
                 FOR UPDATE',
                    [
                        $deliveryItemId,
                        (int) $return['delivery_id'],
                    ]
                )->getRowArray();

                if (!$deliveryItem) {
                    throw new RuntimeException(
                        'Detail barang bukan bagian dari pengiriman asal.'
                    );
                }

                // 2. Validasi total retur terdahulu untuk detail pengiriman ini.
                // Berlaku untuk barang restockable maupun non-restockable.
                $previousReturn = $this->db->query(
                    'SELECT COALESCE(SUM(sri.quantity), 0) AS total
                 FROM sales_return_items sri
                 INNER JOIN sales_returns sr
                    ON sr.id = sri.sales_return_id
                 WHERE sri.delivery_item_id = ?
                   AND sr.status = ?
                   AND sr.id != ?',
                    [
                        $deliveryItemId,
                        'posted',
                        $returnId,
                    ]
                )->getRowArray();

                $alreadyReturned = (float) ($previousReturn['total'] ?? 0);
                $dispatchedQuantity = (float) $deliveryItem['quantity_dispatched'];

                if (
                    $alreadyReturned + $quantity
                    > $dispatchedQuantity + 0.0001
                ) {
                    throw new RuntimeException(
                        'Jumlah retur melebihi jumlah barang yang dikirim.'
                    );
                }

                // 3. Validasi batch asal.
                $batch = $this->db->query(
                    'SELECT * FROM product_batches
                 WHERE id = ?
                 FOR UPDATE',
                    [$batchId]
                )->getRowArray();

                if (
                    !$batch
                    || (int) $batch['id'] !== (int) $deliveryItem['batch_id']
                ) {
                    throw new RuntimeException(
                        'Batch retur tidak sesuai dengan pengiriman asal.'
                    );
                }

                // 4. Barang hanya masuk stok jika layak dijual kembali.
                if ((int) $item['restockable'] === 1) {
                    if (
                        !empty($batch['expired_at'])
                        && $batch['expired_at'] < date('Y-m-d')
                    ) {
                        throw new RuntimeException(
                            'Batch kedaluwarsa tidak dapat dikembalikan ke stok tersedia.'
                        );
                    }

                    if ($batch['status'] !== 'available') {
                        throw new RuntimeException(
                            'Status batch tidak memungkinkan penambahan stok.'
                        );
                    }

                    $newBalance =
                        (float) $batch['quantity_remaining'] + $quantity;

                    $this->db->table('product_batches')
                        ->where('id', $batchId)
                        ->update([
                            'quantity_remaining' => $newBalance,
                        ]);

                    $this->insertStockMovement([
                        'product_id' => (int) $batch['product_id'],
                        'batch_id' => $batchId,
                        'movement_date' => $return['return_date'],
                        'movement_type' => 'in',
                        'reference_type' => 'sales_return',
                        'reference_id' => $returnId,
                        'quantity_in' => $quantity,
                        'quantity_out' => 0,
                        'unit_cost' => (float) $batch['unit_cost'],
                        'notes' => 'Sales Return ' . $return['return_number'],
                        'created_by' => $userId,
                    ]);

                    $restockedQuantity += $quantity;
                }
            }

            // 5. Posting dokumen retur.
            $this->db->table('sales_returns')
                ->where('id', $returnId)
                ->update([
                    'status' => 'posted',
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->completeTransaction();

            return [
                'return_id' => $returnId,
                'status' => 'posted',
                'total_items' => count($items),
                'restocked_quantity' => $restockedQuantity,
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Daftar Sales Return.
     */
    public function getSalesReturns(): array
    {
        $builder = $this->db->table('sales_returns sr');

        $rows = $builder
            ->select([
                'sr.*',
                '(SELECT COUNT(*) FROM sales_return_items sri
              WHERE sri.sales_return_id = sr.id) AS total_items',
                '(SELECT COALESCE(SUM(sri.quantity), 0)
              FROM sales_return_items sri
              WHERE sri.sales_return_id = sr.id) AS total_quantity',
                '(SELECT COALESCE(SUM(sri.quantity), 0)
              FROM sales_return_items sri
              WHERE sri.sales_return_id = sr.id
              AND sri.restockable = 1) AS restockable_quantity',
            ])
            ->orderBy('sr.return_date', 'DESC')
            ->orderBy('sr.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'items' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * Detail Sales Return.
     */
    public function getSalesReturnDetail(int $returnId): array
    {
        $return = $this->db
            ->table('sales_returns')
            ->where('id', $returnId)
            ->get()
            ->getRowArray();

        if (!$return) {
            throw new RuntimeException('Data sales return tidak ditemukan.');
        }

        $items = $this->db
            ->table('sales_return_items')
            ->where('sales_return_id', $returnId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'return' => $return,
            'items' => $items,
        ];
    }


    /**
     * Menyiapkan detail retur pembelian.
     */

    private function preparePurchaseReturnItems(
        int $receiptId,
        array $items
    ): array {
        if (!$items) {
            throw new RuntimeException(
                'Detail retur pembelian wajib diisi.'
            );
        }

        $prepared = [];
        $seen = [];

        foreach ($items as $item) {
            $receiptItemId = (int) ($item['goods_receipt_item_id'] ?? 0);
            $batchId = (int) ($item['batch_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);

            if (
                $receiptItemId <= 0 ||
                $batchId <= 0 ||
                !is_finite($quantity) ||
                $quantity <= 0
            ) {
                throw new RuntimeException(
                    'Detail retur pembelian tidak valid.'
                );
            }

            $key = $receiptItemId . ':' . $batchId;

            if (isset($seen[$key])) {
                throw new RuntimeException(
                    'Batch yang sama tidak boleh berulang dalam satu retur.'
                );
            }

            $seen[$key] = true;

            // 1. Validasi detail penerimaan.
            $receiptItem = $this->db->query(
                'SELECT *
             FROM goods_receipt_items
             WHERE id = ?
               AND goods_receipt_id = ?
             FOR UPDATE',
                [
                    $receiptItemId,
                    $receiptId,
                ]
            )->getRowArray();

            if (!$receiptItem) {
                throw new RuntimeException(
                    'Detail penerimaan tidak sesuai dengan dokumen asal.'
                );
            }

            // 2. Validasi batch.
            $batch = $this->db->query(
                'SELECT *
             FROM product_batches
             WHERE id = ?
               AND goods_receipt_item_id = ?
             FOR UPDATE',
                [
                    $batchId,
                    $receiptItemId,
                ]
            )->getRowArray();

            if (!$batch) {
                throw new RuntimeException(
                    'Batch tidak terhubung dengan detail penerimaan.'
                );
            }

            // 3. Validasi status batch.
            if ($batch['status'] !== 'available') {
                throw new RuntimeException(
                    'Batch tidak tersedia untuk retur pembelian.'
                );
            }

            // 4. Validasi kuantitas.
            $available = (float) $batch['quantity_remaining'];

            if ($quantity > $available + 0.0001) {
                throw new RuntimeException(
                    'Jumlah retur melebihi stok batch yang tersedia.'
                );
            }

            // 5. Gunakan HPP batch.
            $unitCost = (float) $batch['unit_cost'];

            if ($unitCost < 0) {
                throw new RuntimeException(
                    'Harga pokok batch tidak valid.'
                );
            }

            $prepared[] = [
                'goods_receipt_item_id' => $receiptItemId,
                'batch_id' => $batchId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'subtotal' => $quantity * $unitCost,
                'notes' => $item['notes'] ?? null,
            ];
        }

        return $prepared;
    }

    /**
     * Menyiapkan detail retur penjualan.
     */
    private function prepareSalesReturnItems(
        int $deliveryId,
        array $items
    ): array {
        if ($deliveryId <= 0 || empty($items)) {
            throw new RuntimeException(
                'Detail retur penjualan wajib diisi.'
            );
        }

        $prepared = [];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException(
                    'Format detail retur penjualan tidak valid.'
                );
            }

            // Validate delivery item ID
            $rawDeliveryItemId = $item['delivery_item_id'] ?? null;

            if (
                !is_int($rawDeliveryItemId)
                && !(
                    is_string($rawDeliveryItemId)
                    && preg_match('/^[1-9][0-9]*$/', $rawDeliveryItemId)
                )
            ) {
                throw new RuntimeException(
                    'ID detail pengiriman tidak valid.'
                );
            }

            $deliveryItemId = (int) $rawDeliveryItemId;

            if ($deliveryItemId <= 0) {
                throw new RuntimeException(
                    'ID detail pengiriman tidak valid.'
                );
            }

            // Validate quantity
            $rawQuantity = $item['quantity'] ?? null;

            if (
                !is_numeric($rawQuantity)
                || is_bool($rawQuantity)
            ) {
                throw new RuntimeException(
                    'Kuantitas retur tidak valid.'
                );
            }

            $quantity = (float) $rawQuantity;

            if (!is_finite($quantity) || $quantity <= 0) {
                throw new RuntimeException(
                    'Kuantitas retur harus lebih besar dari nol.'
                );
            }

            // Prevent duplicate delivery items
            if (isset($seen[$deliveryItemId])) {
                throw new RuntimeException(
                    'Detail pengiriman tidak boleh berulang.'
                );
            }

            $seen[$deliveryItemId] = true;

            // Lock and validate delivery item
            $deliveryItem = $this->db->query(
                'SELECT *
             FROM delivery_items
             WHERE id = ?
               AND delivery_id = ?
             FOR UPDATE',
                [$deliveryItemId, $deliveryId]
            )->getRowArray();

            if (!$deliveryItem) {
                throw new RuntimeException(
                    'Detail barang bukan bagian dari pengiriman asal.'
                );
            }

            // Validate historical unit price
            $unitPrice = (float) $deliveryItem['unit_price'];

            if (!is_finite($unitPrice) || $unitPrice < 0) {
                throw new RuntimeException(
                    'Harga satuan pada detail pengiriman tidak valid.'
                );
            }

            $subtotal = $quantity * $unitPrice;

            if (!is_finite($subtotal)) {
                throw new RuntimeException(
                    'Subtotal retur penjualan tidak valid.'
                );
            }

            // Validate restockable flag
            $rawRestockable = $item['restockable'] ?? 0;

            if (in_array($rawRestockable, [true, 1, '1'], true)) {
                $restockable = 1;
            } elseif (in_array($rawRestockable, [false, 0, '0'], true)) {
                $restockable = 0;
            } else {
                throw new RuntimeException(
                    'Status restock barang tidak valid.'
                );
            }

            // Validate condition notes
            $conditionNotes = $item['condition_notes'] ?? null;

            if ($conditionNotes !== null && !is_string($conditionNotes)) {
                throw new RuntimeException(
                    'Catatan kondisi barang tidak valid.'
                );
            }

            $conditionNotes = $conditionNotes !== null
                ? trim($conditionNotes)
                : null;

            if ($conditionNotes === '') {
                $conditionNotes = null;
            }

            $prepared[] = [
                'delivery_item_id' => $deliveryItemId,
                'batch_id' => (int) $deliveryItem['batch_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'restockable' => $restockable,
                'condition_notes' => $conditionNotes,
            ];
        }

        return $prepared;
    }

    /**
     * Mengambil daftar Purchase Return.
     */
    public function getPurchaseReturns(): array
    {
        return $this->db
            ->table('purchase_returns pr')
            ->select([
                'pr.id',
                'pr.return_number',
                'pr.supplier_id',
                's.name AS supplier_name',
                'pr.goods_receipt_id',
                'gr.receipt_number',
                'pr.return_date',
                'pr.status',
                'pr.reason',
                'pr.total_amount',
                'pr.notes',
                'pr.created_at',
                'pr.updated_at',
            ])
            ->join('suppliers s', 's.id = pr.supplier_id', 'left')
            ->join(
                'goods_receipts gr',
                'gr.id = pr.goods_receipt_id',
                'left'
            )
            ->orderBy('pr.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Mengambil detail Purchase Return beserta item.
     */
    public function getPurchaseReturnDetail(int $returnId): array
    {
        if ($returnId < 1) {
            throw new RuntimeException('Purchase Return ID tidak valid.');
        }

        $return = $this->db
            ->table('purchase_returns pr')
            ->select([
                'pr.*',
                's.name AS supplier_name',
                'gr.receipt_number',
            ])
            ->join('suppliers s', 's.id = pr.supplier_id', 'left')
            ->join(
                'goods_receipts gr',
                'gr.id = pr.goods_receipt_id',
                'left'
            )
            ->where('pr.id', $returnId)
            ->get()
            ->getRowArray();

        if (!$return) {
            throw new RuntimeException('Purchase Return tidak ditemukan.');
        }

        $items = $this->db
            ->table('purchase_return_items pri')
            ->select([
                'pri.id',
                'pri.purchase_return_id',
                'pri.goods_receipt_item_id',
                'pri.batch_id',
                'pri.quantity',
                'pri.unit_cost',
                'pri.subtotal',
                'pri.notes',
                'p.name AS product_name',
                'p.sku',
                'b.batch_number',
            ])
            ->join(
                'goods_receipt_items gri',
                'gri.id = pri.goods_receipt_item_id',
                'left'
            )
            ->join('products p', 'p.id = gri.product_id', 'left')
            ->join('batches b', 'b.id = pri.batch_id', 'left')
            ->where('pri.purchase_return_id', $returnId)
            ->orderBy('pri.id', 'ASC')
            ->get()
            ->getResultArray();

        $return['items'] = $items;

        return $return;
    }

    private function insertStockMovement(array $data): void
    {
        $this->db->table('stock_movements')->insert([
            'product_id' => $data['product_id'],
            'batch_id' => $data['batch_id'],
            'movement_date' => $data['movement_date'],
            'movement_type' => $data['movement_type'],
            'reference_type' => $data['reference_type'],
            'reference_id' => $data['reference_id'],
            'quantity_in' => $data['quantity_in'],
            'quantity_out' => $data['quantity_out'],
            'unit_cost' => $data['unit_cost'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function validateHeader(
        array $data,
        array $required
    ): void {
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === []) {
                throw new RuntimeException("Field {$field} wajib diisi.");
            }
        }

        if (!is_array($data['items'])) {
            throw new RuntimeException('Detail retur harus berupa array.');
        }
    }

    private function completeTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Transaksi database gagal.');
        }

        $this->db->transCommit();
    }
}
