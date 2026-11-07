<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use Modules\Pharmacy\Models\Inventory\ProductBatchModel;
use Modules\Pharmacy\Models\Inventory\StockMovementModel;
use Modules\Pharmacy\Models\Procurement\GoodsReceiptItemModel;
use Modules\Pharmacy\Models\Procurement\GoodsReceiptModel;
use Modules\Pharmacy\Models\Procurement\PurchaseOrderModel;
use Modules\Pharmacy\Models\Inventory\StockOpnameModel;
use Modules\Pharmacy\Models\Inventory\StockOpnameItemModel;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class InventoryService
{
    protected $db;

    protected GoodsReceiptModel $goodsReceiptModel;
    protected GoodsReceiptItemModel $goodsReceiptItemModel;
    protected ProductBatchModel $productBatchModel;
    protected StockMovementModel $stockMovementModel;
    protected PurchaseOrderModel $purchaseOrderModel;
    protected StockOpnameModel $stockOpnameModel;
    protected StockOpnameItemModel $stockOpnameItemModel;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');

        $this->goodsReceiptModel = new GoodsReceiptModel();
        $this->goodsReceiptItemModel = new GoodsReceiptItemModel();
        $this->productBatchModel = new ProductBatchModel();
        $this->stockMovementModel = new StockMovementModel();
        $this->purchaseOrderModel = new PurchaseOrderModel();
        $this->stockOpnameModel = new StockOpnameModel();
        $this->stockOpnameItemModel = new StockOpnameItemModel();
    }

    public function getStockOverview(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 10)));
        $search = trim((string) ($filters['search'] ?? ''));

        $countBuilder = $this->db->table('products p');

        if ($search !== '') {
            $countBuilder->groupStart()
                ->like('p.sku', $search)
                ->orLike('p.barcode', $search)
                ->orLike('p.name', $search)
                ->groupEnd();
        }

        $total = $countBuilder->countAllResults();

        $builder = $this->db->table('products p')
            ->select("
            p.id AS product_id,
            p.sku,
            p.barcode,
            p.name AS product_name,
            COALESCE(SUM(
                CASE
                    WHEN pb.status = 'available'
                    AND (
                        pb.expired_at IS NULL
                        OR pb.expired_at >= CURDATE()
                    )
                    THEN pb.quantity_remaining
                    ELSE 0
                END
            ), 0) AS available_stock,
            COALESCE(SUM(
                CASE
                    WHEN pb.quantity_remaining > 0
                    AND pb.expired_at < CURDATE()
                    THEN pb.quantity_remaining
                    ELSE 0
                END
            ), 0) AS expired_stock,
            COUNT(pb.id) AS batch_count
        ", false)
            ->join('product_batches pb', 'pb.product_id = p.id', 'left');

        if ($search !== '') {
            $builder->groupStart()
                ->like('p.sku', $search)
                ->orLike('p.barcode', $search)
                ->orLike('p.name', $search)
                ->groupEnd();
        }

        $items = $builder
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => $this->paginationData(
                $page,
                $perPage,
                $total
            ),
        ];
    }

    public function getBatches(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 10)));

        $builder = $this->db->table('product_batches pb')
            ->join('products p', 'p.id = pb.product_id')
            ->select('
            pb.*,
            p.sku,
            p.barcode,
            p.name AS product_name
        ');

        if (!empty($filters['product_id'])) {
            $builder->where('pb.product_id', (int) $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $builder->where('pb.status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $builder->groupStart()
                ->like('pb.batch_number', $search)
                ->orLike('p.sku', $search)
                ->orLike('p.name', $search)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('pb.expired_at', 'ASC')
            ->orderBy('pb.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => $this->paginationData(
                $page,
                $perPage,
                $total
            ),
        ];
    }

    public function getBatchDetail(int $batchId): array
    {
        $batch = $this->db->table('product_batches pb')
            ->select('
            pb.*,
            p.sku,
            p.barcode,
            p.name AS product_name
        ')
            ->join('products p', 'p.id = pb.product_id')
            ->where('pb.id', $batchId)
            ->get()
            ->getRowArray();

        if (!$batch) {
            throw new RuntimeException('Product batch tidak ditemukan.');
        }

        return $batch;
    }

    public function getMovements(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 10)));

        $builder = $this->db->table('stock_movements sm')
            ->select('
            sm.*,
            p.sku,
            p.name AS product_name,
            pb.batch_number
        ')
            ->join('products p', 'p.id = sm.product_id')
            ->join('product_batches pb', 'pb.id = sm.batch_id', 'left');

        if (!empty($filters['product_id'])) {
            $builder->where('sm.product_id', (int) $filters['product_id']);
        }

        if (!empty($filters['movement_type'])) {
            $builder->where('sm.movement_type', $filters['movement_type']);
        }

        if (!empty($filters['reference_type'])) {
            $builder->where('sm.reference_type', $filters['reference_type']);
        }

        if (!empty($filters['reference_id'])) {
            $builder->where('sm.reference_id', (int) $filters['reference_id']);
        }

        if (!empty($filters['date_from'])) {
            $builder->where('sm.movement_date >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $builder->where('sm.movement_date <=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $builder->groupStart()
                ->like('p.sku', $search)
                ->orLike('p.name', $search)
                ->orLike('pb.batch_number', $search)
                ->orLike('sm.notes', $search)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('sm.movement_date', 'DESC')
            ->orderBy('sm.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => $this->paginationData(
                $page,
                $perPage,
                $total
            ),
        ];
    }

    public function getOpnames(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 10)));

        $builder = $this->db->table('stock_opnames');

        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $builder->like('opname_number', trim((string) $filters['search']));
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('opname_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => $this->paginationData(
                $page,
                $perPage,
                $total
            ),
        ];
    }

    public function getOpnameDetail(int $id): array
    {
        $opname = $this->db->table('stock_opnames')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if (!$opname) {
            throw new RuntimeException('Stock Opname tidak ditemukan.');
        }

        $items = $this->db->table('stock_opname_items soi')
            ->select('
            soi.*,
            p.sku,
            p.name AS product_name,
            pb.batch_number,
            pb.expired_at
        ')
            ->join('products p', 'p.id = soi.product_id')
            ->join('product_batches pb', 'pb.id = soi.batch_id', 'left')
            ->where('soi.stock_opname_id', $id)
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'opname' => $opname,
            'items' => $items,
        ];
    }
    private function paginationData(
        int $page,
        int $perPage,
        int $total
    ): array {
        $totalPages = (int) ceil($total / $perPage);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
        ];
    }

    public function createStockOpname(
        array $data,
        int $userId
    ): int {
        $number = trim((string) ($data['opname_number'] ?? ''));
        $date = trim((string) ($data['opname_date'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($number === '') {
            throw new InvalidArgumentException(
                'Nomor stock opname wajib diisi.'
            );
        }

        if (!$this->isValidDate($date)) {
            throw new InvalidArgumentException(
                'Tanggal stock opname tidak valid.'
            );
        }

        $exists = $this->db->table('stock_opnames')
            ->where('opname_number', $number)
            ->countAllResults();

        if ($exists > 0) {
            throw new InvalidArgumentException(
                'Nomor stock opname sudah digunakan.'
            );
        }

        $this->db->transBegin();

        try {
            $batches = $this->db->table('product_batches')
                ->select([
                    'id',
                    'product_id',
                    'quantity_remaining',
                ])
                ->where('status', 'available')
                ->orderBy('product_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            if (empty($batches)) {
                throw new RuntimeException(
                    'Tidak ada batch tersedia untuk stock opname.'
                );
            }

            $opnameId = $this->stockOpnameModel->insert([
                'opname_number' => $number,
                'opname_date'   => $date,
                'status'        => 'draft',
                'notes'         => $notes !== '' ? $notes : null,
                'created_by'    => $userId,
                'updated_by'    => $userId,
            ], true);

            if (!$opnameId) {
                throw new RuntimeException(
                    'Gagal membuat stock opname.'
                );
            }

            $items = [];

            foreach ($batches as $batch) {
                $items[] = [
                    'stock_opname_id' => (int) $opnameId,
                    'product_id'      => (int) $batch['product_id'],
                    'batch_id'        => (int) $batch['id'],
                    'system_quantity' => (float) $batch['quantity_remaining'],
                    'counted_quantity' => null,
                    'difference_quantity' => null,
                    'notes' => null,
                ];
            }

            $inserted = $this->stockOpnameItemModel->insertBatch($items);

            if (!$inserted) {
                throw new RuntimeException(
                    'Gagal membuat item stock opname.'
                );
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Transaksi stock opname gagal.'
                );
            }

            $this->db->transCommit();

            return (int) $opnameId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }


    public function saveStockOpnameCounts(
        int $opnameId,
        array $data,
        int $userId
    ): array {
        $items = $data['items'] ?? [];

        if (!is_array($items) || empty($items)) {
            throw new InvalidArgumentException(
                'Data penghitungan stock opname wajib diisi.'
            );
        }

        $this->db->transBegin();

        try {
            $opname = $this->db->table('stock_opnames')
                ->where('id', $opnameId)
                ->get()
                ->getRowArray();

            if (!$opname) {
                throw new RuntimeException(
                    'Stock opname tidak ditemukan.'
                );
            }

            if ($opname['status'] !== 'draft') {
                throw new RuntimeException(
                    'Stock opname bukan berstatus draft.'
                );
            }

            $allowedItems = [];

            foreach ($items as $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                $quantity = $item['counted_quantity'] ?? null;

                if ($itemId <= 0) {
                    throw new InvalidArgumentException(
                        'Item stock opname tidak valid.'
                    );
                }

                if (
                    $quantity === null ||
                    !is_numeric($quantity) ||
                    !is_finite((float) $quantity) ||
                    (float) $quantity < 0
                ) {
                    throw new InvalidArgumentException(
                        'Jumlah fisik harus berupa angka nol atau lebih.'
                    );
                }

                if (isset($allowedItems[$itemId])) {
                    throw new InvalidArgumentException(
                        'Item stock opname dikirim lebih dari satu kali.'
                    );
                }

                $allowedItems[$itemId] = [
                    'counted_quantity' => round((float) $quantity, 3),
                    'notes' => isset($item['notes'])
                        ? trim((string) $item['notes'])
                        : null,
                ];
            }

            $existingItems = $this->db->table('stock_opname_items')
                ->where('stock_opname_id', $opnameId)
                ->get()
                ->getResultArray();

            $existingIds = array_map(
                static fn(array $row): int => (int) $row['id'],
                $existingItems
            );

            foreach ($allowedItems as $itemId => $count) {
                if (!in_array($itemId, $existingIds, true)) {
                    throw new InvalidArgumentException(
                        'Item tidak termasuk dalam stock opname ini.'
                    );
                }

                $item = $this->db->table('stock_opname_items')
                    ->where('id', $itemId)
                    ->where('stock_opname_id', $opnameId)
                    ->get()
                    ->getRowArray();

                $difference = round(
                    $count['counted_quantity']
                        - (float) $item['system_quantity'],
                    3
                );

                $updated = $this->stockOpnameItemModel->update(
                    $itemId,
                    [
                        'counted_quantity' => $count['counted_quantity'],
                        'difference_quantity' => $difference,
                        'notes' => $count['notes'],
                    ]
                );

                if (!$updated) {
                    throw new RuntimeException(
                        'Gagal menyimpan hasil penghitungan item.'
                    );
                }
            }

            $this->stockOpnameModel->update($opnameId, [
                'updated_by' => $userId,
            ]);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan penghitungan stock opname.'
                );
            }

            $this->db->transCommit();

            return [
                'opname_id' => $opnameId,
                'updated_items' => count($allowedItems),
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }


    public function postStockOpname(
        int $opnameId,
        int $userId
    ): array {
        $this->db->transBegin();

        try {
            // Lock header agar tidak diposting dua kali.
            $opname = $this->db->query(
                'SELECT * FROM stock_opnames WHERE id = ? FOR UPDATE',
                [$opnameId]
            )->getRowArray();

            if (!$opname) {
                throw new RuntimeException(
                    'Stock opname tidak ditemukan.'
                );
            }

            if ($opname['status'] !== 'draft') {
                throw new RuntimeException(
                    'Stock opname sudah diposting atau tidak berstatus draft.'
                );
            }

            // Lock semua item opname.
            $items = $this->db->query(
                'SELECT * FROM stock_opname_items
             WHERE stock_opname_id = ?
             ORDER BY id ASC
             FOR UPDATE',
                [$opnameId]
            )->getResultArray();

            if (empty($items)) {
                throw new RuntimeException(
                    'Stock opname tidak memiliki item.'
                );
            }

            foreach ($items as $item) {
                if ($item['counted_quantity'] === null) {
                    throw new RuntimeException(
                        'Masih ada item yang belum dihitung.'
                    );
                }

                $batchId = (int) $item['batch_id'];

                if ($batchId <= 0) {
                    throw new RuntimeException(
                        'Batch pada item stock opname tidak valid.'
                    );
                }

                // Lock batch untuk mencegah perubahan saldo bersamaan.
                $batch = $this->db->query(
                    'SELECT * FROM product_batches WHERE id = ? FOR UPDATE',
                    [$batchId]
                )->getRowArray();

                if (!$batch) {
                    throw new RuntimeException(
                        "Batch ID {$batchId} tidak ditemukan."
                    );
                }

                $systemQuantity = round(
                    (float) $item['system_quantity'],
                    3
                );

                $currentQuantity = round(
                    (float) $batch['quantity_remaining'],
                    3
                );

                $countedQuantity = round(
                    (float) $item['counted_quantity'],
                    3
                );

                // Cegah saldo yang sudah berubah sejak snapshot ditimpa.
                if ($currentQuantity !== $systemQuantity) {
                    throw new RuntimeException(
                        "Saldo batch {$batch['batch_number']} telah berubah " .
                            'sejak stock opname dibuat. Posting dibatalkan.'
                    );
                }

                $difference = round(
                    $countedQuantity - $systemQuantity,
                    3
                );

                // Perbarui saldo batch sesuai hasil hitung fisik.
                $updated = $this->productBatchModel->update(
                    $batchId,
                    [
                        'quantity_remaining' => $countedQuantity,
                    ]
                );

                if (!$updated) {
                    throw new RuntimeException(
                        "Gagal memperbarui saldo batch {$batch['batch_number']}."
                    );
                }

                // Tidak perlu membuat mutasi jika tidak ada selisih.
                if ($difference == 0.0) {
                    continue;
                }

                $movementType = $difference > 0 ? 'in' : 'out';

                $quantityIn = $difference > 0
                    ? $difference
                    : 0;

                $quantityOut = $difference < 0
                    ? abs($difference)
                    : 0;

                $movementInserted = $this->stockMovementModel->insert([
                    'product_id' => (int) $item['product_id'],
                    'batch_id' => $batchId,
                    'movement_date' => $opname['opname_date'] . ' 00:00:00',
                    'movement_type' => $movementType,
                    'reference_type' => 'stock_opname',
                    'reference_id' => $opnameId,
                    'quantity_in' => $quantityIn,
                    'quantity_out' => $quantityOut,
                    'unit_cost' => (float) $batch['unit_cost'],
                    'notes' => 'Penyesuaian Stock Opname ' .
                        $opname['opname_number'],
                    'created_by' => $userId,
                ]);

                if (!$movementInserted) {
                    throw new RuntimeException(
                        'Gagal mencatat mutasi stock opname.'
                    );
                }

                // Pastikan hasil selisih tersimpan.
                $this->stockOpnameItemModel->update(
                    (int) $item['id'],
                    [
                        'difference_quantity' => $difference,
                    ]
                );
            }

            $updated = $this->stockOpnameModel->update(
                $opnameId,
                [
                    'status' => 'posted',
                    'updated_by' => $userId,
                ]
            );

            if (!$updated || $this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyelesaikan posting stock opname.'
                );
            }

            $this->db->transCommit();

            return [
                'opname_id' => $opnameId,
                'opname_number' => $opname['opname_number'],
                'status' => 'posted',
                'total_items' => count($items),
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Post Goods Receipt and create product batches.
     *
     * $batchBreakdown format:
     *
     * [
     *     receipt_item_id => [
     *         [
     *             'batch_number' => 'BATCH-001',
     *             'expired_at'   => '2027-12-31',
     *             'quantity'     => 60,
     *         ],
     *     ],
     * ]
     */

    public function postGoodsReceipt(
        int $receiptId,
        array $batchBreakdown,
        int $userId
    ): array {
        $this->db->transBegin();

        try {
            // 1. Lock Goods Receipt.
            $receipt = $this->db->query(
                'SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE',
                [$receiptId]
            )->getRowArray();

            if (!$receipt) {
                throw new RuntimeException(
                    'Goods Receipt tidak ditemukan.'
                );
            }

            if ($receipt['status'] !== 'draft') {
                throw new RuntimeException(
                    'Goods Receipt harus berstatus draft.'
                );
            }

            $purchaseOrderId = (int) $receipt['purchase_order_id'];

            // 2. Lock Purchase Order.
            $purchaseOrder = $this->db->query(
                'SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE',
                [$purchaseOrderId]
            )->getRowArray();

            if (!$purchaseOrder) {
                throw new RuntimeException(
                    'Purchase Order tidak ditemukan.'
                );
            }

            // 3. Lock Goods Receipt Items.
            $items = $this->db->query(
                'SELECT * FROM goods_receipt_items
             WHERE goods_receipt_id = ?
             FOR UPDATE',
                [$receiptId]
            )->getResultArray();

            if (empty($items)) {
                throw new RuntimeException(
                    'Goods Receipt belum memiliki item.'
                );
            }

            // 4. Hitung penerimaan pada receipt ini.
            $currentReceived = [];

            foreach ($items as $item) {
                $poItemId = (int) ($item['purchase_order_item_id'] ?? 0);

                if ($poItemId <= 0) {
                    throw new RuntimeException(
                        'Receipt item harus terhubung dengan item Purchase Order.'
                    );
                }

                $currentReceived[$poItemId] =
                    ($currentReceived[$poItemId] ?? 0)
                    + (float) $item['quantity_received'];
            }

            // 5. Ambil kuantitas PO dan penerimaan yang sudah posted.
            $poItems = $this->db->query(
                'SELECT
                poi.id,
                poi.quantity,
                COALESCE(
                    SUM(
                        CASE
                            WHEN gr.status = "posted"
                             AND gr.id != ?
                            THEN gri.quantity_received
                            ELSE 0
                        END
                    ),
                    0
                ) AS posted_quantity
             FROM purchase_order_items poi
             LEFT JOIN goods_receipt_items gri
                ON gri.purchase_order_item_id = poi.id
             LEFT JOIN goods_receipts gr
                ON gr.id = gri.goods_receipt_id
             WHERE poi.purchase_order_id = ?
             GROUP BY poi.id, poi.quantity',
                [$receiptId, $purchaseOrderId]
            )->getResultArray();

            if (empty($poItems)) {
                throw new RuntimeException(
                    'Purchase Order belum memiliki item.'
                );
            }

            // 6. Validasi agar penerimaan tidak melebihi PO.
            foreach ($poItems as $poItem) {
                $poItemId = (int) $poItem['id'];

                $orderedQuantity = (float) $poItem['quantity'];
                $postedQuantity = (float) $poItem['posted_quantity'];
                $currentQuantity = (float) (
                    $currentReceived[$poItemId] ?? 0
                );

                $totalReceived = $postedQuantity + $currentQuantity;

                if ($totalReceived - $orderedQuantity > 0.0001) {
                    throw new RuntimeException(
                        "Jumlah penerimaan produk pada PO item {$poItemId} melebihi jumlah pesanan."
                    );
                }
            }

            // Pastikan tidak ada item receipt yang mengacu ke PO item lain.
            $validPoItemIds = array_map(
                static fn($item) => (int) $item['id'],
                $poItems
            );

            foreach (array_keys($currentReceived) as $poItemId) {
                if (!in_array($poItemId, $validPoItemIds, true)) {
                    throw new RuntimeException(
                        "Purchase Order item {$poItemId} tidak ditemukan."
                    );
                }
            }

            // 7. Buat batch dan stock movement.
            $createdBatches = [];

            foreach ($items as $item) {
                $itemId = (int) $item['id'];

                $batchRows = $batchBreakdown[$itemId] ?? [];

                if (!is_array($batchRows) || empty($batchRows)) {
                    throw new RuntimeException(
                        "Rincian batch untuk receipt item {$itemId} belum tersedia."
                    );
                }

                $receivedQuantity = (float) $item['quantity_received'];
                $allocatedQuantity = 0;

                foreach ($batchRows as $batch) {
                    if (!is_array($batch)) {
                        throw new RuntimeException(
                            'Format rincian batch tidak valid.'
                        );
                    }

                    $batchNumber = trim(
                        (string) ($batch['batch_number'] ?? '')
                    );

                    $quantity = (float) ($batch['quantity'] ?? 0);
                    $expiredAt = $batch['expired_at'] ?? null;

                    if ($batchNumber === '') {
                        throw new RuntimeException(
                            'Nomor batch wajib diisi.'
                        );
                    }

                    if (!is_finite($quantity) || $quantity <= 0) {
                        throw new RuntimeException(
                            'Jumlah batch harus lebih besar dari nol.'
                        );
                    }

                    if (
                        $expiredAt !== null &&
                        (
                            !is_string($expiredAt) ||
                            !$this->isValidDate($expiredAt)
                        )
                    ) {
                        throw new RuntimeException(
                            'Format tanggal kedaluwarsa tidak valid.'
                        );
                    }

                    $allocatedQuantity += $quantity;

                    // Create Product Batch.
                    $batchId = $this->productBatchModel->insert([
                        'product_id'          => $item['product_id'],
                        'goods_receipt_item_id' => $itemId,
                        'batch_number'        => $batchNumber,
                        'expired_at'          => $expiredAt,
                        'received_quantity'   => $quantity,
                        'quantity_remaining'  => $quantity,
                        'unit_cost'           => $item['unit_cost'],
                        'status'              => 'available',
                    ]);

                    if (!$batchId) {
                        throw new RuntimeException(
                            'Gagal membuat product batch.'
                        );
                    }

                    // Record Stock Movement.
                    $movementId = $this->stockMovementModel->insert([
                        'product_id'    => $item['product_id'],
                        'batch_id'      => $batchId,
                        'movement_date' => $receipt['received_date'],
                        'movement_type' => 'in',
                        'reference_type' => 'goods_receipt',
                        'reference_id'  => $receiptId,
                        'quantity_in'   => $quantity,
                        'quantity_out'  => 0,
                        'unit_cost'     => $item['unit_cost'],
                        'notes'         => 'Goods Receipt ' . $receipt['receipt_number'],
                        'created_by'    => $userId,
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);

                    if (!$movementId) {
                        throw new RuntimeException(
                            'Gagal mencatat stock movement.'
                        );
                    }

                    $createdBatches[] = (int) $batchId;
                }

                // Total batch harus sama dengan jumlah receipt item.
                if (
                    abs($allocatedQuantity - $receivedQuantity) > 0.0001
                ) {
                    throw new RuntimeException(
                        "Total batch tidak sama dengan jumlah penerimaan pada item {$itemId}."
                    );
                }
            }

            // 8. Update Goods Receipt menjadi posted.
            if (!$this->goodsReceiptModel->update($receiptId, [
                'status'     => 'posted',
                'updated_by' => $userId,
            ])) {
                throw new RuntimeException(
                    'Gagal memperbarui status Goods Receipt.'
                );
            }

            // 9. Tentukan status Purchase Order.
            $allReceived = true;

            foreach ($poItems as $poItem) {
                $poItemId = (int) $poItem['id'];

                $orderedQuantity = (float) $poItem['quantity'];
                $postedQuantity = (float) $poItem['posted_quantity'];
                $currentQuantity = (float) (
                    $currentReceived[$poItemId] ?? 0
                );

                $totalReceived = $postedQuantity + $currentQuantity;

                if ($totalReceived + 0.0001 < $orderedQuantity) {
                    $allReceived = false;
                    break;
                }
            }

            $purchaseOrderStatus = $allReceived
                ? 'received'
                : 'partial';

            if (!$this->purchaseOrderModel->update($purchaseOrderId, [
                'status'     => $purchaseOrderStatus,
                'updated_by' => $userId,
            ])) {
                throw new RuntimeException(
                    'Gagal memperbarui status Purchase Order.'
                );
            }

            // 10. Commit transaction.
            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Transaksi inventory gagal.'
                );
            }

            $this->db->transCommit();

            return [
                'receipt_id'          => $receiptId,
                'receipt_number'      => $receipt['receipt_number'],
                'status'              => 'posted',
                'purchase_order_id'   => $purchaseOrderId,
                'purchase_order_status' => $purchaseOrderStatus,
                'batch_ids'           => $createdBatches,
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }
    /**
     * Generate FEFO batch allocation.
     *
     * This method only generates an allocation plan.
     * It does not reduce stock.
     */
    public function getFefoAllocation(
        int $productId,
        float $quantity
    ): array {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Jumlah permintaan harus lebih besar dari nol.'
            );
        }

        $batches = $this->db->query(
            "SELECT
                id,
                batch_number,
                expired_at,
                quantity_remaining,
                unit_cost
             FROM product_batches
             WHERE product_id = ?
               AND status = 'available'
               AND quantity_remaining > 0
               AND (
                    expired_at IS NULL
                    OR expired_at >= CURDATE()
               )
             ORDER BY
                expired_at IS NULL ASC,
                expired_at ASC,
                id ASC",
            [$productId]
        )->getResultArray();

        $remaining = $quantity;
        $allocation = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $batch['quantity_remaining'];

            $allocated = min($available, $remaining);

            $allocation[] = [
                'batch_id'     => (int) $batch['id'],
                'batch_number' => $batch['batch_number'],
                'expired_at'   => $batch['expired_at'],
                'quantity'     => $allocated,
                'unit_cost'    => (float) $batch['unit_cost'],
            ];

            $remaining -= $allocated;
        }

        if ($remaining > 0.0001) {
            throw new RuntimeException(
                'Stok tersedia tidak mencukupi untuk produk ini.'
            );
        }

        return $allocation;
    }

    /**
     * Validate YYYY-MM-DD date.
     */
    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date;
    }
}


/**
 * $service = new InventoryService();

$result = $service->postGoodsReceipt(
    1,
    [
        1 => [
            [
                'batch_number' => 'BATCH-001',
                'expired_at'   => '2027-12-31',
                'quantity'     => 60,
            ],
            [
                'batch_number' => 'BATCH-002',
                'expired_at'   => '2028-06-30',
                'quantity'     => 40,
            ],
        ],
        2 => [
            [
                'batch_number' => 'BATCH-003',
                'expired_at'   => '2028-01-31',
                'quantity'     => 50,
            ],
        ],
    ],
    auth()->id()
);
 */
