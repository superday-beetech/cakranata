<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Modules\Pharmacy\Services\FinanceService;
use RuntimeException;
use Throwable;

class FinanceApiController extends ApiController
{

    /**
     * GET /api/v1/pharmacy/finance/supplier-payments
     */
    public function supplierPayments(): ResponseInterface
    {
        return $this->getTransactions(
            'supplier_payments',
            'payment_date',
            'Daftar pembayaran supplier berhasil diambil.',
            'Gagal mengambil pembayaran supplier.'
        );
    }

    /**
     * GET /api/v1/pharmacy/finance/customer-payments
     */
    public function customerPayments(): ResponseInterface
    {
        return $this->getTransactions(
            'customer_payments',
            'payment_date',
            'Daftar pembayaran customer berhasil diambil.',
            'Gagal mengambil pembayaran customer.'
        );
    }

    /**
     * GET /api/v1/pharmacy/finance/expenses
     */
    public function expenses(): ResponseInterface
    {
        return $this->getTransactions(
            'expenses',
            'expense_date',
            'Daftar pengeluaran berhasil diambil.',
            'Gagal mengambil pengeluaran.'
        );
    }

    /**
     * GET /api/v1/pharmacy/finance/cash-transactions
     */
    public function cashTransactions(): ResponseInterface
    {
        return $this->getTransactions(
            'cash_transactions',
            'transaction_date',
            'Daftar transaksi kas berhasil diambil.',
            'Gagal mengambil transaksi kas.'
        );
    }

    /**
     * POST /api/v1/pharmacy/finance/supplier-payments
     */
    public function createSupplierPayments(): ResponseInterface
    {
        try {
            $data = $this->getRequestData();

            if ($data instanceof ResponseInterface) {
                return $data;
            }

            $userId = $this->getAuthenticatedUserId();

            if ($userId instanceof ResponseInterface) {
                return $userId;
            }

            $paymentId = (new FinanceService())
                ->createSupplierPayment($data, $userId);

            return $this->successResponse(
                ['id' => $paymentId],
                'Pembayaran supplier berhasil dicatat.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[FinanceApiController::createSupplierPayment] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mencatat pembayaran supplier.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/finance/customer-payments
     */
    public function createCustomerPayments(): ResponseInterface
    {
        try {
            $data = $this->getRequestData();

            if ($data instanceof ResponseInterface) {
                return $data;
            }

            $userId = $this->getAuthenticatedUserId();

            if ($userId instanceof ResponseInterface) {
                return $userId;
            }

            $paymentId = (new FinanceService())
                ->createCustomerPayment($data, $userId);

            return $this->successResponse(
                ['id' => $paymentId],
                'Pembayaran customer berhasil dicatat.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[FinanceApiController::createCustomerPayment] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mencatat pembayaran customer.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/finance/expenses
     */
    public function createExpenses(): ResponseInterface
    {
        try {
            $data = $this->getRequestData();

            if ($data instanceof ResponseInterface) {
                return $data;
            }

            $userId = $this->getAuthenticatedUserId();

            if ($userId instanceof ResponseInterface) {
                return $userId;
            }

            $expenseId = (new FinanceService())
                ->createExpense($data, $userId);

            return $this->successResponse(
                ['id' => $expenseId],
                'Pengeluaran berhasil dicatat.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[FinanceApiController::createExpense] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mencatat pengeluaran.',
                500
            );
        }
    }

    /**
     * GET /api/v1/pharmacy/finance/outstanding/{type}/{referenceId}
     *
     * type: supplier | customer
     */
    public function outstandings(
        string $type,
        int $referenceId
    ): ResponseInterface {
        try {
            if (!in_array($type, ['supplier', 'customer', 'sale'], true)) {
                return $this->errorResponse('Jenis tagihan tidak valid.', 422);
            }
            if ($referenceId < 1) {
                return $this->errorResponse(
                    'Reference ID tidak valid.',
                    422
                );
            }

            $result = (new FinanceService())->getOutstanding(
                $type,
                $referenceId
            );

            return $this->successResponse(
                $result,
                'Informasi outstanding berhasil diambil.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[FinanceApiController::outstanding] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil informasi outstanding.',
                500
            );
        }
    }

    /**
     * Helper untuk mengambil daftar transaksi.
     *
     * Nama tabel dan kolom tanggal berasal dari FinanceService.
     */
    private function getTransactions(
        string $table,
        string $dateColumn,
        string $successMessage,
        string $errorMessage
    ): ResponseInterface {
        try {
            $db      = Database::connect('pharmacy');
            $builder = $db->table($table);

            $dateFrom = trim((string) $this->request->getGet('date_from'));
            $dateTo   = trim((string) $this->request->getGet('date_to'));

            foreach (['date_from' => $dateFrom, 'date_to' => $dateTo] as $field => $value) {
                if ($value === '') {
                    continue;
                }
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
                    return $this->errorResponse("Format {$field} harus YYYY-MM-DD.", 422);
                }
            }

            if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
                return $this->errorResponse('date_from tidak boleh melebihi date_to.', 422);
            }

            // Batas hari penuh: aman untuk kolom DATE maupun DATETIME.
            if ($dateFrom !== '') {
                $builder->where($dateColumn . ' >=', $dateFrom . ' 00:00:00');
            }
            if ($dateTo !== '') {
                $builder->where($dateColumn . ' <=', $dateTo . ' 23:59:59');
            }

            // Filter opsional untuk pelunasan piutang POS.
            $saleId = (int) $this->request->getGet('sale_id');
            if ($table === 'customer_payments' && $saleId > 0) {
                $builder->where('sale_id', $saleId);
            }

            $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = min(200, max(1, (int) ($this->request->getGet('per_page') ?? 100)));

            $total = (clone $builder)->countAllResults();

            $data = $builder
                ->orderBy($dateColumn, 'DESC')
                ->orderBy('id', 'DESC')
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();

            return $this->successResponse([
                'items'      => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => (int) ceil($total / $perPage),
                ],
            ], $successMessage);
        } catch (Throwable $e) {
            log_message('error', '[FinanceApiController::getTransactions] ' . $e->getMessage());

            return $this->errorResponse($errorMessage, 500);
        }
    }

    /**
     * Mengambil JSON request.
     */
    private function getRequestData(): array|ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->errorResponse(
                'Request body harus berupa JSON object.',
                400
            );
        }

        return $data;
    }

    /**
     * Mengambil user ID dari Shield.
     */
    private function getAuthenticatedUserId(): int|ResponseInterface
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        return $userId;
    }
}
