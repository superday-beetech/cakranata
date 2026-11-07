<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Config\Database;
use DateTimeImmutable;
use Modules\Pharmacy\Models\Procurement\GoodsReceiptItemModel;
use Modules\Pharmacy\Models\Procurement\GoodsReceiptModel;
use Modules\Pharmacy\Models\Procurement\PurchaseOrderItemModel;
use Modules\Pharmacy\Models\Procurement\PurchaseOrderModel;
use Modules\Pharmacy\Services\InventoryService;
use Modules\Pharmacy\Services\ProcurementService;
use RuntimeException;
use Throwable;

class ProcurementApiController extends ApiController
{
    protected $db;

    protected ProcurementService $procurementService;
    protected InventoryService $inventoryService;

    protected PurchaseOrderModel $purchaseOrderModel;
    protected PurchaseOrderItemModel $purchaseOrderItemModel;

    protected GoodsReceiptModel $goodsReceiptModel;
    protected GoodsReceiptItemModel $goodsReceiptItemModel;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');

        $this->procurementService = new ProcurementService();
        $this->inventoryService = new InventoryService();

        $this->purchaseOrderModel = new PurchaseOrderModel();
        $this->purchaseOrderItemModel = new PurchaseOrderItemModel();

        $this->goodsReceiptModel = new GoodsReceiptModel();
        $this->goodsReceiptItemModel = new GoodsReceiptItemModel();
    }

    /*
    |--------------------------------------------------------------------------
    | PURCHASE ORDER
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/v1/pharmacy/purchase-orders
     *
     * Query:
     * - search
     * - status
     * - supplier_id
     * - page
     * - per_page
     */
    public function purchaseOrders()
    {
        try {
            $page = max(1, (int) $this->request->getGet('page'));

            $perPage = (int) ($this->request->getGet('per_page') ?: 10);
            $perPage = min(100, max(1, $perPage));

            $builder = $this->db->table('purchase_orders po');

            $builder->select([
                'po.*',
                's.name AS supplier_name',
            ]);

            $builder->join(
                'suppliers s',
                's.id = po.supplier_id',
                'left'
            );

            // Search
            if ($search = trim((string) $this->request->getGet('search'))) {
                $builder->groupStart()
                    ->like('po.order_number', $search)
                    ->orLike('s.name', $search)
                    ->groupEnd();
            }

            // Filter status
            if ($status = $this->request->getGet('status')) {
                $builder->where('po.status', $status);
            }

            // Filter supplier
            if ($supplierId = $this->request->getGet('supplier_id')) {
                $builder->where('po.supplier_id', (int) $supplierId);
            }

            // Count total records without changing the original builder
            $countBuilder = clone $builder;
            $total = $countBuilder->countAllResults();

            // Calculate pagination
            $totalPages = (int) ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;

            // Fetch current page
            $orders = $builder
                ->orderBy('po.id', 'DESC')
                ->limit($perPage, $offset)
                ->get()
                ->getResultArray();

            return $this->successResponse(
                [
                    'items' => $orders,
                    'pagination' => [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total'       => $total,
                        'total_pages' => $totalPages,
                        'has_next'    => $page < $totalPages,
                        'has_prev'    => $page > 1,
                    ],
                ],
                'Data Purchase Order berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ProcurementApiController::purchaseOrders] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil data Purchase Order.',
                500
            );
        }
    }

    /**
     * GET /api/v1/pharmacy/purchase-orders/{id}
     */
    public function purchaseOrderDetail(int $id)
    {
        try {
            $order = $this->db->table('purchase_orders po')
                ->select([
                    'po.*',
                    's.name AS supplier_name',
                    's.phone AS supplier_phone',
                    's.email AS supplier_email',
                    's.address AS supplier_address',
                ])
                ->join('suppliers s', 's.id = po.supplier_id', 'left')
                ->where('po.id', $id)
                ->get()
                ->getRowArray();

            if (!$order) {
                return $this->errorResponse(
                    'Purchase Order tidak ditemukan.',
                    404
                );
            }

            $items = $this->db->table('purchase_order_items poi')
                ->select([
                    'poi.*',
                    'p.sku',
                    'p.name AS product_name',
                    'p.barcode',
                ])
                ->join('products p', 'p.id = poi.product_id', 'left')
                ->where('poi.purchase_order_id', $id)
                ->orderBy('poi.id', 'ASC')
                ->get()
                ->getResultArray();

            $order['items'] = $items;

            return $this->successResponse(
                $order,
                'Detail Purchase Order berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message('error', '[ProcurementApiController::purchaseOrderDetail] ' . $e->getMessage());

            return $this->errorResponse(
                'Gagal mengambil detail Purchase Order.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/purchase-orders
     */
    public function createPurchaseOrder()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->errorResponse(
                    'Payload JSON tidak valid.',
                    400
                );
            }

            $orderId = $this->procurementService->createPurchaseOrder(
                $data,
                (int) auth()->id()
            );

            $order = $this->purchaseOrderModel->find($orderId);

            return $this->successResponse(
                $order,
                'Purchase Order berhasil dibuat.',
                201
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message('error', '[ProcurementApiController::createPurchaseOrder] ' . $e->getMessage());

            return $this->errorResponse(
                'Terjadi kesalahan saat membuat Purchase Order.',
                500
            );
        }
    }

    /**
     * PUT /api/v1/pharmacy/procurement-api/updatePurchaseOrder/{id}
     */
    public function updatePurchaseOrder(int $id)
    {
        try {
            $data = $this->getRequestData();

            if (!is_array($data)) {
                return $this->errorResponse(
                    'Payload JSON tidak valid.',
                    400
                );
            }

            $this->procurementService->updatePurchaseOrder(
                $id,
                $data,
                (int) auth()->id()
            );

            $order = $this->purchaseOrderModel->find($id);

            return $this->successResponse(
                $order,
                'Purchase Order berhasil diperbarui.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ProcurementApiController::updatePurchaseOrder] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Terjadi kesalahan saat memperbarui Purchase Order.',
                500
            );
        }
    }

    /**
     * DELETE /api/v1/pharmacy/procurement-api/deletePurchaseOrder/{id}
     */
    public function deletePurchaseOrder(int $id)
    {
        try {
            $this->procurementService->deletePurchaseOrder($id);

            return $this->successResponse(
                null,
                'Purchase Order berhasil dihapus.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ProcurementApiController::deletePurchaseOrder] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Terjadi kesalahan saat menghapus Purchase Order.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GOODS RECEIPT
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/v1/pharmacy/goods-receipts
     */

    public function goodsReceipts()
    {
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = (int) ($this->request->getGet('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));

        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $purchaseOrderId = (int) $this->request->getGet('purchase_order_id');

        $builder = $this->db->table('goods_receipts gr')
            ->select([
                'gr.*',
                'po.order_number',
                's.name AS supplier_name',
            ])
            ->join(
                'purchase_orders po',
                'po.id = gr.purchase_order_id'
            )
            ->join(
                'suppliers s',
                's.id = po.supplier_id'
            );

        if ($search !== '') {
            $builder->groupStart()
                ->like('gr.receipt_number', $search)
                ->orLike('po.order_number', $search)
                ->orLike('s.name', $search)
                ->groupEnd();
        }

        if ($status !== '') {
            $builder->where('gr.status', $status);
        }

        if ($purchaseOrderId > 0) {
            $builder->where('gr.purchase_order_id', $purchaseOrderId);
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        $totalPages = (int) ceil($total / $perPage);

        $receipts = $builder
            ->orderBy('gr.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->successResponse([
            'items' => $receipts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ],
        ], 'Daftar penerimaan barang berhasil dimuat.');
    }
    /* public function goodsReceipts()
    {
        try {
            $builder = $this->db->table('goods_receipts gr');

            $builder->select([
                'gr.*',
                'po.order_number',
                's.name AS supplier_name',
            ]);

            $builder->join(
                'purchase_orders po',
                'po.id = gr.purchase_order_id',
                'left'
            );

            $builder->join(
                'suppliers s',
                's.id = po.supplier_id',
                'left'
            );

            if ($search = trim((string) $this->request->getGet('search'))) {
                $builder->groupStart()
                    ->like('gr.receipt_number', $search)
                    ->orLike('po.order_number', $search)
                    ->orLike('s.name', $search)
                    ->groupEnd();
            }

            if ($status = $this->request->getGet('status')) {
                $builder->where('gr.status', $status);
            }

            if ($purchaseOrderId = $this->request->getGet('purchase_order_id')) {
                $builder->where(
                    'gr.purchase_order_id',
                    (int) $purchaseOrderId
                );
            }

            $receipts = $builder
                ->orderBy('gr.id', 'DESC')
                ->get()
                ->getResultArray();

            return $this->successResponse(
                $receipts,
                'Data Goods Receipt berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message('error', '[ProcurementApiController::goodsReceipts] ' . $e->getMessage());

            return $this->errorResponse(
                'Gagal mengambil data Goods Receipt.',
                500
            );
        }
    } */

    /**
     * GET /api/v1/pharmacy/goods-receipts/{id}
     */
    /* public function goodsReceiptDetail(int $id)
    {
        try {
            $receipt = $this->db->table('goods_receipts gr')
                ->select([
                    'gr.*',
                    'po.order_number',
                    'po.supplier_id',
                    's.name AS supplier_name',
                ])
                ->join(
                    'purchase_orders po',
                    'po.id = gr.purchase_order_id',
                    'left'
                )
                ->join(
                    'suppliers s',
                    's.id = po.supplier_id',
                    'left'
                )
                ->where('gr.id', $id)
                ->get()
                ->getRowArray();

            if (!$receipt) {
                return $this->errorResponse(
                    'Goods Receipt tidak ditemukan.',
                    404
                );
            }

            $items = $this->db->table('goods_receipt_items gri')
                ->select([
                    'gri.*',
                    'poi.quantity AS ordered_quantity',
                    'p.sku',
                    'p.name AS product_name',
                ])
                ->join(
                    'purchase_order_items poi',
                    'poi.id = gri.purchase_order_item_id',
                    'left'
                )
                ->join(
                    'products p',
                    'p.id = gri.product_id',
                    'left'
                )
                ->where('gri.goods_receipt_id', $id)
                ->orderBy('gri.id', 'ASC')
                ->get()
                ->getResultArray();

            $receipt['items'] = $items;

            return $this->successResponse(
                $receipt,
                'Detail Goods Receipt berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message('error', '[ProcurementApiController::goodsReceiptDetail] ' . $e->getMessage());

            return $this->errorResponse(
                'Gagal mengambil detail Goods Receipt.',
                500
            );
        }
    } */


    public function goodsReceiptDetail(int $id)
    {
        try {
            $receipt = $this->db->table('goods_receipts gr')
                ->select([
                    'gr.*',
                    'po.order_number',
                    'po.supplier_id',
                    's.name AS supplier_name',
                    's.phone AS supplier_phone',
                    's.email AS supplier_email',
                    's.address AS supplier_address',
                ])
                ->join(
                    'purchase_orders po',
                    'po.id = gr.purchase_order_id',
                    'left'
                )
                ->join(
                    'suppliers s',
                    's.id = po.supplier_id',
                    'left'
                )
                ->where('gr.id', $id)
                ->get()
                ->getRowArray();

            if (!$receipt) {
                return $this->errorResponse(
                    'Goods Receipt tidak ditemukan.',
                    404
                );
            }

            $items = $this->db->table('goods_receipt_items gri')
                ->select([
                    'gri.id',
                    'gri.goods_receipt_id',
                    'gri.purchase_order_item_id',
                    'gri.product_id',
                    'gri.quantity_received',
                    'gri.unit_cost',
                    'gri.notes',
                    'poi.quantity AS ordered_quantity',
                    'p.sku',
                    'p.name AS product_name',
                ])
                ->join(
                    'purchase_order_items poi',
                    'poi.id = gri.purchase_order_item_id',
                    'left'
                )
                ->join(
                    'products p',
                    'p.id = gri.product_id',
                    'left'
                )
                ->where('gri.goods_receipt_id', $id)
                ->orderBy('gri.id', 'ASC')
                ->get()
                ->getResultArray();

            $receipt['items'] = $items;

            return $this->successResponse(
                $receipt,
                'Detail Goods Receipt berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ProcurementApiController::goodsReceiptDetail] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil detail Goods Receipt.',
                500
            );
        }
    }


    /**
     * POST /api/v1/pharmacy/goods-receipts
     *
     * Payload:
     * {
     *   "receipt_number": "GR-001",
     *   "purchase_order_id": 1,
     *   "received_date": "2026-11-05",
     *   "supplier_delivery_number": "SJ-001",
     *   "notes": "Penerimaan barang",
     *   "items": [
     *     {
     *       "purchase_order_item_id": 1,
     *       "quantity_received": 100,
     *       "unit_cost": 5000,
     *       "notes": null
     *     }
     *   ]
     * }
     */
    public function createGoodsReceipt()
    {
        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->errorResponse(
                'Payload JSON tidak valid.',
                400
            );
        }

        try {
            $this->validateGoodsReceiptPayload($data);

            $this->db->transBegin();

            $purchaseOrderId = (int) $data['purchase_order_id'];

            $purchaseOrder = $this->purchaseOrderModel->find(
                $purchaseOrderId
            );

            if (!$purchaseOrder) {
                throw new RuntimeException(
                    'Purchase Order tidak ditemukan.'
                );
            }

            if (!in_array($purchaseOrder['status'], ['draft', 'approved', 'ordered', 'partial'], true)) {
                throw new RuntimeException(
                    'Purchase Order tidak dapat menerima barang dengan status saat ini.'
                );
            }

            $receiptId = $this->goodsReceiptModel->insert([
                'receipt_number' => trim($data['receipt_number']),
                'purchase_order_id' => $purchaseOrderId,
                'received_date' => $data['received_date'],
                'supplier_delivery_number' => $data['supplier_delivery_number'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => (int) auth()->id(),
                'updated_by' => (int) auth()->id(),
            ]);

            if (!$receiptId) {
                throw new RuntimeException(
                    'Gagal menyimpan Goods Receipt.'
                );
            }

            foreach ($data['items'] as $item) {
                $purchaseOrderItemId = (int) $item['purchase_order_item_id'];

                $purchaseOrderItem = $this->purchaseOrderItemModel
                    ->where('purchase_order_id', $purchaseOrderId)
                    ->find($purchaseOrderItemId);

                if (!$purchaseOrderItem) {
                    throw new RuntimeException(
                        "Item Purchase Order ID {$purchaseOrderItemId} tidak ditemukan pada PO ini."
                    );
                }

                $quantityReceived = (float) $item['quantity_received'];

                $unitCost = isset($item['unit_cost'])
                    ? (float) $item['unit_cost']
                    : (float) $purchaseOrderItem['unit_cost'];

                if ($quantityReceived <= 0 || $unitCost < 0) {
                    throw new RuntimeException(
                        'Jumlah penerimaan harus positif dan harga tidak boleh negatif.'
                    );
                }

                $itemId = $this->goodsReceiptItemModel->insert([
                    'goods_receipt_id' => $receiptId,
                    'purchase_order_item_id' => $purchaseOrderItemId,
                    'product_id' => (int) $purchaseOrderItem['product_id'],
                    'quantity_received' => $quantityReceived,
                    'unit_cost' => $unitCost,
                    'notes' => $item['notes'] ?? null,
                ]);

                if (!$itemId) {
                    throw new RuntimeException(
                        'Gagal menyimpan detail Goods Receipt.'
                    );
                }
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Transaksi Goods Receipt gagal.'
                );
            }

            $this->db->transCommit();

            $receipt = $this->goodsReceiptModel->find($receiptId);

            $receipt['items'] = $this->goodsReceiptItemModel
                ->where('goods_receipt_id', $receiptId)
                ->findAll();

            return $this->successResponse(
                $receipt,
                'Goods Receipt berhasil dibuat sebagai draft.',
                201
            );
        } catch (RuntimeException $e) {
            $this->db->transRollback();

            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            $this->db->transRollback();

            log_message('error', '[ProcurementApiController::createGoodsReceipt] ' . $e->getMessage());

            return $this->errorResponse(
                'Terjadi kesalahan saat membuat Goods Receipt.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/goods-receipts/{id}/post
     *
     * Payload:
     * {
     *   "batch_breakdown": {
     *     "1": [
     *       {
     *         "batch_number": "BATCH-001",
     *         "expired_at": "2027-12-31",
     *         "quantity": 60
     *       },
     *       {
     *         "batch_number": "BATCH-002",
     *         "expired_at": "2028-06-30",
     *         "quantity": 40
     *       }
     *     ]
     *   }
     * }
     */
    public function postGoodsReceipt(int $id)
    {
        try {
            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->errorResponse(
                    'Payload JSON tidak valid.',
                    400
                );
            }

            $batchBreakdown = $data['batch_breakdown'] ?? null;

            if (!is_array($batchBreakdown) || empty($batchBreakdown)) {
                return $this->errorResponse(
                    'Rincian batch wajib diisi.',
                    422
                );
            }

            $result = $this->inventoryService->postGoodsReceipt(
                $id,
                $batchBreakdown,
                (int) auth()->id()
            );

            return $this->successResponse(
                $result,
                'Goods Receipt berhasil diposting. Stok dan batch berhasil dicatat.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message('error', '[ProcurementApiController::postGoodsReceipt] ' . $e->getMessage());

            return $this->errorResponse(
                'Terjadi kesalahan saat mem-posting Goods Receipt.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    private function validateGoodsReceiptPayload(array $data): void
    {
        foreach (
            [
                'receipt_number',
                'purchase_order_id',
                'received_date',
                'items',
            ] as $field
        ) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new RuntimeException(
                    "Field {$field} wajib diisi."
                );
            }
        }

        if (!is_numeric($data['purchase_order_id']) || (int) $data['purchase_order_id'] <= 0) {
            throw new RuntimeException(
                'Purchase Order ID tidak valid.'
            );
        }

        if (!$this->isValidDate((string) $data['received_date'])) {
            throw new RuntimeException(
                'Format received_date harus YYYY-MM-DD.'
            );
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new RuntimeException(
                'Goods Receipt harus memiliki minimal satu item.'
            );
        }

        foreach ($data['items'] as $index => $item) {
            if (!is_array($item)) {
                throw new RuntimeException(
                    "Format item ke-" . ($index + 1) . ' tidak valid.'
                );
            }

            foreach (
                [
                    'purchase_order_item_id',
                    'quantity_received',
                ] as $field
            ) {
                if (!isset($item[$field]) || !is_numeric($item[$field])) {
                    throw new RuntimeException(
                        "Field {$field} pada item ke-" . ($index + 1) . ' tidak valid.'
                    );
                }
            }

            if (
                (int) $item['purchase_order_item_id'] <= 0 ||
                (float) $item['quantity_received'] <= 0
            ) {
                throw new RuntimeException(
                    'ID item PO dan jumlah penerimaan harus lebih besar dari nol.'
                );
            }

            if (
                isset($item['unit_cost']) &&
                (!is_numeric($item['unit_cost']) || (float) $item['unit_cost'] < 0)
            ) {
                throw new RuntimeException(
                    'Harga satuan penerimaan tidak valid.'
                );
            }
        }
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date;
    }

    private function getRequestData(): ?array
    {
        try {
            $data = $this->request->getJSON(true);
            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }
}
