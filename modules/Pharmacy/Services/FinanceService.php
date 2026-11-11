<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class FinanceService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /**
     * Pembayaran kepada supplier.
     *
     * purchase_order_id boleh null untuk pembayaran umum supplier.
     */
    public function createSupplierPayment(
        array $data,
        int $userId
    ): int {
        $this->validatePaymentData($data);

        $this->db->transBegin();

        try {
            $supplierId = (int) $data['supplier_id'];
            $purchaseOrderId = !empty($data['purchase_order_id'])
                ? (int) $data['purchase_order_id']
                : null;

            $supplier = $this->db->table('suppliers')
                ->where('id', $supplierId)
                ->get()
                ->getRowArray();

            if (!$supplier) {
                throw new RuntimeException('Supplier tidak ditemukan.');
            }

            if ($purchaseOrderId !== null) {
                $order = $this->db->query(
                    'SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE',
                    [$purchaseOrderId]
                )->getRowArray();

                if (!$order) {
                    throw new RuntimeException('Purchase Order tidak ditemukan.');
                }

                if ((int) $order['supplier_id'] !== $supplierId) {
                    throw new RuntimeException(
                        'Supplier tidak sesuai dengan Purchase Order.'
                    );
                }

                $this->validateOutstanding(
                    'supplier_payments',
                    'purchase_order_id',
                    $purchaseOrderId,
                    (float) $order['total_amount'],
                    (float) $data['amount']
                );
            }

            $this->db->table('supplier_payments')->insert([
                'payment_number' => $data['payment_number'],
                'supplier_id' => $supplierId,
                'purchase_order_id' => $purchaseOrderId,
                'payment_date' => $data['payment_date'],
                'amount' => (float) $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $paymentId = (int) $this->db->insertID();

            $this->createCashTransaction([
                'transaction_number' => $this->makeCashTransactionNumber(
                    $data['payment_number']
                ),
                'transaction_date' => $data['payment_date'],
                'transaction_type' => 'out',
                'reference_type' => 'supplier_payment',
                'reference_id' => $paymentId,
                'description' => 'Pembayaran supplier: ' . $supplier['name'],
                'amount' => (float) $data['amount'],
                'payment_method' => $data['payment_method'],
                'created_by' => $userId,
            ]);

            $this->completeTransaction();

            return $paymentId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Pembayaran dari pelanggan.
     *
     * distribution_order_id boleh null untuk pembayaran umum pelanggan.
     */
    public function createCustomerPayment(array $data, int $userId): int
    {
        $this->validatePaymentData($data);

        $customerId = (int) ($data['customer_id'] ?? 0);
        $orderId    = !empty($data['distribution_order_id']) ? (int) $data['distribution_order_id'] : null;
        $saleId     = !empty($data['sale_id']) ? (int) $data['sale_id'] : null;

        if ($customerId < 1) {
            throw new RuntimeException('Pelanggan wajib ditentukan.');
        }

        if ($orderId !== null && $saleId !== null) {
            throw new RuntimeException('Pembayaran hanya boleh mengacu ke satu dokumen.');
        }

        $this->db->transBegin();

        try {
            $customer = $this->db->table('customers')->where('id', $customerId)->get()->getRowArray();
            if (!$customer) {
                throw new RuntimeException('Pelanggan tidak ditemukan.');
            }

            $amount = round((float) $data['amount'], 2);

            if ($orderId !== null) {
                $order = $this->db->query(
                    'SELECT * FROM distribution_orders WHERE id = ? FOR UPDATE',
                    [$orderId]
                )->getRowArray();

                if (!$order) {
                    throw new RuntimeException('Pesanan distribusi tidak ditemukan.');
                }
                if ((int) $order['customer_id'] !== $customerId) {
                    throw new RuntimeException('Pelanggan tidak sesuai dengan pesanan distribusi.');
                }
                if ($order['status'] === 'cancelled') {
                    throw new RuntimeException('Pesanan sudah dibatalkan.');
                }

                $this->validateOutstanding(
                    'customer_payments',
                    'distribution_order_id',
                    $orderId,
                    (float) $order['total_amount'],
                    $amount
                );
            }

            if ($saleId !== null) {
                $sale = $this->db->query(
                    'SELECT * FROM sales WHERE id = ? FOR UPDATE',
                    [$saleId]
                )->getRowArray();

                if (!$sale || $sale['status'] !== 'posted') {
                    throw new RuntimeException('Penjualan tidak ditemukan atau belum diposting.');
                }
                if ((int) $sale['customer_id'] !== $customerId) {
                    throw new RuntimeException('Pelanggan tidak sesuai dengan penjualan.');
                }

                // paid_amount di sales = pembayaran saat POS + pelunasan susulan.
                $outstanding = round((float) $sale['total_amount'] - (float) $sale['paid_amount'], 2);

                if ($outstanding <= 0) {
                    throw new RuntimeException('Penjualan sudah lunas.');
                }
                if ($amount > $outstanding + 0.0001) {
                    throw new RuntimeException('Pembayaran melebihi sisa piutang. Sisa: ' . $outstanding);
                }
            }

            $this->db->table('customer_payments')->insert([
                'payment_number'        => $data['payment_number'],
                'customer_id'           => $customerId,
                'sale_id'               => $saleId,
                'distribution_order_id' => $orderId,
                'payment_date'          => $data['payment_date'],
                'amount'                => $amount,
                'payment_method'        => $data['payment_method'],
                'reference_number'      => $data['reference_number'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'created_by'            => $userId,
            ]);
            $paymentId = (int) $this->db->insertID();

            if ($saleId !== null) {
                $newPaid = round((float) $sale['paid_amount'] + $amount, 2);
                $this->db->table('sales')->where('id', $saleId)->update([
                    'paid_amount'    => $newPaid,
                    'payment_status' => $newPaid + 0.0001 >= (float) $sale['total_amount'] ? 'paid' : 'partial',
                    'updated_by'     => $userId,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            $this->createCashTransaction([
                'transaction_number' => $this->makeCashTransactionNumber($data['payment_number']),
                'transaction_date'   => $data['payment_date'],
                'transaction_type'   => 'in',
                'reference_type'     => $saleId !== null ? 'sale_settlement' : 'customer_payment',
                'reference_id'       => $paymentId,
                'description'        => 'Pembayaran pelanggan: ' . $customer['name'],
                'amount'             => $amount,
                'payment_method'     => $data['payment_method'],
                'created_by'         => $userId,
            ]);

            $this->completeTransaction();

            return $paymentId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Mencatat pengeluaran operasional.
     */
    public function createExpense(
        array $data,
        int $userId
    ): int {
        foreach (
            [
                'expense_number',
                'expense_date',
                'category',
                'description',
                'amount',
                'payment_method',
            ] as $field
        ) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new RuntimeException("Field {$field} wajib diisi.");
            }
        }

        $amount = (float) $data['amount'];

        if (!is_finite($amount) || $amount <= 0) {
            throw new RuntimeException('Nominal pengeluaran harus lebih besar dari nol.');
        }

        $this->db->transBegin();

        try {
            $this->db->table('expenses')->insert([
                'expense_number' => $data['expense_number'],
                'expense_date' => $data['expense_date'],
                'category' => $data['category'],
                'description' => $data['description'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $expenseId = (int) $this->db->insertID();

            $this->createCashTransaction([
                'transaction_number' => $this->makeCashTransactionNumber(
                    $data['expense_number']
                ),
                'transaction_date' => $data['expense_date'],
                'transaction_type' => 'out',
                'reference_type' => 'expense',
                'reference_id' => $expenseId,
                'description' => $data['description'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'created_by' => $userId,
            ]);

            $this->completeTransaction();

            return $expenseId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Menghitung sisa tagihan PO atau pesanan pelanggan.
     */
    public function getOutstanding(string $type, int $referenceId): array
    {
        if ($type === 'sale') {
            $sale = $this->db->table('sales')->where('id', $referenceId)->get()->getRowArray();

            if (!$sale || $sale['status'] !== 'posted') {
                throw new RuntimeException('Penjualan tidak ditemukan atau belum diposting.');
            }

            $total = (float) $sale['total_amount'];
            $paid  = (float) $sale['paid_amount'];

            return [
                'total_amount'       => $total,
                'paid_amount'        => $paid,
                'outstanding_amount' => max(0, round($total - $paid, 2)),
                'due_date'           => $sale['due_date'] ?? null,
            ];
        }

        $config = [
            'supplier' => ['table' => 'supplier_payments', 'column' => 'purchase_order_id',    'header' => 'purchase_orders'],
            'customer' => ['table' => 'customer_payments', 'column' => 'distribution_order_id', 'header' => 'distribution_orders'],
        ];

        if (!isset($config[$type])) {
            throw new RuntimeException('Jenis tagihan tidak valid.');
        }

        $item   = $config[$type];
        $header = $this->db->table($item['header'])->where('id', $referenceId)->get()->getRowArray();

        if (!$header) {
            throw new RuntimeException('Dokumen tagihan tidak ditemukan.');
        }

        $paidRow = $this->db->table($item['table'])
            ->selectSum('amount', 'total_paid')
            ->where($item['column'], $referenceId)
            ->get()->getRowArray();

        $total = (float) $header['total_amount'];
        $paid  = (float) ($paidRow['total_paid'] ?? 0);

        return [
            'total_amount'       => $total,
            'paid_amount'        => $paid,
            'outstanding_amount' => max(0, round($total - $paid, 2)),
        ];
    }

    /**
     * Menambahkan transaksi kas/bank.
     * Method ini hanya dipanggil dari dalam transaksi service.
     */
    private function createCashTransaction(array $data): void
    {
        $this->db->table('cash_transactions')->insert([
            'transaction_number' => $data['transaction_number'],
            'transaction_date' => $data['transaction_date'],
            'transaction_type' => $data['transaction_type'],
            'reference_type' => $data['reference_type'],
            'reference_id' => $data['reference_id'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'created_by' => $data['created_by'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Validasi agar pembayaran tidak melebihi sisa tagihan.
     */
    private function validateOutstanding(
        string $paymentTable,
        string $referenceColumn,
        int $referenceId,
        float $invoiceAmount,
        float $paymentAmount
    ): void {
        if (!is_finite($paymentAmount) || $paymentAmount <= 0) {
            throw new RuntimeException('Nominal pembayaran tidak valid.');
        }

        $paidRow = $this->db->table($paymentTable)
            ->selectSum('amount', 'total_paid')
            ->where($referenceColumn, $referenceId)
            ->get()
            ->getRowArray();

        $alreadyPaid = (float) ($paidRow['total_paid'] ?? 0);
        $outstanding = $invoiceAmount - $alreadyPaid;

        if ($outstanding <= 0) {
            throw new RuntimeException('Tagihan sudah lunas.');
        }

        if ($paymentAmount > $outstanding + 0.0001) {
            throw new RuntimeException(
                'Pembayaran melebihi sisa tagihan. Sisa: ' . $outstanding
            );
        }
    }

    private function validatePaymentData(array $data): void
    {
        foreach (
            [
                'payment_number',
                'payment_date',
                'amount',
                'payment_method',
            ] as $field
        ) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new RuntimeException("Field {$field} wajib diisi.");
            }
        }

        if (!is_finite((float) $data['amount']) || (float) $data['amount'] <= 0) {
            throw new RuntimeException('Nominal pembayaran harus lebih besar dari nol.');
        }

        if (
            empty($data['supplier_id'])
            && empty($data['customer_id'])
        ) {
            throw new RuntimeException('Supplier atau pelanggan wajib ditentukan.');
        }
    }

    private function makeCashTransactionNumber(string $reference): string
    {
        return 'CASH-' . $reference;
    }

    private function completeTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Transaksi database gagal.');
        }

        $this->db->transCommit();
    }
}


/**
 * Pembayaran Supllier
 * $service = new FinanceService();

$paymentId = $service->createSupplierPayment([
    'payment_number' => 'SP-001',
    'supplier_id' => 1,
    'purchase_order_id' => 1,
    'payment_date' => '2026-11-05',
    'amount' => 500000,
    'payment_method' => 'transfer',
    'reference_number' => 'TRF-001',
], $userId);


Pembayaran Pelanggan
$paymentId = $service->createCustomerPayment([
    'payment_number' => 'CP-001',
    'customer_id' => 1,
    'distribution_order_id' => 1,
    'payment_date' => '2026-11-05',
    'amount' => 750000,
    'payment_method' => 'cash',
], $userId);


pengeluaran operasional
$expenseId = $service->createExpense([
    'expense_number' => 'EXP-001',
    'expense_date' => '2026-11-05',
    'category' => 'Operasional',
    'description' => 'Biaya transportasi',
    'amount' => 100000,
    'payment_method' => 'cash',
], $userId);
 */
