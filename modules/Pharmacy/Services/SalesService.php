<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * SalesService
 *
 * Service layer untuk semua operasi penjualan POS:
 * - Create draft (dengan validasi produk, customer, stok)
 * - Post sale (dengan FEFO allocation, payment, settlement)
 * - Checkout (atomic create+post)
 * - Hold / Resume / Cancel
 * - Query sales & detail
 *
 * Semua operasi mutasi dibungkus dalam transaksi database.
 * Semua input divalidasi ketat sebelum menyentuh database.
 *
 * @package Modules\Pharmacy\Services
 */
class SalesService
{
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

    private const MAX_DUE_DATE_HORIZON_DAYS = 365;

    private const MAX_PAYMENT_ROUNDING_TOLERANCE = 0.05;

    private const MAX_SALE_NUMBER_ATTEMPTS = 5;

    private const MAX_CUSTOMER_CODE_ATTEMPTS = 5;

    private $db;

    private bool $transactionStarted = false;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    // =========================================================
    // CREATE DRAFT
    // =========================================================

    /**
     * Membuat draft penjualan baru.
     *
     * Validasi:
     * - Minimal 1 item.
     * - Produk harus aktif.
     * - Customer (jika ada) harus aktif.
     * - Tanggal harus hari ini (toleransi 5 menit ke depan).
     * - Diskon tidak boleh melebihi gross/subtotal.
     * - Total > 0.
     *
     * @param array $data Payload dari FE (CreateSalePayload).
     * @param int $userId ID user yang membuat.
     * @return int ID sale yang dibuat.
     * @throws InvalidArgumentException Jika input tidak valid.
     * @throws RuntimeException Jika operasi database gagal.
     */
    public function createDraft(array $data, int $userId): int
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('User tidak valid.');
        }

        $items = $data['items'] ?? [];

        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('Item penjualan belum diisi.');
        }

        $this->beginTransaction();

        try {
            $now = date('Y-m-d H:i:s');

            $customerId = $this->resolveCustomerId($data);

            $saleDate = $this->normalizeDate($data['sale_date'] ?? null);

            [$subtotal, $preparedItems] = $this->prepareItems($items);

            $headerDiscount = round($this->numericValue($data['discount_amount'] ?? 0, 'Diskon transaksi'), 2);

            $taxAmount = round($this->numericValue($data['tax_amount'] ?? 0, 'Pajak'), 2);

            $this->assertNonNegative($headerDiscount, 'Diskon transaksi');
            $this->assertNonNegative($taxAmount, 'Pajak');

            if ($headerDiscount > $subtotal) {
                throw new InvalidArgumentException('Diskon transaksi melebihi subtotal.');
            }

            $totalAmount = round($subtotal - $headerDiscount + $taxAmount, 2);

            if (!is_finite($totalAmount) || $totalAmount <= 0) {
                throw new InvalidArgumentException('Total penjualan harus lebih besar dari nol.');
            }

            $saleNumber = $this->generateSaleNumber();

            $inserted = $this->db->table('sales')->insert([
                'sale_number'      => $saleNumber,
                'customer_id'      => $customerId,
                'sale_date'        => $saleDate,
                'status'           => 'draft',
                'subtotal'         => $subtotal,
                'discount_amount'  => $headerDiscount,
                'tax_amount'       => $taxAmount,
                'total_amount'     => $totalAmount,
                'paid_amount'      => 0,
                'amount_received'  => 0,
                'change_amount'    => 0,
                'settlement_mode'  => 'paid',
                'payment_status'   => 'unpaid',
                'payment_method'   => null,
                'reference_number' => null,
                'due_date'         => null,
                'notes'            => $this->nullableString($data['notes'] ?? null),
                'created_by'       => $userId,
                'updated_by'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            if (!$inserted) {
                throw new RuntimeException('Gagal membuat transaksi penjualan.');
            }

            $saleId = (int) $this->db->insertID();

            if ($saleId < 1) {
                throw new RuntimeException('ID transaksi penjualan tidak berhasil dibuat.');
            }

            $this->insertSaleItems($saleId, $preparedItems, $now);

            $this->commitTransaction();

            return $saleId;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    // =========================================================
    // POST SALE
    // =========================================================

    /**
     * Memposting draft penjualan menjadi transaksi final.
     *
     * Alur:
     * 1. Lock header & items (FOR UPDATE).
     * 2. Validasi status draft.
     * 3. Validasi payment & settlement mode.
     * 4. FEFO stock allocation.
     * 5. Record payment & cash transaction.
     * 6. Update header ke status 'posted'.
     *
     * @param int $saleId
     * @param array $payment Payment payload (sudah dinormalisasi controller).
     * @param int $userId
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function postSale(int $saleId, array $payment, int $userId): bool
    {
        if ($saleId < 1 || $userId < 1) {
            throw new InvalidArgumentException('ID transaksi atau user tidak valid.');
        }

        $this->beginTransaction();

        try {
            $sale = $this->lockSaleHeader($saleId);

            if ($sale['status'] !== 'draft') {
                throw new RuntimeException('Hanya transaksi draft yang dapat diposting.');
            }

            $items = $this->lockSaleItems($saleId);

            if ($items === []) {
                throw new RuntimeException('Transaksi tidak memiliki item.');
            }

            $totalAmount = round($this->numericValue($sale['total_amount'], 'Total transaksi'), 2);

            if ($totalAmount <= 0) {
                throw new InvalidArgumentException('Total transaksi harus lebih besar dari nol.');
            }

            $paidAmount = $this->resolvePaidAmount($payment, $totalAmount);

            $paymentMethod = $this->resolvePaymentMethod($payment, $paidAmount);

            $requestedSettlementMode = $this->resolveRequestedSettlementMode($payment);

            if ($requestedSettlementMode !== 'paid') {
                $sale = $this->ensureCreditCustomer($sale, $payment, $userId);
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
                $this->allocateStock($sale, $item, $userId);
            }

            // Catat pembayaran jika ada nominal yang diterapkan.
            if ($paidAmount > 0) {
                $this->recordPayment(
                    $sale,
                    $paidAmount,
                    $paymentMethod,
                    $this->nullableString($payment['reference_number'] ?? null),
                    $userId
                );
            }

            $paymentStatus = $this->resolvePaymentStatus($totalAmount, $paidAmount);

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
                    'reference_number' => $this->nullableString($payment['reference_number'] ?? null),
                    'due_date'         => $settlement['due_date'],
                    'customer_id'      => !empty($sale['customer_id']) ? (int) $sale['customer_id'] : null,
                    'collateral_note'  => $sale['collateral_note'] ?? null,
                    'updated_by'       => $userId,
                    'updated_at'       => $now,
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException('Status transaksi gagal diperbarui.');
            }

            $this->commitTransaction();

            return true;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    // =========================================================
    // CHECKOUT (ATOMIC CREATE + POST)
    // =========================================================

    /**
     * Membuat draft lalu posting dalam SATU transaksi database.
     *
     * Jika posting gagal, draft ikut di-rollback (tidak ada orphan).
     *
     * @param array $data
     * @param array $payment
     * @param int $userId
     * @return int
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */


    public function checkout(
        array $data,
        array $payment,
        int $userId,
        ?string $idempotencyKey = null
    ): int {
        if ($userId < 1) {
            throw new InvalidArgumentException('User tidak valid.');
        }

        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency-Key wajib dikirim.');
        }

        $idempotencyKey = trim($idempotencyKey);

        if (strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Idempotency-Key maksimal 128 karakter.');
        }

        $this->beginTransaction();

        try {
            if (($payment['settlement_mode'] ?? '') === 'paid') {
                $payment = $this->normalizePaidRounding($data, $payment);
            }

            $payloadHash = $this->createCheckoutPayloadHash($data, $payment);
            $now = date('Y-m-d H:i:s');

            // INSERT IGNORE + unique index menangani request bersamaan.
            $this->db->query(
                'INSERT IGNORE INTO sale_sync_requests
                (user_id, idempotency_key, payload_hash, sale_id, created_at)
             VALUES (?, ?, ?, NULL, ?)',
                [$userId, $idempotencyKey, $payloadHash, $now]
            );

            if ($this->db->error()['code'] !== 0) {
                throw new RuntimeException('Gagal menyimpan kunci idempotensi.');
            }

            // Lock baris agar request dengan key sama diproses berurutan.
            $syncRequest = $this->db->query(
                'SELECT id, payload_hash, sale_id
             FROM sale_sync_requests
             WHERE user_id = ? AND idempotency_key = ?
             FOR UPDATE',
                [$userId, $idempotencyKey]
            )->getRowArray();

            if (!$syncRequest) {
                throw new RuntimeException('Data idempotensi gagal dibaca.');
            }

            if (!hash_equals(
                (string) $syncRequest['payload_hash'],
                $payloadHash
            )) {
                throw new \DomainException(
                    'Idempotency-Key sudah digunakan untuk payload transaksi yang berbeda.'
                );
            }

            // Request yang sama sudah pernah berhasil.
            if ($syncRequest['sale_id'] !== null) {
                $saleId = (int) $syncRequest['sale_id'];

                $this->commitTransaction();

                return $saleId;
            }

            // Belum pernah berhasil: proses checkout dalam transaksi yang sama.
            $saleId = $this->createDraftInternal($data, $userId);

            $this->postSaleInternal($saleId, $payment, $userId);

            $updated = $this->db->table('sale_sync_requests')
                ->where('id', (int) $syncRequest['id'])
                ->update([
                    'sale_id'    => $saleId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException('Referensi transaksi idempotensi gagal diperbarui.');
            }

            $this->commitTransaction();

            return $saleId;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Hash payload checkout secara deterministik.
     */
    private function createCheckoutPayloadHash(
        array $data,
        array $payment
    ): string {
        $payload = [
            'sale'    => $this->normalizeHashValue($data),
            'payment' => $this->normalizeHashValue($payment),
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
        );

        return hash('sha256', $json);
    }

    /**
     * Urutkan key associative array, tetapi pertahankan urutan list.
     */
    private function normalizeHashValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn($item) => $this->normalizeHashValue($item),
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeHashValue($item);
        }

        return $value;
    }


    public function checkoutOld(
        array $data,
        array $payment,
        int $userId,
        ?string $idempotencyKey = null
    ): int {
        if ($userId < 1) {
            throw new InvalidArgumentException('User tidak valid.');
        }

        $this->beginTransaction();

        try {
            // Normalisasi pembulatan untuk pelunasan penuh.
            if (($payment['settlement_mode'] ?? '') === 'paid') {
                $payment = $this->normalizePaidRounding($data, $payment);
            }

            $saleId = $this->createDraftInternal($data, $userId);

            $this->postSaleInternal($saleId, $payment, $userId);

            $this->commitTransaction();

            return $saleId;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Versi internal createDraft TANPA transaksi (untuk dipakai checkout).
     *
     * @param array $data
     * @param int $userId
     * @return int
     */
    private function createDraftInternal(array $data, int $userId): int
    {
        $items = $data['items'] ?? [];

        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('Item penjualan belum diisi.');
        }

        $now = date('Y-m-d H:i:s');

        $customerId = $this->resolveCustomerId($data);

        $saleDate = $this->normalizeDate($data['sale_date'] ?? null);

        [$subtotal, $preparedItems] = $this->prepareItems($items);

        $headerDiscount = round($this->numericValue($data['discount_amount'] ?? 0, 'Diskon transaksi'), 2);

        $taxAmount = round($this->numericValue($data['tax_amount'] ?? 0, 'Pajak'), 2);

        $this->assertNonNegative($headerDiscount, 'Diskon transaksi');
        $this->assertNonNegative($taxAmount, 'Pajak');

        if ($headerDiscount > $subtotal) {
            throw new InvalidArgumentException('Diskon transaksi melebihi subtotal.');
        }

        $totalAmount = round($subtotal - $headerDiscount + $taxAmount, 2);

        if (!is_finite($totalAmount) || $totalAmount <= 0) {
            throw new InvalidArgumentException('Total penjualan harus lebih besar dari nol.');
        }

        $saleNumber = $this->generateSaleNumber();

        try {
            $inserted = $this->db->table('sales')->insert([
                'sale_number'      => $saleNumber,
                'customer_id'      => $customerId,
                'sale_date'        => $saleDate,
                'status'           => 'draft',
                'subtotal'         => $subtotal,
                'discount_amount'  => $headerDiscount,
                'tax_amount'       => $taxAmount,
                'total_amount'     => $totalAmount,
                'paid_amount'      => 0,
                'amount_received'  => 0,
                'change_amount'    => 0,
                'settlement_mode'  => 'paid',
                'payment_status'   => 'unpaid',
                'payment_method'   => null,
                'reference_number' => null,
                'due_date'         => null,
                'notes'            => $this->nullableString($data['notes'] ?? null),
                'created_by'       => $userId,
                'updated_by'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new RuntimeException('Nomor penjualan duplikat. Silakan coba lagi.');
            }
            throw $e;
        }

        $saleId = (int) $this->db->insertID();

        if ($saleId < 1) {
            throw new RuntimeException('ID transaksi penjualan tidak berhasil dibuat.');
        }

        $this->insertSaleItems($saleId, $preparedItems, $now);

        return $saleId;
    }



    /**
     * Versi internal postSale TANPA transaksi (untuk dipakai checkout).
     *
     * @param int $saleId
     * @param array $payment
     * @param int $userId
     * @return bool
     */
    private function postSaleInternal(int $saleId, array $payment, int $userId): bool
    {
        $sale = $this->lockSaleHeader($saleId);

        if ($sale['status'] !== 'draft') {
            throw new RuntimeException('Hanya transaksi draft yang dapat diposting.');
        }

        $items = $this->lockSaleItems($saleId);

        if ($items === []) {
            throw new RuntimeException('Transaksi tidak memiliki item.');
        }

        $totalAmount = round($this->numericValue($sale['total_amount'], 'Total transaksi'), 2);

        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('Total transaksi harus lebih besar dari nol.');
        }

        $paidAmount = $this->resolvePaidAmount($payment, $totalAmount);

        $paymentMethod = $this->resolvePaymentMethod($payment, $paidAmount);

        $requestedSettlementMode = $this->resolveRequestedSettlementMode($payment);

        if ($requestedSettlementMode !== 'paid') {
            $sale = $this->ensureCreditCustomer($sale, $payment, $userId);
        }

        $settlement = $this->resolveSettlement(
            $sale,
            $totalAmount,
            $paidAmount,
            $paymentMethod,
            $payment,
            $requestedSettlementMode
        );

        foreach ($items as $item) {
            $this->allocateStock($sale, $item, $userId);
        }

        if ($paidAmount > 0) {
            $this->recordPayment(
                $sale,
                $paidAmount,
                $paymentMethod,
                $this->nullableString($payment['reference_number'] ?? null),
                $userId
            );
        }

        $paymentStatus = $this->resolvePaymentStatus($totalAmount, $paidAmount);

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
                'reference_number' => $this->nullableString($payment['reference_number'] ?? null),
                'due_date'         => $settlement['due_date'],
                'customer_id'      => !empty($sale['customer_id']) ? (int) $sale['customer_id'] : null,
                'collateral_note'  => $sale['collateral_note'] ?? null,
                'updated_by'       => $userId,
                'updated_at'       => $now,
            ]);

        if (!$updated || $this->db->affectedRows() !== 1) {
            throw new RuntimeException('Status transaksi gagal diperbarui.');
        }

        return true;
    }

    // =========================================================
    // HOLD / RESUME / CANCEL
    // =========================================================

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

    /**
     * Mengubah status draft/held.
     *
     * @param int $saleId
     * @param int $userId
     * @param string $currentStatus
     * @param string $targetStatus
     * @param string $errorMessage
     * @return bool
     */
    private function changeDraftState(
        int $saleId,
        int $userId,
        string $currentStatus,
        string $targetStatus,
        string $errorMessage
    ): bool {
        if ($saleId < 1 || $userId < 1) {
            throw new InvalidArgumentException('ID transaksi atau user tidak valid.');
        }

        $this->beginTransaction();

        try {
            $sale = $this->db->query(
                'SELECT id, status FROM sales WHERE id = ? FOR UPDATE',
                [$saleId]
            )->getRowArray();

            if (!$sale) {
                throw new RuntimeException('Transaksi penjualan tidak ditemukan.');
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
                throw new RuntimeException('Status transaksi gagal diperbarui.');
            }

            $this->commitTransaction();

            return true;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Membatalkan transaksi draft/held.
     *
     * @param int $saleId
     * @param int $userId
     * @param bool $restoreHeld Jika true dan sale sebelumnya draft hasil resume,
     *                          kembalikan ke held alih-alih cancelled.
     *                          Default false untuk kompatibilitas.
     * @return bool
     */
    public function cancelSale(int $saleId, int $userId, bool $restoreHeld = false): bool
    {
        if ($saleId < 1 || $userId < 1) {
            throw new InvalidArgumentException('ID transaksi atau user tidak valid.');
        }

        $this->beginTransaction();

        try {
            $sale = $this->db->query(
                'SELECT id, status FROM sales WHERE id = ? FOR UPDATE',
                [$saleId]
            )->getRowArray();

            if (!$sale) {
                throw new RuntimeException('Transaksi penjualan tidak ditemukan.');
            }

            if (!in_array($sale['status'], ['draft', 'held'], true)) {
                throw new RuntimeException('Hanya transaksi draft atau held yang dapat dibatalkan.');
            }

            $targetStatus = ($restoreHeld && $sale['status'] === 'draft')
                ? 'held'
                : 'cancelled';

            $updated = $this->db->table('sales')
                ->where('id', $saleId)
                ->whereIn('status', ['draft', 'held'])
                ->update([
                    'status'     => $targetStatus,
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException('Transaksi gagal dibatalkan.');
            }

            $this->commitTransaction();

            return true;
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    // =========================================================
    // QUERIES
    // =========================================================

    /**
     * @param array $filters
     * @return array{data: array, pagination: array}
     */
    public function getSales(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 10)));

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

        $total = (clone $builder)->countAllResults();

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
     * @param int $saleId
     * @return array|null
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

    // =========================================================
    // FEFO STOCK ALLOCATION
    // =========================================================

    /**
     * Alokasikan stok FEFO untuk satu item draft.
     *
     * @param array $sale
     * @param array $item
     * @param int $userId
     * @return void
     */
    private function allocateStock(array $sale, array $item, int $userId): void
    {
        $movementDate = (new DateTimeImmutable((string) $sale['sale_date']))->format('Y-m-d H:i:s');

        $today = date('Y-m-d');

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
            [$item['product_id'], $today]
        )->getResultArray();

        $remainingQuantity = round((float) $item['quantity'], 3);

        if ($remainingQuantity <= 0) {
            throw new RuntimeException('Jumlah produk tidak valid saat alokasi stok.');
        }

        $originalQuantity = $remainingQuantity;

        $originalDiscount = round((float) $item['discount_amount'], 2);
        $originalSubtotal = round((float) $item['subtotal'], 2);
        $originalGross = round($originalSubtotal + $originalDiscount, 2);

        $expectedGross = round($originalQuantity * (float) $item['unit_price'], 2);

        if (abs($originalGross - $expectedGross) > 0.001) {
            throw new RuntimeException('Nilai bruto item tidak sesuai dengan kuantitas dan harga satuan.');
        }

        if ($originalDiscount < 0 || $originalSubtotal < 0 || $originalDiscount > $originalGross) {
            throw new RuntimeException('Nilai diskon atau subtotal item tidak valid.');
        }

        $remainingGross = $originalGross;
        $remainingDiscount = $originalDiscount;

        $firstAllocation = true;
        $now = date('Y-m-d H:i:s');

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $available = round((float) $batch['quantity_remaining'], 3);

            if ($available <= 0) {
                continue;
            }

            $allocated = round(min($remainingQuantity, $available), 3);

            if ($allocated <= 0) {
                continue;
            }

            $isLastAllocation = ($allocated >= $remainingQuantity);

            if ($isLastAllocation) {
                $allocatedGross = $remainingGross;
                $allocatedDiscount = $remainingDiscount;
            } else {
                $allocatedGross = round($originalGross * ($allocated / $originalQuantity), 2);
                $allocatedGross = min($allocatedGross, $remainingGross);

                $allocatedDiscount = round($originalDiscount * ($allocated / $originalQuantity), 2);
                $allocatedDiscount = min($allocatedDiscount, $remainingDiscount);
            }

            $allocatedSubtotal = round($allocatedGross - $allocatedDiscount, 2);

            if ($allocatedGross < 0 || $allocatedDiscount < 0 || $allocatedSubtotal < 0) {
                throw new RuntimeException('Nilai hasil alokasi penjualan tidak valid.');
            }

            $unitCost = $this->numericValue($batch['unit_cost'], 'Harga pokok batch');

            $updated = $this->db->table('product_batches')
                ->where('id', $batch['id'])
                ->where('status', 'available')
                ->where('quantity_remaining >=', $allocated)
                ->update([
                    'quantity_remaining' => round($available - $allocated, 3),
                    'updated_at'         => $now,
                ]);

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException("Gagal memperbarui stok batch ID {$batch['id']}.");
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
                $updated = $this->db->table('sale_items')
                    ->where('id', $item['id'])
                    ->where('sale_id', $sale['id'])
                    ->update($allocatedItem);

                if (!$updated || $this->db->affectedRows() !== 1) {
                    throw new RuntimeException('Gagal memperbarui item penjualan hasil alokasi stok.');
                }

                $firstAllocation = false;
            } else {
                $allocatedItem['created_at'] = $now;

                $inserted = $this->db->table('sale_items')->insert($allocatedItem);

                if (!$inserted) {
                    throw new RuntimeException('Gagal menyimpan item penjualan hasil alokasi stok.');
                }
            }

            $movementInserted = $this->db->table('stock_movements')->insert([
                'product_id'     => $item['product_id'],
                'batch_id'       => $batch['id'],
                'movement_date'  => $movementDate,
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
                throw new RuntimeException('Gagal mencatat pergerakan stok.');
            }

            $remainingQuantity = round($remainingQuantity - $allocated, 3);
            $remainingGross = round($remainingGross - $allocatedGross, 2);
            $remainingDiscount = round($remainingDiscount - $allocatedDiscount, 2);
        }

        if ($remainingQuantity > 0) {
            throw new RuntimeException("Stok produk ID {$item['product_id']} tidak mencukupi.");
        }

        if (abs($remainingGross) > 0.001 || abs($remainingDiscount) > 0.001) {
            throw new RuntimeException('Alokasi nilai penjualan tidak konsisten.');
        }
    }

    // =========================================================
    // CREDIT CUSTOMER
    // =========================================================

    /**
     * Memastikan transaksi tempo/piutang memiliki customer_id.
     * Jika tidak ada, buat customer walk-in dari data pembeli.
     *
     * @param array $sale
     * @param array $payment
     * @param int $userId
     * @return array
     */
    private function ensureCreditCustomer(array $sale, array $payment, int $userId): array
    {
        if (!empty($sale['customer_id'])) {
            return $sale;
        }

        $name       = $this->nullableString($payment['buyer_name'] ?? null);
        $phone      = $this->nullableString($payment['buyer_phone'] ?? null);
        $collateral = $this->nullableString($payment['collateral_note'] ?? null);

        if ($name === null || $collateral === null) {
            throw new InvalidArgumentException(
                'Pembeli tempo non-terdaftar wajib diisi nama dan jaminan.'
            );
        }

        $now = date('Y-m-d H:i:s');

        $code = $this->generateCustomerCode();

        $inserted = $this->db->table('customers')->insert([
            'code'          => $code,
            'name'          => $name,
            'customer_type' => 'credit_walkin',
            'phone'         => $phone,
            'is_active'     => 1,
            'created_by'    => $userId,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        if (!$inserted) {
            throw new RuntimeException('Gagal membuat data pembeli tempo.');
        }

        $sale['customer_id']     = (int) $this->db->insertID();
        $sale['collateral_note'] = $collateral;

        return $sale;
    }

    // =========================================================
    // SETTLEMENT
    // =========================================================

    /**
     * @param array $sale
     * @param float $totalAmount
     * @param float $paidAmount
     * @param string|null $paymentMethod
     * @param array $payment
     * @param string $requestedSettlementMode
     * @return array{settlement_mode: string, due_date: ?string, amount_received: float, change_amount: float}
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

        if (!is_finite($totalAmount) || !is_finite($paidAmount) || $totalAmount <= 0 || $paidAmount < 0 || $paidAmount > $totalAmount) {
            throw new InvalidArgumentException('Nominal transaksi atau pembayaran tidak valid.');
        }

        if ($paidAmount > 0) {
            if ($paymentMethod === null || !in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
                throw new InvalidArgumentException('Metode pembayaran wajib dipilih.');
            }
        } else {
            $paymentMethod = null;
        }

        $remaining = round($totalAmount - $paidAmount, 2);

        if ($remaining <= 0) {
            $settlementMode = 'paid';
        } elseif ($paidAmount > 0) {
            $settlementMode = 'partial';
        } else {
            $settlementMode = 'receivable';
        }

        if ($settlementMode !== $requestedSettlementMode) {
            throw new InvalidArgumentException('Mode pembayaran tidak sesuai dengan nominal pembayaran.');
        }

        $dueDate = null;

        if ($remaining > 0) {
            if (empty($sale['customer_id'])) {
                throw new RuntimeException('Pelanggan wajib dipilih untuk transaksi tempo atau piutang.');
            }

            $dueDate = $this->normalizeDueDate($payment['due_date'] ?? null);
        }

        if (
            in_array($paymentMethod, ['transfer', 'qris'], true)
            && !filter_var($payment['payment_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ) {
            throw new RuntimeException('Pembayaran transfer atau QRIS harus dikonfirmasi kasir.');
        }

        $amountReceived = $paidAmount;
        $changeAmount = 0.00;

        if ($paymentMethod === 'cash') {
            $received = round($this->numericValue($payment['amount_received'] ?? 0, 'Uang diterima'), 2);

            if ($received <= 0) {
                $received = $paidAmount;
            }

            if ($received < $paidAmount) {
                throw new InvalidArgumentException('Uang diterima kurang dari jumlah pembayaran.');
            }

            $amountReceived = $received;
            $changeAmount = round($amountReceived - $paidAmount, 2);

            if ($settlementMode === 'partial' && $changeAmount > 0) {
                throw new InvalidArgumentException('Pembayaran tunai sebagian harus sesuai dengan uang yang diterima.');
            }
        }

        return [
            'settlement_mode' => $settlementMode,
            'due_date'        => $dueDate,
            'amount_received' => $amountReceived,
            'change_amount'   => $changeAmount,
        ];
    }

    // =========================================================
    // PAYMENT RECORDING
    // =========================================================

    /**
     * @param array $sale
     * @param float $amount
     * @param string $method
     * @param string|null $referenceNumber
     * @param int $userId
     * @return void
     */
    private function recordPayment(
        array $sale,
        float $amount,
        string $method,
        ?string $referenceNumber,
        int $userId
    ): void {
        $amount = round($amount, 2);

        if (!isset($sale['id']) || (int) $sale['id'] < 1 || $userId < 1) {
            throw new InvalidArgumentException('Referensi penjualan atau user tidak valid.');
        }

        if (!is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih besar dari nol.');
        }

        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        }

        $now = date('Y-m-d H:i:s');

        $paymentNumber = $this->generateNumber('customer_payments', 'payment_number', 'CP');
        $transactionNumber = $this->generateNumber('cash_transactions', 'transaction_number', 'CT');

        if (!empty($sale['customer_id'])) {
            $paymentInserted = $this->db->table('customer_payments')->insert([
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
                throw new RuntimeException('Gagal mencatat pembayaran pelanggan.');
            }
        }

        $cashInserted = $this->db->table('cash_transactions')->insert([
            'transaction_number' => $transactionNumber,
            'transaction_date'   => $now,
            'transaction_type'   => 'in',
            'reference_type'     => 'sale',
            'reference_id'       => $sale['id'],
            'description'        => 'Pembayaran POS ' . $sale['sale_number'],
            'amount'             => $amount,
            'payment_method'     => $method,
            'created_by'         => $userId,
            'created_at'         => $now,
        ]);

        if (!$cashInserted) {
            throw new RuntimeException('Gagal mencatat transaksi kas masuk.');
        }
    }

    // =========================================================
    // HELPERS (PRIVATE)
    // =========================================================

    private function resolveCustomerId(array $data): ?int
    {
        $customerId = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;

        if ($customerId === null) {
            return null;
        }

        $customer = $this->db->table('customers')
            ->select('id')
            ->where('id', $customerId)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if (!$customer) {
            throw new InvalidArgumentException('Pelanggan tidak ditemukan atau tidak aktif.');
        }

        return $customerId;
    }

    /**
     * @param array $items
     * @return array{0: float, 1: array}
     */
    private function prepareItems(array $items): array
    {
        $subtotal = 0.00;
        $preparedItems = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Format item penjualan tidak valid.');
            }

            $productId = (int) ($item['product_id'] ?? 0);

            $quantity = round($this->numericValue($item['quantity'] ?? null, 'Jumlah produk'), 3);

            $unitPrice = round($this->numericValue($item['unit_price'] ?? null, 'Harga produk'), 2);

            $discount = round($this->numericValue($item['discount_amount'] ?? 0, 'Diskon item'), 2);

            if ($productId < 1 || $quantity <= 0) {
                throw new InvalidArgumentException('Produk atau jumlah penjualan tidak valid.');
            }

            if ($unitPrice < 0 || $discount < 0) {
                throw new InvalidArgumentException('Harga atau diskon tidak valid.');
            }

            $product = $this->db->table('products')
                ->select('id')
                ->where('id', $productId)
                ->where('is_active', 1)
                ->get()
                ->getRowArray();

            if (!$product) {
                throw new InvalidArgumentException("Produk ID {$productId} tidak ditemukan atau tidak aktif.");
            }

            $gross = round($quantity * $unitPrice, 2);

            if ($discount > $gross) {
                throw new InvalidArgumentException('Diskon item melebihi nilai penjualan.');
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
                'notes'           => $this->nullableString($item['notes'] ?? null),
            ];
        }

        return [round($subtotal, 2), $preparedItems];
    }

    private function insertSaleItems(int $saleId, array $preparedItems, string $now): void
    {
        $itemsToInsert = [];

        foreach ($preparedItems as $item) {
            $item['sale_id'] = $saleId;
            $item['created_at'] = $now;
            $item['updated_at'] = $now;

            $itemsToInsert[] = $item;
        }

        if ($itemsToInsert === []) {
            return;
        }

        $inserted = $this->db->table('sale_items')->insertBatch($itemsToInsert);

        if ($inserted === false) {
            throw new RuntimeException('Gagal menyimpan item transaksi penjualan.');
        }

        if ((int) $inserted !== count($itemsToInsert)) {
            throw new RuntimeException('Jumlah item yang tersimpan tidak sesuai.');
        }
    }

    private function lockSaleHeader(int $saleId): array
    {
        $sale = $this->db->query(
            'SELECT * FROM sales WHERE id = ? FOR UPDATE',
            [$saleId]
        )->getRowArray();

        if (!$sale) {
            throw new RuntimeException('Transaksi penjualan tidak ditemukan.');
        }

        return $sale;
    }

    private function lockSaleItems(int $saleId): array
    {
        return $this->db->query(
            'SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC FOR UPDATE',
            [$saleId]
        )->getResultArray();
    }

    private function resolvePaidAmount(array $payment, float $totalAmount): float
    {
        $paidAmount = round($this->numericValue($payment['amount'] ?? null, 'Jumlah pembayaran'), 2);

        $appliedAmountRaw = $payment['applied_amount'] ?? $payment['amount'] ?? 0;

        $appliedAmount = round($this->numericValue($appliedAmountRaw, 'Nominal pembayaran diterapkan'), 2);

        if (
            $paidAmount < 0
            || $paidAmount > $totalAmount
            || abs($paidAmount - $appliedAmount) > 0.001
        ) {
            throw new InvalidArgumentException('Nominal pembayaran tidak valid atau tidak konsisten.');
        }

        return $paidAmount;
    }

    private function resolvePaymentMethod(array $payment, float $paidAmount): ?string
    {
        if ($paidAmount <= 0) {
            return null;
        }

        $method = trim((string) ($payment['payment_method'] ?? ''));

        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        }

        return $method;
    }

    private function resolveRequestedSettlementMode(array $payment): string
    {
        $mode = trim((string) ($payment['settlement_mode'] ?? ''));

        if (!in_array($mode, self::SETTLEMENT_MODES, true)) {
            throw new InvalidArgumentException('Mode pembayaran tidak valid.');
        }

        return $mode;
    }

    private function resolvePaymentStatus(float $totalAmount, float $paidAmount): string
    {
        $remaining = round($totalAmount - $paidAmount, 2);

        if ($remaining <= 0) {
            return 'paid';
        }

        return $paidAmount > 0 ? 'partial' : 'unpaid';
    }

    private function normalizePaidRounding(array $data, array $payment): array
    {
        // Hitung total dari data (tanpa query DB) untuk normalisasi awal.
        $total = $this->estimateTotalFromData($data);
        $paid = round((float) ($payment['amount'] ?? 0), 2);

        if ($total > 0 && abs($paid - $total) <= self::MAX_PAYMENT_ROUNDING_TOLERANCE) {
            $payment['amount'] = $total;
            $payment['applied_amount'] = $total;
        }

        return $payment;
    }

    private function estimateTotalFromData(array $data): float
    {
        $items = $data['items'] ?? [];

        if (!is_array($items) || $items === []) {
            return 0.0;
        }

        $subtotal = 0.0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);

            $subtotal += round($qty * $price - $discount, 2);
        }

        $headerDiscount = (float) ($data['discount_amount'] ?? 0);
        $taxAmount = (float) ($data['tax_amount'] ?? 0);

        return round($subtotal - $headerDiscount + $taxAmount, 2);
    }

    private function generateSaleNumber(): string
    {
        for ($attempt = 0; $attempt < self::MAX_SALE_NUMBER_ATTEMPTS; $attempt++) {
            $number = 'POS-' . date('Ymd-His') . '-' . random_int(100000, 999999);

            $exists = $this->db->table('sales')
                ->where('sale_number', $number)
                ->countAllResults() > 0;

            if (!$exists) {
                return $number;
            }
        }

        throw new RuntimeException('Gagal membuat nomor penjualan unik.');
    }

    private function generateCustomerCode(): string
    {
        for ($attempt = 0; $attempt < self::MAX_CUSTOMER_CODE_ATTEMPTS; $attempt++) {
            $code = 'TMP' . date('ymd') . random_int(1000, 9999);

            $exists = $this->db->table('customers')
                ->where('code', $code)
                ->countAllResults() > 0;

            if (!$exists) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal membuat kode pelanggan unik.');
    }

    private function generateNumber(string $table, string $column, string $prefix): string
    {
        for ($attempt = 0; $attempt < self::MAX_SALE_NUMBER_ATTEMPTS; $attempt++) {
            $number = $prefix . date('YmdHis') . random_int(100000, 999999);

            $exists = $this->db->table($table)
                ->where($column, $number)
                ->countAllResults() > 0;

            if (!$exists) {
                return $number;
            }
        }

        throw new RuntimeException("Gagal membuat nomor unik untuk {$table}.{$column}.");
    }

    // =========================================================
    // DATE / VALUE NORMALIZATION
    // =========================================================

    private function normalizeDate(mixed $value): string
    {
        $now = new DateTimeImmutable('now');

        if ($value === null || trim((string) $value) === '') {
            return $now->format('Y-m-d H:i:s');
        }

        try {
            $date = new DateTimeImmutable((string) $value);
        } catch (Throwable) {
            throw new InvalidArgumentException('Tanggal penjualan tidak valid.');
        }

        if (
            $date->format('Y-m-d') !== $now->format('Y-m-d')
            || $date > $now->modify('+5 minutes')
        ) {
            throw new InvalidArgumentException('Tanggal penjualan POS harus hari ini.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function normalizeDueDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            throw new InvalidArgumentException('Tanggal jatuh tempo wajib diisi.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $text
        ) {
            throw new InvalidArgumentException('Format tanggal jatuh tempo tidak valid.');
        }

        $today = new DateTimeImmutable('today');

        if ($date < $today) {
            throw new InvalidArgumentException('Tanggal jatuh tempo tidak boleh sebelum hari ini.');
        }

        $maxHorizon = $today->modify('+' . self::MAX_DUE_DATE_HORIZON_DAYS . ' days');

        if ($date > $maxHorizon) {
            throw new InvalidArgumentException(
                'Tanggal jatuh tempo tidak boleh lebih dari ' . self::MAX_DUE_DATE_HORIZON_DAYS . ' hari.'
            );
        }

        return $date->format('Y-m-d');
    }

    private function numericValue(mixed $value, string $label): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("{$label} harus berupa angka.");
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            throw new InvalidArgumentException("{$label} tidak valid.");
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

    private function assertNonNegative(float $value, string $label): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$label} tidak boleh negatif.");
        }
    }

    // =========================================================
    // TRANSACTION HELPERS
    // =========================================================

    private function beginTransaction(): void
    {
        if ($this->transactionStarted) {
            throw new RuntimeException('Transaksi database sudah aktif.');
        }

        if (!$this->db->transBegin()) {
            throw new RuntimeException('Gagal memulai transaksi database.');
        }

        $this->transactionStarted = true;
    }

    private function commitTransaction(): void
    {
        if (!$this->transactionStarted) {
            throw new RuntimeException('Tidak ada transaksi database yang aktif.');
        }

        if ($this->db->transStatus() === false) {
            $this->transactionStarted = false;

            throw new RuntimeException('Transaksi database mengalami kegagalan.');
        }

        if (!$this->db->transCommit()) {
            $this->transactionStarted = false;

            throw new RuntimeException('Gagal melakukan commit transaksi database.');
        }

        $this->transactionStarted = false;
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
