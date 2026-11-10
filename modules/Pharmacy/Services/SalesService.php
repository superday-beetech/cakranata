<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SalesService
{
    private $db;

    private const PAYMENT_METHODS = [
        'cash',
        'transfer',
        'qris',
        'debit',
        'credit',
        'other',
    ];

    private const SETTLEMENT_MODES = [
        'paid',
        'partial',
        'receivable',
    ];

    private bool $transactionStarted = false;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE DRAFT
    |--------------------------------------------------------------------------
    */

    public function createDraft(array $data, int $userId): int
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('User tidak valid.');
        }

        $items = $data['items'] ?? [];

        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException(
                'Item penjualan belum diisi.'
            );
        }

        $this->beginTransaction();

        try {
            $now = date('Y-m-d H:i:s');

            $customerId = !empty($data['customer_id'])
                ? (int) $data['customer_id']
                : null;

            if ($customerId !== null) {
                $customer = $this->db->table('customers')
                    ->select('id')
                    ->where('id', $customerId)
                    ->where('is_active', 1)
                    ->get()
                    ->getRowArray();

                if (!$customer) {
                    throw new InvalidArgumentException(
                        'Pelanggan tidak ditemukan atau tidak aktif.'
                    );
                }
            }

            $saleDate = $this->normalizeDate(
                $data['sale_date'] ?? null
            );

            $subtotal = 0.00;
            $preparedItems = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException(
                        'Format item penjualan tidak valid.'
                    );
                }

                $productId = (int) ($item['product_id'] ?? 0);

                $quantity = round(
                    $this->numericValue(
                        $item['quantity'] ?? null,
                        'Jumlah produk'
                    ),
                    3
                );

                $unitPrice = round(
                    $this->numericValue(
                        $item['unit_price'] ?? null,
                        'Harga produk'
                    ),
                    2
                );

                $discount = round(
                    $this->numericValue(
                        $item['discount_amount'] ?? 0,
                        'Diskon item'
                    ),
                    2
                );

                if ($productId < 1 || $quantity <= 0) {
                    throw new InvalidArgumentException(
                        'Produk atau jumlah penjualan tidak valid.'
                    );
                }

                if ($unitPrice < 0 || $discount < 0) {
                    throw new InvalidArgumentException(
                        'Harga atau diskon tidak valid.'
                    );
                }

                $product = $this->db->table('products')
                    ->select('id')
                    ->where('id', $productId)
                    ->where('is_active', 1)
                    ->get()
                    ->getRowArray();

                if (!$product) {
                    throw new InvalidArgumentException(
                        "Produk ID {$productId} tidak ditemukan atau tidak aktif."
                    );
                }

                $gross = round($quantity * $unitPrice, 2);

                if ($discount > $gross) {
                    throw new InvalidArgumentException(
                        'Diskon item melebihi nilai penjualan.'
                    );
                }

                $lineSubtotal = round($gross - $discount, 2);

                $subtotal += $lineSubtotal;

                $preparedItems[] = [
                    'product_id'      => $productId,
                    'quantity'        => $quantity,
                    'unit_price'      => $unitPrice,
                    'discount_amount' => $discount,
                    'subtotal'        => $lineSubtotal,
                    'unit_cost'       => 0,
                    'batch_id'        => null,
                    'notes'           => $this->nullableString(
                        $item['notes'] ?? null
                    ),
                ];
            }

            $subtotal = round($subtotal, 2);

            $headerDiscount = round(
                $this->numericValue(
                    $data['discount_amount'] ?? 0,
                    'Diskon transaksi'
                ),
                2
            );

            $taxAmount = round(
                $this->numericValue(
                    $data['tax_amount'] ?? 0,
                    'Pajak'
                ),
                2
            );

            if ($headerDiscount < 0 || $taxAmount < 0) {
                throw new InvalidArgumentException(
                    'Diskon atau pajak tidak valid.'
                );
            }

            if ($headerDiscount > $subtotal) {
                throw new InvalidArgumentException(
                    'Diskon transaksi melebihi subtotal.'
                );
            }

            $totalAmount = round(
                $subtotal - $headerDiscount + $taxAmount,
                2
            );

            if (!is_finite($totalAmount) || $totalAmount <= 0) {
                throw new InvalidArgumentException(
                    'Total penjualan harus lebih besar dari nol.'
                );
            }

            $saleNumber = $this->generateSaleNumber();

            $inserted = $this->db->table('sales')->insert([
                'sale_number'     => $saleNumber,
                'customer_id'     => $customerId,
                'sale_date'       => $saleDate,
                'status'          => 'draft',
                'subtotal'        => $subtotal,
                'discount_amount' => $headerDiscount,
                'tax_amount'      => $taxAmount,
                'total_amount'    => $totalAmount,
                'paid_amount'     => 0,
                'amount_received' => 0,
                'change_amount'   => 0,
                'settlement_mode' => 'paid',
                'payment_status'  => 'unpaid',
                'payment_method'  => null,
                'reference_number' => null,
                'due_date'        => null,
                'notes'           => $this->nullableString(
                    $data['notes'] ?? null
                ),
                'created_by'      => $userId,
                'updated_by'      => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            if (!$inserted) {
                throw new RuntimeException(
                    'Gagal membuat transaksi penjualan.'
                );
            }

            $saleId = (int) $this->db->insertID();

            if ($saleId < 1) {
                throw new RuntimeException(
                    'ID transaksi penjualan tidak berhasil dibuat.'
                );
            }

            $itemsToInsert = [];

            foreach ($preparedItems as $item) {
                $item['sale_id'] = $saleId;
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                $itemsToInsert[] = $item;
            }

            if ($itemsToInsert !== []) {
                $inserted = $this->db
                    ->table('sale_items')
                    ->insertBatch($itemsToInsert);

                if ($inserted === false) {
                    throw new RuntimeException(
                        'Gagal menyimpan item transaksi penjualan.'
                    );
                }

                if ((int) $inserted !== count($itemsToInsert)) {
                    throw new RuntimeException(
                        'Jumlah item yang tersimpan tidak sesuai.'
                    );
                }
            }

            $this->commitTransaction();

            return $saleId;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | POST SALE
    |--------------------------------------------------------------------------
    */

    public function postSale(
        int $saleId,
        array $payment,
        int $userId
    ): bool {
        if ($saleId < 1 || $userId < 1) {
            throw new InvalidArgumentException(
                'ID transaksi atau user tidak valid.'
            );
        }

        $this->beginTransaction();

        try {
            // Kunci header penjualan.
            $sale = $this->db->query(
                'SELECT *
                 FROM sales
                 WHERE id = ?
                 FOR UPDATE',
                [$saleId]
            )->getRowArray();

            if (!$sale) {
                throw new RuntimeException(
                    'Transaksi penjualan tidak ditemukan.'
                );
            }

            if ($sale['status'] !== 'draft') {
                throw new RuntimeException(
                    'Hanya transaksi draft yang dapat diposting.'
                );
            }

            // Kunci item penjualan.
            $items = $this->db->query(
                'SELECT *
                 FROM sale_items
                 WHERE sale_id = ?
                 ORDER BY id ASC
                 FOR UPDATE',
                [$saleId]
            )->getResultArray();

            if ($items === []) {
                throw new RuntimeException(
                    'Transaksi tidak memiliki item.'
                );
            }

            $totalAmount = round(
                $this->numericValue(
                    $sale['total_amount'],
                    'Total transaksi'
                ),
                2
            );

            if ($totalAmount <= 0) {
                throw new InvalidArgumentException(
                    'Total transaksi harus lebih besar dari nol.'
                );
            }

            // amount dan applied_amount harus sama.
            $paidAmount = round(
                $this->numericValue(
                    $payment['amount'] ?? null,
                    'Jumlah pembayaran'
                ),
                2
            );

            $appliedAmountRaw = $payment['applied_amount']
                ?? $payment['amount']
                ?? 0;

            $appliedAmount = round(
                $this->numericValue(
                    $appliedAmountRaw,
                    'Nominal pembayaran diterapkan'
                ),
                2
            );

            if (
                $paidAmount < 0
                || $paidAmount > $totalAmount
                || abs($paidAmount - $appliedAmount) > 0.001
            ) {
                throw new InvalidArgumentException(
                    'Nominal pembayaran tidak valid atau tidak konsisten.'
                );
            }

            $paymentMethod = null;

            if ($paidAmount > 0) {
                $paymentMethod = trim(
                    (string) ($payment['payment_method'] ?? '')
                );

                if (!in_array(
                    $paymentMethod,
                    self::PAYMENT_METHODS,
                    true
                )) {
                    throw new InvalidArgumentException(
                        'Metode pembayaran tidak valid.'
                    );
                }
            }

            $referenceNumber = $this->nullableString(
                $payment['reference_number'] ?? null
            );

            $requestedSettlementMode = trim(
                (string) ($payment['settlement_mode'] ?? '')
            );

            if (!in_array(
                $requestedSettlementMode,
                self::SETTLEMENT_MODES,
                true
            )) {
                throw new InvalidArgumentException(
                    'Mode pembayaran tidak valid.'
                );
            }

            $settlement = $this->resolveSettlement(
                $sale,
                $totalAmount,
                $paidAmount,
                $paymentMethod,
                $payment,
                $requestedSettlementMode
            );

            // Alokasikan stok menggunakan FEFO.
            foreach ($items as $item) {
                $this->allocateStock(
                    $sale,
                    $item,
                    $userId
                );
            }

            // Catat pembayaran jika ada nominal yang diterapkan.
            if ($paidAmount > 0) {
                $this->recordPayment(
                    $sale,
                    $paidAmount,
                    $paymentMethod,
                    $referenceNumber,
                    $userId
                );
            }

            $remainingAmount = round(
                $totalAmount - $paidAmount,
                2
            );

            $paymentStatus = $remainingAmount <= 0
                ? 'paid'
                : ($paidAmount > 0 ? 'partial' : 'unpaid');

            $now = date('Y-m-d H:i:s');

            $updated = $this->db->table('sales')
                ->where('id', $saleId)
                ->where('status', 'draft')
                ->update([
                    'status'           => 'posted',
                    'paid_amount'      => $paidAmount,
                    'amount_received'  => $settlement['amount_received'],
                    'change_amount'    => $settlement['change_amount'],
                    'payment_status'   => $paymentStatus,
                    'settlement_mode'  => $settlement['settlement_mode'],
                    'payment_method'   => $paymentMethod,
                    'reference_number' => $referenceNumber,
                    'due_date'         => $settlement['due_date'],
                    'updated_by'       => $userId,
                    'updated_at'       => $now,
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException(
                    'Status transaksi gagal diperbarui.'
                );
            }

            $this->commitTransaction();

            return true;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HOLD / RESUME / CANCEL
    |--------------------------------------------------------------------------
    */

    public function holdSale(int $saleId, int $userId): bool
    {
        return $this->changeDraftState(
            $saleId,
            $userId,
            'draft',
            'held',
            'Transaksi tidak dapat ditahan.'
        );
    }

    public function resumeSale(int $saleId, int $userId): bool
    {
        return $this->changeDraftState(
            $saleId,
            $userId,
            'held',
            'draft',
            'Transaksi tidak dapat dilanjutkan.'
        );
    }

    private function changeDraftState(
        int $saleId,
        int $userId,
        string $currentStatus,
        string $targetStatus,
        string $errorMessage
    ): bool {
        if ($saleId < 1 || $userId < 1) {
            throw new InvalidArgumentException(
                'ID transaksi atau user tidak valid.'
            );
        }

        $this->beginTransaction();

        try {
            $sale = $this->db->query(
                'SELECT id, status
                 FROM sales
                 WHERE id = ?
                 FOR UPDATE',
                [$saleId]
            )->getRowArray();

            if (!$sale) {
                throw new RuntimeException(
                    'Transaksi penjualan tidak ditemukan.'
                );
            }

            if ($sale['status'] !== $currentStatus) {
                throw new RuntimeException($errorMessage);
            }

            $updated = $this->db->table('sales')
                ->where('id', $saleId)
                ->where('status', $currentStatus)
                ->update([
                    'status'     => $targetStatus,
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException(
                    'Status transaksi gagal diperbarui.'
                );
            }

            $this->commitTransaction();

            return true;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    public function cancelSale(int $saleId, int $userId): bool
    {
        if ($saleId < 1 || $userId < 1) {
            throw new InvalidArgumentException(
                'ID transaksi atau user tidak valid.'
            );
        }

        $this->beginTransaction();

        try {
            $sale = $this->db->query(
                'SELECT id, status
                 FROM sales
                 WHERE id = ?
                 FOR UPDATE',
                [$saleId]
            )->getRowArray();

            if (!$sale) {
                throw new RuntimeException(
                    'Transaksi penjualan tidak ditemukan.'
                );
            }

            if (!in_array(
                $sale['status'],
                ['draft', 'held'],
                true
            )) {
                throw new RuntimeException(
                    'Hanya transaksi draft atau held yang dapat dibatalkan.'
                );
            }

            $updated = $this->db->table('sales')
                ->where('id', $saleId)
                ->whereIn('status', ['draft', 'held'])
                ->update([
                    'status'     => 'cancelled',
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException(
                    'Transaksi gagal dibatalkan.'
                );
            }

            $this->commitTransaction();

            return true;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SALES QUERIES
    |--------------------------------------------------------------------------
    */

    public function getSales(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));

        $perPage = min(
            100,
            max(1, (int) ($filters['per_page'] ?? 10))
        );

        $offset = ($page - 1) * $perPage;

        $builder = $this->db->table('sales s')
            ->select('s.*, c.name AS customer_name')
            ->join('customers c', 'c.id = s.customer_id', 'left');

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $builder->groupStart()
                ->like('s.sale_number', $search)
                ->orLike('c.name', $search)
                ->groupEnd();
        }

        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $builder->where('s.status', $status);
        }

        $countBuilder = clone $builder;

        $total = $countBuilder->countAllResults();

        $data = $builder
            ->orderBy('s.id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function getSaleDetail(int $saleId): ?array
    {
        if ($saleId < 1) {
            return null;
        }

        $sale = $this->db->table('sales s')
            ->select('s.*, c.name AS customer_name')
            ->join('customers c', 'c.id = s.customer_id', 'left')
            ->where('s.id', $saleId)
            ->get()
            ->getRowArray();

        if (!$sale) {
            return null;
        }

        $sale['items'] = $this->db->table('sale_items si')
            ->select('
                si.*,
                p.name AS product_name,
                p.sku,
                p.barcode,
                pb.batch_number,
                pb.expired_at
            ')
            ->join('products p', 'p.id = si.product_id')
            ->join('product_batches pb', 'pb.id = si.batch_id', 'left')
            ->where('si.sale_id', $saleId)
            ->orderBy('si.id', 'ASC')
            ->get()
            ->getResultArray();

        return $sale;
    }

    /*
    |--------------------------------------------------------------------------
    | FEFO STOCK ALLOCATION
    |--------------------------------------------------------------------------
    */

    private function allocateStock(
        array $sale,
        array $item,
        int $userId
    ): void {
        $saleDate = (new DateTimeImmutable(
            (string) $sale['sale_date']
        ))->format('Y-m-d H:i:s');

        $batches = $this->db->query(
            "SELECT *
             FROM product_batches
             WHERE product_id = ?
               AND status = 'available'
               AND quantity_remaining > 0
               AND (expired_at IS NULL OR expired_at >= ?)
             ORDER BY
               (expired_at IS NULL) ASC,
               expired_at ASC,
               id ASC
             FOR UPDATE",
            [
                $item['product_id'],
                $saleDate,
            ]
        )->getResultArray();

        $remainingQuantity = round(
            (float) $item['quantity'],
            3
        );

        $originalQuantity = $remainingQuantity;

        if ($originalQuantity <= 0) {
            throw new RuntimeException(
                'Jumlah produk tidak valid saat alokasi stok.'
            );
        }

        $originalDiscount = round(
            (float) $item['discount_amount'],
            2
        );

        $originalSubtotal = round(
            (float) $item['subtotal'],
            2
        );

        $originalGross = round(
            $originalSubtotal + $originalDiscount,
            2
        );

        $expectedGross = round(
            $originalQuantity * (float) $item['unit_price'],
            2
        );

        if (abs($originalGross - $expectedGross) > 0.001) {
            throw new RuntimeException(
                'Nilai bruto item tidak sesuai dengan kuantitas dan harga satuan.'
            );
        }

        if (
            $originalDiscount < 0
            || $originalSubtotal < 0
            || $originalDiscount > $originalGross
        ) {
            throw new RuntimeException(
                'Nilai diskon atau subtotal item tidak valid.'
            );
        }

        $remainingGross = $originalGross;
        $remainingDiscount = $originalDiscount;

        $firstAllocation = true;
        $now = date('Y-m-d H:i:s');

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $available = round(
                (float) $batch['quantity_remaining'],
                3
            );

            if ($available <= 0) {
                continue;
            }

            $allocated = round(
                min($remainingQuantity, $available),
                3
            );

            if ($allocated <= 0) {
                continue;
            }

            $isLastAllocation = (
                $allocated >= $remainingQuantity
            );

            if ($isLastAllocation) {
                $allocatedGross = $remainingGross;
                $allocatedDiscount = $remainingDiscount;
            } else {
                $allocatedGross = round(
                    $originalGross
                        * ($allocated / $originalQuantity),
                    2
                );

                $allocatedGross = min(
                    $allocatedGross,
                    $remainingGross
                );

                $allocatedDiscount = round(
                    $originalDiscount
                        * ($allocated / $originalQuantity),
                    2
                );

                $allocatedDiscount = min(
                    $allocatedDiscount,
                    $remainingDiscount
                );
            }

            $allocatedSubtotal = round(
                $allocatedGross - $allocatedDiscount,
                2
            );

            if (
                $allocatedGross < 0
                || $allocatedDiscount < 0
                || $allocatedSubtotal < 0
            ) {
                throw new RuntimeException(
                    'Nilai hasil alokasi penjualan tidak valid.'
                );
            }

            $unitCost = $this->numericValue(
                $batch['unit_cost'],
                'Harga pokok batch'
            );

            // Kurangi stok batch.
            $updated = $this->db->table('product_batches')
                ->where('id', $batch['id'])
                ->where('status', 'available')
                ->where('quantity_remaining >=', $allocated)
                ->update([
                    'quantity_remaining' => round(
                        $available - $allocated,
                        3
                    ),
                    'updated_at' => $now,
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException(
                    "Gagal memperbarui stok batch ID {$batch['id']}."
                );
            }

            $allocatedItem = [
                'sale_id'         => $sale['id'],
                'product_id'      => $item['product_id'],
                'batch_id'        => $batch['id'],
                'quantity'        => $allocated,
                'unit_price'      => $item['unit_price'],
                'discount_amount' => $allocatedDiscount,
                'subtotal'        => $allocatedSubtotal,
                'unit_cost'       => $unitCost,
                'notes'           => $item['notes'] ?? null,
                'updated_at'      => $now,
            ];

            if ($firstAllocation) {
                // Gunakan kembali baris draft pertama.
                $updated = $this->db->table('sale_items')
                    ->where('id', $item['id'])
                    ->where('sale_id', $sale['id'])
                    ->update($allocatedItem);

                if (!$updated || $this->db->affectedRows() !== 1) {
                    throw new RuntimeException(
                        'Gagal memperbarui item penjualan hasil alokasi stok.'
                    );
                }

                $firstAllocation = false;
            } else {
                // Tambahkan baris untuk batch berikutnya.
                $allocatedItem['created_at'] = $now;

                $inserted = $this->db
                    ->table('sale_items')
                    ->insert($allocatedItem);

                if (!$inserted) {
                    throw new RuntimeException(
                        'Gagal menyimpan item penjualan hasil alokasi stok.'
                    );
                }
            }

            // Catat stock movement.
            $movementInserted = $this->db
                ->table('stock_movements')
                ->insert([
                    'product_id'     => $item['product_id'],
                    'batch_id'       => $batch['id'],
                    'movement_date'  => $saleDate,
                    'movement_type'  => 'out',
                    'reference_type' => 'sale',
                    'reference_id'   => $sale['id'],
                    'quantity_in'    => 0,
                    'quantity_out'   => $allocated,
                    'unit_cost'      => $unitCost,
                    'notes'          => 'Penjualan POS ' . $sale['sale_number'],
                    'created_by'     => $userId,
                    'created_at'     => $now,
                ]);

            if (!$movementInserted) {
                throw new RuntimeException(
                    'Gagal mencatat pergerakan stok.'
                );
            }

            $remainingQuantity = round(
                $remainingQuantity - $allocated,
                3
            );

            $remainingGross = round(
                $remainingGross - $allocatedGross,
                2
            );

            $remainingDiscount = round(
                $remainingDiscount - $allocatedDiscount,
                2
            );
        }

        if ($remainingQuantity > 0) {
            throw new RuntimeException(
                "Stok produk ID {$item['product_id']} tidak mencukupi."
            );
        }

        if (
            abs($remainingGross) > 0.001
            || abs($remainingDiscount) > 0.001
        ) {
            throw new RuntimeException(
                'Alokasi nilai penjualan tidak konsisten.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SETTLEMENT
    |--------------------------------------------------------------------------
    */

    private function resolveSettlement(
        array $sale,
        float $totalAmount,
        float $paidAmount,
        ?string $paymentMethod,
        array $payment,
        string $requestedSettlementMode
    ): array {
        $totalAmount = round($totalAmount, 2);
        $paidAmount = round($paidAmount, 2);

        if (
            !is_finite($totalAmount)
            || !is_finite($paidAmount)
            || $totalAmount <= 0
            || $paidAmount < 0
            || $paidAmount > $totalAmount
        ) {
            throw new InvalidArgumentException(
                'Nominal transaksi atau pembayaran tidak valid.'
            );
        }

        if ($paidAmount > 0) {
            if (
                $paymentMethod === null
                || !in_array(
                    $paymentMethod,
                    self::PAYMENT_METHODS,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Metode pembayaran wajib dipilih.'
                );
            }
        } else {
            $paymentMethod = null;
        }

        $remaining = round(
            $totalAmount - $paidAmount,
            2
        );

        if ($remaining <= 0) {
            $settlementMode = 'paid';
        } elseif ($paidAmount > 0) {
            $settlementMode = 'partial';
        } else {
            $settlementMode = 'receivable';
        }

        if ($settlementMode !== $requestedSettlementMode) {
            throw new InvalidArgumentException(
                'Mode pembayaran tidak sesuai dengan nominal pembayaran.'
            );
        }

        $dueDate = null;

        if ($remaining > 0) {
            if (empty($sale['customer_id'])) {
                throw new RuntimeException(
                    'Pelanggan wajib dipilih untuk transaksi tempo atau piutang.'
                );
            }

            $dueDate = $this->normalizeDueDate(
                $payment['due_date'] ?? null
            );
        }

        if (
            in_array($paymentMethod, ['transfer', 'qris'], true)
            && !filter_var(
                $payment['payment_confirmed'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            )
        ) {
            throw new RuntimeException(
                'Pembayaran transfer atau QRIS harus dikonfirmasi kasir.'
            );
        }

        $amountReceived = $paidAmount;
        $changeAmount = 0.00;

        if ($paymentMethod === 'cash') {
            $received = round(
                $this->numericValue(
                    $payment['amount_received'] ?? 0,
                    'Uang diterima'
                ),
                2
            );

            if ($received <= 0) {
                $received = $paidAmount;
            }

            if ($received < $paidAmount) {
                throw new InvalidArgumentException(
                    'Uang diterima kurang dari jumlah pembayaran.'
                );
            }

            $amountReceived = $received;

            $changeAmount = round(
                $amountReceived - $paidAmount,
                2
            );

            if (
                $settlementMode === 'partial'
                && $changeAmount > 0
            ) {
                throw new InvalidArgumentException(
                    'Pembayaran tunai sebagian harus sesuai dengan uang yang diterima.'
                );
            }
        }

        return [
            'settlement_mode' => $settlementMode,
            'due_date'        => $dueDate,
            'amount_received' => $amountReceived,
            'change_amount'   => $changeAmount,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT RECORDING
    |--------------------------------------------------------------------------
    */

    private function recordPayment(
        array $sale,
        float $amount,
        string $method,
        ?string $referenceNumber,
        int $userId
    ): void {
        $amount = round($amount, 2);

        if (
            !isset($sale['id'])
            || (int) $sale['id'] < 1
            || $userId < 1
        ) {
            throw new InvalidArgumentException(
                'Referensi penjualan atau user tidak valid.'
            );
        }

        if (!is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException(
                'Nominal pembayaran harus lebih besar dari nol.'
            );
        }

        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException(
                'Metode pembayaran tidak valid.'
            );
        }

        $now = date('Y-m-d H:i:s');

        $paymentNumber = $this->generateNumber(
            'customer_payments',
            'payment_number',
            'CP'
        );

        $transactionNumber = $this->generateNumber(
            'cash_transactions',
            'transaction_number',
            'CT'
        );

        // Pembayaran pelanggan hanya untuk transaksi berkustomer.
        if (!empty($sale['customer_id'])) {
            $paymentInserted = $this->db
                ->table('customer_payments')
                ->insert([
                    'payment_number'        => $paymentNumber,
                    'customer_id'           => $sale['customer_id'],
                    'sale_id'               => $sale['id'],
                    'distribution_order_id' => null,
                    'payment_date'          => date('Y-m-d'),
                    'amount'                => $amount,
                    'payment_method'        => $method,
                    'reference_number'      => $referenceNumber,
                    'notes'                 => 'Pembayaran POS ' . $sale['sale_number'],
                    'created_by'            => $userId,
                    'created_at'            => $now,
                ]);

            if (!$paymentInserted) {
                throw new RuntimeException(
                    'Gagal mencatat pembayaran pelanggan.'
                );
            }
        }

        // Kas masuk selalu dicatat, termasuk transaksi walk-in.
        $cashInserted = $this->db
            ->table('cash_transactions')
            ->insert([
                'transaction_number' => $transactionNumber,
                'transaction_date'   => $now,
                'transaction_type'   => 'income',
                'reference_type'     => 'sale',
                'reference_id'       => $sale['id'],
                'description'        => 'Pembayaran POS ' . $sale['sale_number'],
                'amount'              => $amount,
                'payment_method'      => $method,
                'created_by'          => $userId,
                'created_at'          => $now,
            ]);

        if (!$cashInserted) {
            throw new RuntimeException(
                'Gagal mencatat transaksi kas masuk.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NUMBER GENERATORS
    |--------------------------------------------------------------------------
    */

    private function generateSaleNumber(): string
    {
        do {
            $number = 'POS-' . date('Ymd-His')
                . '-' . random_int(100000, 999999);

            $exists = $this->db->table('sales')
                ->where('sale_number', $number)
                ->countAllResults() > 0;
        } while ($exists);

        return $number;
    }

    private function generateNumber(
        string $table,
        string $column,
        string $prefix
    ): string {
        do {
            $number = $prefix
                . date('YmdHis')
                . random_int(100000, 999999);

            $exists = $this->db->table($table)
                ->where($column, $number)
                ->countAllResults() > 0;
        } while ($exists);

        return $number;
    }

    /*
    |--------------------------------------------------------------------------
    | DATE / VALUE NORMALIZATION
    |--------------------------------------------------------------------------
    */

    private function normalizeDate(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return date('Y-m-d H:i:s');
        }

        try {
            return (new DateTimeImmutable((string) $value))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw new InvalidArgumentException(
                'Tanggal penjualan tidak valid.'
            );
        }
    }

    private function normalizeDueDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            throw new InvalidArgumentException(
                'Tanggal jatuh tempo wajib diisi.'
            );
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $text
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                $errors !== false
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d') !== $text
        ) {
            throw new InvalidArgumentException(
                'Format tanggal jatuh tempo tidak valid.'
            );
        }

        if ($date < new DateTimeImmutable('today')) {
            throw new InvalidArgumentException(
                'Tanggal jatuh tempo tidak boleh sebelum hari ini.'
            );
        }

        return $date->format('Y-m-d');
    }

    private function numericValue(
        mixed $value,
        string $label
    ): float {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "{$label} harus berupa angka."
            );
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            throw new InvalidArgumentException(
                "{$label} tidak valid."
            );
        }

        return $number;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSACTION HELPERS
    |--------------------------------------------------------------------------
    */

    private function beginTransaction(): void
    {
        if (!$this->db->transBegin()) {
            throw new RuntimeException(
                'Gagal memulai transaksi database.'
            );
        }
    }

    private function commitTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new RuntimeException(
                'Transaksi database mengalami kegagalan.'
            );
        }

        if (!$this->db->transCommit()) {
            throw new RuntimeException(
                'Gagal melakukan commit transaksi database.'
            );
        }
    }


    private function rollbackTransaction(): void
    {
        if (!$this->transactionStarted) {
            return;
        }

        try {
            $this->db->transRollback();
        } finally {
            $this->transactionStarted = false;
        }
    }
}
