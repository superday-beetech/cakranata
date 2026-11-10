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
    protected $db;

    private const PAYMENT_METHODS = [
        'cash',
        'transfer',
        'qris',
        'debit',
        'credit',
        'other',
    ];

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /**
     * Membuat draft POS.
     *
     * sale_number dibuat otomatis oleh backend.
     */
    public function createDraft(array $data, int $userId): int
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('User tidak valid.');
        }

        $items = $data['items'] ?? [];

        if (!is_array($items) || count($items) === 0) {
            throw new InvalidArgumentException('Item penjualan belum diisi.');
        }

        $this->db->transBegin();

        try {
            $customerId = !empty($data['customer_id'])
                ? (int) $data['customer_id']
                : null;

            if ($customerId !== null) {
                $customer = $this->db->table('customers')
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

            $subtotal = 0.0;
            $preparedItems = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException(
                        'Format item penjualan tidak valid.'
                    );
                }

                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = $this->numericValue(
                    $item['quantity'] ?? null,
                    'Jumlah produk'
                );
                $unitPrice = $this->numericValue(
                    $item['unit_price'] ?? null,
                    'Harga produk'
                );
                $discount = $this->numericValue(
                    $item['discount_amount'] ?? 0,
                    'Diskon item'
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
                    'notes'           => $item['notes'] ?? null,
                ];
            }

            $subtotal = round($subtotal, 2);

            $headerDiscount = $this->numericValue(
                $data['discount_amount'] ?? 0,
                'Diskon transaksi'
            );

            $taxAmount = $this->numericValue(
                $data['tax_amount'] ?? 0,
                'Pajak'
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

            if ($totalAmount < 0) {
                throw new InvalidArgumentException(
                    'Total penjualan tidak valid.'
                );
            }

            $now = date('Y-m-d H:i:s');
            $saleNumber = $this->generateSaleNumber();

            $this->db->table('sales')->insert([
                'sale_number'     => $saleNumber,
                'customer_id'     => $customerId,
                'sale_date'       => $saleDate,
                'status'          => 'draft',
                'subtotal'        => $subtotal,
                'discount_amount' => $headerDiscount,
                'tax_amount'      => $taxAmount,
                'total_amount'    => $totalAmount,
                'paid_amount'     => 0,
                'payment_status'  => 'unpaid',
                'notes'           => $data['notes'] ?? null,
                'created_by'      => $userId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $saleId = (int) $this->db->insertID();

            if ($saleId < 1) {
                throw new RuntimeException(
                    'Gagal membuat transaksi penjualan.'
                );
            }

            foreach ($preparedItems as $item) {
                $item['sale_id'] = $saleId;
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                $this->db->table('sale_items')->insert($item);
            }

            $this->assertTransaction();

            $this->db->transCommit();

            return $saleId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Posting transaksi POS.
     *
     * - Mengunci transaksi dan item.
     * - Mengalokasikan stok FEFO.
     * - Memecah item jika stok berasal dari beberapa batch.
     * - Mengurangi stok batch.
     * - Mencatat stock movement.
     * - Mencatat pembayaran dan kas masuk.
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

        $this->db->transBegin();

        try {
            $sale = $this->db->query(
                'SELECT * FROM sales WHERE id = ? FOR UPDATE',
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

            $items = $this->db->query(
                'SELECT *
                 FROM sale_items
                 WHERE sale_id = ?
                 ORDER BY id ASC
                 FOR UPDATE',
                [$saleId]
            )->getResultArray();

            if (count($items) === 0) {
                throw new RuntimeException(
                    'Transaksi tidak memiliki item.'
                );
            }

            $totalAmount = (float) $sale['total_amount'];

            $paidAmount = $this->numericValue(
                $payment['amount'] ?? 0,
                'Jumlah pembayaran'
            );

            if ($paidAmount < 0 || $paidAmount > $totalAmount) {
                throw new InvalidArgumentException(
                    'Jumlah pembayaran tidak valid.'
                );
            }

            $paymentMethod = null;
            $referenceNumber = $this->nullableString(
                $payment['reference_number'] ?? null
            );

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

            // Validasi pembayaran dilakukan sebelum perubahan stok.
            foreach ($items as $item) {
                $this->allocateStock($sale, $item, $userId);
            }

            if ($paidAmount > 0) {
                $this->recordPayment(
                    $sale,
                    $paidAmount,
                    $paymentMethod,
                    $referenceNumber,
                    $userId
                );
            }

            $paymentStatus = $totalAmount <= 0
                || $paidAmount >= $totalAmount
                ? 'paid'
                : ($paidAmount > 0 ? 'partial' : 'unpaid');

            $this->db->table('sales')
                ->where('id', $saleId)
                ->update([
                    'status'           => 'posted',
                    'paid_amount'      => $paidAmount,
                    'payment_status'   => $paymentStatus,
                    'payment_method'   => $paymentMethod,
                    'reference_number' => $referenceNumber,
                    'updated_by'       => $userId,
                    'updated_at'       => date('Y-m-d H:i:s'),
                ]);

            $this->assertTransaction();

            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Menahan transaksi POS.
     *
     * Draft yang ditahan tetap tersimpan dan tidak mengubah stok.
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

    /**
     * Melanjutkan transaksi POS yang ditahan.
     */
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

    /**
     * Mengubah status draft secara atomik.
     */
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

        $this->db->transBegin();

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

            $this->db->table('sales')
                ->where('id', $saleId)
                ->where('status', $currentStatus)
                ->update([
                    'status'     => $targetStatus,
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->assertTransaction();

            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException(
                    'Status transaksi gagal diperbarui.'
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
     * Membatalkan draft.
     *
     * Draft belum mengurangi stok sehingga tidak memerlukan
     * stock movement pembalik.
     */
    public function cancelSale(int $saleId, int $userId): bool
    {
        if ($saleId < 1 || $userId < 1) {
            throw new InvalidArgumentException(
                'ID transaksi atau user tidak valid.'
            );
        }

        $this->db->transBegin();

        try {
            $sale = $this->db->query(
                'SELECT * FROM sales WHERE id = ? FOR UPDATE',
                [$saleId]
            )->getRowArray();

            if (!$sale) {
                throw new RuntimeException(
                    'Transaksi penjualan tidak ditemukan.'
                );
            }
            if (!in_array($sale['status'], ['draft', 'held'], true)) {
                throw new RuntimeException(
                    'Hanya transaksi draft atau held yang dapat dibatalkan.'
                );
            }
            if ($sale['status'] !== 'draft') {
                throw new RuntimeException(
                    'Hanya transaksi draft yang dapat dibatalkan.'
                );
            }

            $this->db->table('sales')
                ->where('id', $saleId)
                ->update([
                    'status'     => 'cancelled',
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->assertTransaction();

            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Daftar penjualan dengan pagination.
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

    /**
     * Detail penjualan beserta item dan informasi batch.
     */
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

    /**
     * Alokasi stok berdasarkan FEFO.
     *
     * Satu baris item draft dapat menjadi beberapa baris
     * sale_items ketika stok berasal dari beberapa batch.
     */
    private function allocateStock(
        array $sale,
        array $item,
        int $userId
    ): void {
        $saleDate = date(
            'Y-m-d',
            strtotime($sale['sale_date'])
        );

        $batches = $this->db->query(
            "SELECT *
             FROM product_batches
             WHERE product_id = ?
               AND status = 'active'
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

        $remainingQuantity = (float) $item['quantity'];
        $remainingDiscount = (float) $item['discount_amount'];
        $originalQuantity = $remainingQuantity;

        $firstAllocation = true;
        $now = date('Y-m-d H:i:s');

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0.000001) {
                break;
            }

            $available = (float) $batch['quantity_remaining'];
            $allocated = min($remainingQuantity, $available);

            if ($allocated <= 0) {
                continue;
            }

            $isLastAllocation =
                $allocated >= $remainingQuantity - 0.000001;

            if ($isLastAllocation) {
                $allocatedDiscount = $remainingDiscount;
            } else {
                $allocatedDiscount = round(
                    (float) $item['discount_amount']
                        * ($allocated / $originalQuantity),
                    2
                );

                $allocatedDiscount = min(
                    $allocatedDiscount,
                    $remainingDiscount
                );
            }

            $allocatedSubtotal = round(
                ($allocated * (float) $item['unit_price'])
                    - $allocatedDiscount,
                2
            );

            $unitCost = (float) $batch['unit_cost'];

            $this->db->table('product_batches')
                ->where('id', $batch['id'])
                ->update([
                    'quantity_remaining' => round(
                        $available - $allocated,
                        3
                    ),
                    'updated_at' => $now,
                ]);

            $allocatedItem = [
                'sale_id'         => $sale['id'],
                'product_id'      => $item['product_id'],
                'batch_id'        => $batch['id'],
                'quantity'        => $allocated,
                'unit_price'      => $item['unit_price'],
                'discount_amount' => $allocatedDiscount,
                'subtotal'        => $allocatedSubtotal,
                'unit_cost'       => $unitCost,
                'notes'           => $item['notes'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            if ($firstAllocation) {
                $this->db->table('sale_items')
                    ->where('id', $item['id'])
                    ->update($allocatedItem);

                $firstAllocation = false;
            } else {
                $this->db->table('sale_items')
                    ->insert($allocatedItem);
            }

            $this->db->table('stock_movements')->insert([
                'product_id'     => $item['product_id'],
                'batch_id'       => $batch['id'],
                'movement_date'  => $sale['sale_date'],
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

            $remainingQuantity = round(
                $remainingQuantity - $allocated,
                6
            );

            $remainingDiscount = max(
                0,
                round($remainingDiscount - $allocatedDiscount, 2)
            );
        }

        if ($remainingQuantity > 0.000001) {
            throw new RuntimeException(
                "Stok produk ID {$item['product_id']} tidak mencukupi."
            );
        }
    }

    /**
     * Mencatat pembayaran POS.
     */
    private function recordPayment(
        array $sale,
        float $amount,
        string $method,
        ?string $referenceNumber,
        int $userId
    ): void {
        $now = date('Y-m-d H:i:s');

        $paymentNumber = $this->generateNumber('CP');
        $transactionNumber = $this->generateNumber('CT');

        // Simpan juga ke ledger pembayaran baru agar pembayaran POS
        // lama tetap terbaca oleh fondasi hybrid payment.
        $methodCode = match ($method) {
            'cash' => 'cash',
            'transfer' => 'bank_transfer',
            'qris' => 'qris',
            'debit', 'credit' => 'card',
            default => 'other',
        };

        $methodRow = $this->db->table('payment_methods')
            ->where('code', $methodCode)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if (!$methodRow) {
            throw new RuntimeException('Master metode pembayaran belum tersedia: ' . $methodCode);
        }

        $this->db->table('sale_payments')->insert([
            'payment_number' => $paymentNumber,
            'sale_id' => $sale['id'],
            'payment_method_id' => $methodRow['id'],
            'provider_id' => null,
            'amount' => $amount,
            'status' => 'paid',
            'processing_type' => 'manual',
            'reference_number' => $referenceNumber,
            'external_reference' => null,
            'idempotency_key' => 'pos-sale-' . $sale['id'] . '-initial',
            'notes' => 'Pembayaran POS ' . $sale['sale_number'],
            'created_by' => $userId,
            'verified_by' => $userId,
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Customer walk-in tidak dicatat ke customer_payments
        // jika tidak memiliki customer_id.
        if (!empty($sale['customer_id'])) {
            $this->db->table('customer_payments')->insert([
                'payment_number'       => $paymentNumber,
                'customer_id'          => $sale['customer_id'],
                'sale_id'              => $sale['id'],
                'distribution_order_id' => null,
                'payment_date'         => $now,
                'amount'               => $amount,
                'payment_method'       => $method,
                'reference_number'     => $referenceNumber,
                'notes'                => 'Pembayaran POS ' . $sale['sale_number'],
                'created_by'           => $userId,
                'created_at'           => $now,
            ]);
        }

        $this->db->table('cash_transactions')->insert([
            'transaction_number' => $transactionNumber,
            'transaction_date'   => $now,
            'transaction_type'   => 'income',
            'reference_type'     => 'sale',
            'reference_id'       => $sale['id'],
            'description'        => 'Pembayaran POS ' . $sale['sale_number'],
            'amount'             => $amount,
            'payment_method'     => $method,
            'created_by'         => $userId,
            'created_at'         => $now,
        ]);
    }

    /**
     * Nomor transaksi POS otomatis.
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

    /**
     * Nomor pembayaran dan transaksi kas.
     */
    private function generateNumber(string $prefix): string
    {
        return $prefix
            . date('YmdHis')
            . random_int(100000, 999999);
    }

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

    private function assertTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new RuntimeException(
                'Terjadi kegagalan saat menyimpan transaksi database.'
            );
        }
    }
}
