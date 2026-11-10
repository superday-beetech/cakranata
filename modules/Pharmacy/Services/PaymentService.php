<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PaymentService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    public function getMethods(): array
    {
        return $this->db->table('payment_methods')
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getPayment(int $paymentId): ?array
    {
        if ($paymentId < 1) {
            return null;
        }

        return $this->db->table('sale_payments sp')
            ->select('sp.*, pm.code AS method_code, pm.name AS method_name, pp.code AS provider_code')
            ->join('payment_methods pm', 'pm.id = sp.payment_method_id')
            ->join('payment_providers pp', 'pp.id = sp.provider_id', 'left')
            ->where('sp.id', $paymentId)
            ->get()
            ->getRowArray();
    }

    /**
     * Record cash immediately or bank transfer awaiting verification.
     * The sale must already be posted. Gateway payments use a separate adapter flow.
     */
    public function createManualPayment(array $data, int $userId): int
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('User tidak valid.');
        }

        $saleId = (int) ($data['sale_id'] ?? 0);
        $amount = $this->positiveAmount($data['amount'] ?? null);
        $methodCode = trim((string) ($data['method'] ?? ''));
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

        if ($saleId < 1) {
            throw new InvalidArgumentException('ID penjualan tidak valid.');
        }

        if (!in_array($methodCode, ['cash', 'bank_transfer'], true)) {
            throw new InvalidArgumentException('Metode manual tidak didukung.');
        }

        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $this->db->transBegin();

        try {
            $existing = $this->db->table('sale_payments')
                ->where('idempotency_key', $idempotencyKey)
                ->get()->getRowArray();

            if ($existing) {
                if ((int) $existing['sale_id'] !== $saleId
                    || (float) $existing['amount'] !== $amount) {
                    throw new RuntimeException('Idempotency key sudah digunakan untuk data berbeda.');
                }

                $this->assertTransaction();
                $this->db->transCommit();

                return (int) $existing['id'];
            }

            $sale = $this->lockPostedSale($saleId);
            $method = $this->getMethodByCode($methodCode);
            $this->assertOutstanding($sale, $amount);

            $now = date('Y-m-d H:i:s');
            $status = $methodCode === 'cash' ? 'paid' : 'pending_verification';

            $this->db->table('sale_payments')->insert([
                'payment_number' => $this->generateNumber('PAY'),
                'sale_id' => $saleId,
                'payment_method_id' => $method['id'],
                'provider_id' => null,
                'amount' => $amount,
                'status' => $status,
                'processing_type' => 'manual',
                'reference_number' => $this->nullableString($data['reference_number'] ?? null),
                'idempotency_key' => $idempotencyKey,
                'notes' => $this->nullableString($data['notes'] ?? null),
                'created_by' => $userId,
                'verified_by' => $methodCode === 'cash' ? $userId : null,
                'paid_at' => $methodCode === 'cash' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $paymentId = (int) $this->db->insertID();

            if ($paymentId < 1) {
                throw new RuntimeException('Gagal mencatat pembayaran.');
            }

            if ($status === 'paid') {
                $this->applyPaymentToSale($sale, $paymentId, $amount, 'cash', $userId, $now);
            }

            $this->assertTransaction();
            $this->db->transCommit();

            return $paymentId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function verifyManualPayment(
        int $paymentId,
        bool $approved,
        int $userId,
        ?string $notes = null
    ): bool {
        if ($paymentId < 1 || $userId < 1) {
            throw new InvalidArgumentException('ID pembayaran atau user tidak valid.');
        }

        $this->db->transBegin();

        try {
            $payment = $this->db->query(
                'SELECT * FROM sale_payments WHERE id = ? FOR UPDATE',
                [$paymentId]
            )->getRowArray();

            if (!$payment) {
                throw new RuntimeException('Pembayaran tidak ditemukan.');
            }

            if ($payment['processing_type'] !== 'manual'
                || $payment['status'] !== 'pending_verification') {
                throw new RuntimeException('Pembayaran tidak sedang menunggu verifikasi.');
            }

            $sale = $this->lockPostedSale((int) $payment['sale_id']);
            $now = date('Y-m-d H:i:s');
            $nextStatus = $approved ? 'paid' : 'failed';

            $this->db->table('payment_verifications')->insert([
                'sale_payment_id' => $paymentId,
                'verification_status' => $approved ? 'approved' : 'rejected',
                'reference_number' => $payment['reference_number'],
                'notes' => $this->nullableString($notes),
                'verified_by' => $userId,
                'verified_at' => $now,
                'created_at' => $now,
            ]);

            $this->db->table('sale_payments')
                ->where('id', $paymentId)
                ->where('status', 'pending_verification')
                ->update([
                    'status' => $nextStatus,
                    'verified_by' => $userId,
                    'paid_at' => $approved ? $now : null,
                    'updated_at' => $now,
                ]);

            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('Status pembayaran berubah. Muat ulang data.');
            }

            if ($approved) {
                $method = $this->db->table('payment_methods')
                    ->where('id', $payment['payment_method_id'])
                    ->get()->getRowArray();

                $this->applyPaymentToSale(
                    $sale,
                    $paymentId,
                    (float) $payment['amount'],
                    $method['code'] === 'bank_transfer' ? 'transfer' : $method['code'],
                    $userId,
                    $now
                );
            }

            $this->assertTransaction();
            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function lockPostedSale(int $saleId): array
    {
        $sale = $this->db->query(
            'SELECT * FROM sales WHERE id = ? FOR UPDATE',
            [$saleId]
        )->getRowArray();

        if (!$sale || $sale['status'] !== 'posted') {
            throw new RuntimeException('Pembayaran hanya dapat dicatat untuk penjualan posted.');
        }

        return $sale;
    }

    private function getMethodByCode(string $code): array
    {
        $method = $this->db->table('payment_methods')
            ->where('code', $code)
            ->where('is_active', 1)
            ->get()->getRowArray();

        if (!$method) {
            throw new RuntimeException('Metode pembayaran tidak aktif atau tidak ditemukan.');
        }

        return $method;
    }

    private function assertOutstanding(array $sale, float $amount): void
    {
        // sales.paid_amount remains the authoritative accumulated total,
        // including legacy POS payments created before this payment ledger.
        $paid = (float) $sale['paid_amount'];

        $outstanding = round((float) $sale['total_amount'] - $paid, 2);

        if ($amount > $outstanding) {
            throw new InvalidArgumentException('Nominal melebihi sisa tagihan.');
        }
    }

    private function applyPaymentToSale(
        array $sale,
        int $paymentId,
        float $amount,
        string $legacyMethod,
        int $userId,
        string $now
    ): void {
        // The sale row is locked by the caller. Add this payment to its
        // existing balance so payments created before the ledger migration
        // are not lost or counted twice.
        $paid = round((float) $sale['paid_amount'] + $amount, 2);

        $total = (float) $sale['total_amount'];
        $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $this->db->table('sales')
            ->where('id', $sale['id'])
            ->update([
                'paid_amount' => $paid,
                'payment_status' => $status,
                'payment_method' => $legacyMethod,
                'reference_number' => 'PAYMENT:' . $paymentId,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);

        $this->db->table('cash_transactions')->insert([
            'transaction_number' => $this->generateNumber('CT'),
            'transaction_date' => $now,
            'transaction_type' => 'income',
            'reference_type' => 'sale_payment',
            'reference_id' => $paymentId,
            'description' => 'Pembayaran POS ' . $sale['sale_number'],
            'amount' => $amount,
            'payment_method' => $legacyMethod,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        if (!empty($sale['customer_id'])) {
            $this->db->table('customer_payments')->insert([
                'payment_number' => $this->generateNumber('CP'),
                'customer_id' => $sale['customer_id'],
                'sale_id' => $sale['id'],
                'distribution_order_id' => null,
                'payment_date' => $now,
                'amount' => $amount,
                'payment_method' => $legacyMethod,
                'reference_number' => 'PAYMENT:' . $paymentId,
                'notes' => 'Pembayaran POS ' . $sale['sale_number'],
                'created_by' => $userId,
                'created_at' => $now,
            ]);
        }
    }

    private function positiveAmount(mixed $value): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih dari nol.');
        }

        return round((float) $value, 2);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix . date('YmdHis') . random_int(100000, 999999);
    }

    private function assertTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Gagal menyimpan transaksi pembayaran.');
        }
    }
}
