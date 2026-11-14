<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Pharmacy\Services\SalesService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class SalesApiController extends ApiController
{
    protected SalesService $salesService;

    private const SETTLEMENT_MODES = ['paid', 'partial', 'receivable'];

    private const PAYMENT_METHODS = ['cash', 'transfer', 'qris', 'debit', 'credit', 'other'];

    private const IDEMPOTENCY_TTL = 3600; // 1 jam

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->salesService = new SalesService();
    }

    // =========================================================
    // GET /api/v1/pharmacy/sales-api
    // =========================================================

    public function index()
    {
        echo 'asasad';
        try {
            $result = $this->salesService->getSales([
                'page'     => $this->request->getGet('page'),
                'per_page' => $this->request->getGet('per_page'),
                'search'   => $this->request->getGet('search'),
                'status'   => $this->request->getGet('status'),
            ]);

            return $this->successResponse($result, 'Data penjualan berhasil dimuat.');
        } catch (Throwable $e) {
            $this->logError('index', $e);

            return $this->errorResponse('Gagal memuat data penjualan.', 500);
        }
    }

    // =========================================================
    // GET /api/v1/pharmacy/sales-api/{id}
    // =========================================================

    public function show($id = null)
    {
        $saleId = $this->normalizeId($id);

        if ($saleId < 1) {
            return $this->errorResponse('ID penjualan tidak valid.', 400);
        }

        try {
            $sale = $this->salesService->getSaleDetail($saleId);

            if (!$sale) {
                return $this->errorResponse('Data penjualan tidak ditemukan.', 404);
            }

            return $this->successResponse($sale, 'Detail penjualan berhasil dimuat.');
        } catch (Throwable $e) {
            $this->logError('show', $e, ['sale_id' => $saleId]);

            return $this->errorResponse('Gagal memuat detail penjualan.', 500);
        }
    }

    // =========================================================
    // POST /api/v1/pharmacy/sales-api
    // =========================================================

    public function create()
    {
        $userId = $this->requireAuth();
        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $data = $this->parseJsonBody();
        if ($data === null) {
            return $this->errorResponse('Format request tidak valid.', 400);
        }

        // Validasi awal items
        if (!is_array($data['items'] ?? null) || count($data['items']) === 0) {
            return $this->errorResponse('Minimal satu item diperlukan.', 422);
        }

        try {
            $saleId = $this->salesService->createDraft($data, $userId);
            $sale = $this->salesService->getSaleDetail($saleId);

            return $this->successResponse($sale, 'Draft penjualan berhasil dibuat.', 201);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logError('create', $e);

            return $this->errorResponse('Terjadi kesalahan saat membuat penjualan.', 500);
        }
    }

    // =========================================================
    // POST /api/v1/pharmacy/sales-api/checkout
    // =========================================================

    public function checkout()
    {
        $userId = $this->requireAuth();

        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $data = $this->parseJsonBody();

        if ($data === null) {
            return $this->errorResponse('Format request tidak valid.', 400);
        }

        if (!is_array($data['payment'] ?? null)) {
            return $this->errorResponse('Data pembayaran tidak valid.', 400);
        }

        $idempotencyKey = $this->getIdempotencyKey();

        if ($idempotencyKey === null) {
            return $this->errorResponse('Idempotency-Key wajib dikirim.', 400);
        }

        if (strlen($idempotencyKey) > 128) {
            return $this->errorResponse('Idempotency-Key maksimal 128 karakter.', 400);
        }

        try {
            $payment = $this->paymentFromRequest($data['payment']);

            $saleId = $this->salesService->checkout(
                $data,
                $payment,
                $userId,
                $idempotencyKey
            );

            $sale = $this->salesService->getSaleDetail($saleId);

            if (!$sale) {
                return $this->errorResponse(
                    'Transaksi berhasil diproses tetapi detail tidak ditemukan.',
                    500
                );
            }

            return $this->successResponse(
                $sale,
                'Penjualan berhasil diselesaikan.',
                201
            );
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logError('checkout', $e);

            return $this->errorResponse(
                'Terjadi kesalahan saat menyelesaikan penjualan.',
                500
            );
        }
    }

    public function checkoutOld()
    {
        $userId = $this->requireAuth();
        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $data = $this->parseJsonBody();
        if ($data === null) {
            return $this->errorResponse('Format request tidak valid.', 400);
        }

        if (!is_array($data['payment'] ?? null)) {
            return $this->errorResponse('Data pembayaran tidak valid.', 400);
        }

        $idempotencyKey = $this->getIdempotencyKey();
        if ($idempotencyKey !== null) {
            $cached = cache("checkout:{$userId}:{$idempotencyKey}");
            if ($cached !== null) {
                return $this->successResponse($cached, 'Penjualan berhasil diselesaikan.', 201);
            }
        }

        try {
            $payment = $this->paymentFromRequest($data['payment']);
            if ($idempotencyKey === null) {
                return $this->errorResponse(
                    'Idempotency-Key wajib diisi.',
                    400
                );
            }
            $saleId = $this->salesService->checkout(
                $data,
                $payment,
                $userId,
                $idempotencyKey
            );
            $sale = $this->salesService->getSaleDetail($saleId);

            if ($idempotencyKey !== null) {
                cache()->save("checkout:{$userId}:{$idempotencyKey}", $sale, self::IDEMPOTENCY_TTL);
            }

            return $this->successResponse($sale, 'Penjualan berhasil diselesaikan.', 201);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logError('checkout', $e);

            return $this->errorResponse('Terjadi kesalahan saat menyelesaikan penjualan.', 500);
        }
    }

    // =========================================================
    // POST /api/v1/pharmacy/sales-api/{id}/post
    // =========================================================

    public function post(int $id)
    {
        $userId = $this->requireAuth();
        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        if ($id < 1) {
            return $this->errorResponse('ID penjualan tidak valid.', 400);
        }

        $data = $this->parseJsonBody();
        if ($data === null) {
            return $this->errorResponse('Format request tidak valid.', 400);
        }

        $idempotencyKey = $this->getIdempotencyKey();
        if ($idempotencyKey !== null) {
            $cached = cache("post:{$userId}:{$id}:{$idempotencyKey}");
            if ($cached !== null) {
                return $this->successResponse($cached, 'Penjualan berhasil diposting.');
            }
        }

        try {
            // Dukung dua format: flat (dari FE saat ini) atau nested `payment`.
            $paymentData = is_array($data['payment'] ?? null) ? $data['payment'] : $data;
            $payment = $this->paymentFromRequest($paymentData);

            $this->salesService->postSale($id, $payment, $userId);
            $sale = $this->salesService->getSaleDetail($id);

            if ($idempotencyKey !== null) {
                cache()->save("post:{$userId}:{$id}:{$idempotencyKey}", $sale, self::IDEMPOTENCY_TTL);
            }

            return $this->successResponse($sale, 'Penjualan berhasil diposting.');
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logError('post', $e, ['sale_id' => $id]);

            return $this->errorResponse('Terjadi kesalahan saat memposting penjualan.', 500);
        }
    }

    // =========================================================
    // POST /api/v1/pharmacy/sales-api/{id}/hold
    // =========================================================

    public function hold(int $id)
    {
        $userId = $this->requireAuth();
        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        if ($id < 1) {
            return $this->errorResponse('ID penjualan tidak valid.', 400);
        }

        try {
            $this->salesService->holdSale($id, $userId);

            return $this->successResponse(
                $this->salesService->getSaleDetail($id),
                'Transaksi berhasil ditahan.'
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logError('hold', $e, ['sale_id' => $id]);

            return $this->errorResponse('Terjadi kesalahan saat menahan transaksi.', 500);
        }
    }

    // =========================================================
    // POST /api/v1/pharmacy/sales-api/resume/{id}
    // =========================================================

    public function resume(int $id)
    {
        $userId = $this->requireAuth();
        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        if ($id < 1) {
            return $this->errorResponse('ID penjualan tidak valid.', 400);
        }

        try {
            $this->salesService->resumeSale($id, $userId);

            return $this->successResponse(
                $this->salesService->getSaleDetail($id),
                'Transaksi berhasil dilanjutkan.'
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logError('resume', $e, ['sale_id' => $id]);

            return $this->errorResponse('Terjadi kesalahan saat melanjutkan transaksi.', 500);
        }
    }

    // =========================================================
    // POST /api/v1/pharmacy/sales-api/{id}/cancel
    // =========================================================

    public function cancel(int $id)
    {
        $userId = $this->requireAuth();
        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        if ($id < 1) {
            return $this->errorResponse('ID penjualan tidak valid.', 400);
        }

        $restoreHeld = filter_var($this->request->getGet('restore_held') ?? '0', FILTER_VALIDATE_BOOLEAN);

        try {
            $this->salesService->cancelSale($id, $userId, $restoreHeld);

            return $this->successResponse(
                $this->salesService->getSaleDetail($id),
                $restoreHeld ? 'Transaksi dikembalikan ke daftar tertahan.' : 'Transaksi berhasil dibatalkan.'
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logError('cancel', $e, ['sale_id' => $id, 'restore_held' => $restoreHeld]);

            return $this->errorResponse('Terjadi kesalahan saat membatalkan penjualan.', 500);
        }
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private function requireAuth(): int
    {
        return (int) (auth()->id() ?? 0);
    }

    private function normalizeId(mixed $id): int
    {
        if (!is_numeric($id)) {
            return 0;
        }

        return max(0, (int) $id);
    }

    private function parseJsonBody(): ?array
    {
        try {
            $data = $this->request->getJSON(true);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    /* private function getIdempotencyKey(): ?string
    {
        $key = trim($this->request->getHeaderLine('Idempotency-Key'));

        return $key === '' ? null : substr($key, 0, 128);
    } */

    private function getIdempotencyKey(): ?string
    {
        $key = trim($this->request->getHeaderLine('Idempotency-Key'));

        return $key === '' ? null : $key;
    }
    /**
     * Normalisasi payment payload.
     *
     * FE mengirim `applied_amount` dan `amount` — keduanya dipertahankan
     * agar konsisten dengan SalesService. `settlement_mode` wajib valid.
     */
    private function paymentFromRequest(array $data): array
    {
        $settlementMode = trim((string) ($data['settlement_mode'] ?? ''));

        if (!in_array($settlementMode, self::SETTLEMENT_MODES, true)) {
            throw new InvalidArgumentException('Mode pembayaran tidak valid.');
        }

        $appliedAmount = $data['applied_amount'] ?? $data['amount'] ?? 0;
        $amountReceived = $data['amount_received'] ?? $appliedAmount;
        $changeAmount = $data['change_amount'] ?? 0;

        $paymentMethod = $data['payment_method'] ?? null;

        if ($paymentMethod !== null) {
            $paymentMethod = trim((string) $paymentMethod);

            if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
                throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            }
        }

        return [
            'settlement_mode'   => $settlementMode,
            'amount'            => $appliedAmount,
            'applied_amount'    => $appliedAmount,
            'amount_received'   => $amountReceived,
            'change_amount'     => $changeAmount,
            'payment_method'    => $paymentMethod,
            'reference_number'  => $this->nullableString($data['reference_number'] ?? null),
            'due_date'          => $this->nullableString($data['due_date'] ?? null),
            'payment_confirmed' => (bool) ($data['payment_confirmed'] ?? false),
            'buyer_name'        => $this->nullableString($data['buyer_name'] ?? null),
            'buyer_phone'       => $this->nullableString($data['buyer_phone'] ?? null),
            'collateral_note'   => $this->nullableString($data['collateral_note'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function logError(string $action, Throwable $e, array $context = []): void
    {
        $requestId = $this->request->getHeaderLine('X-Request-Id') ?: uniqid('req_', true);

        $payload = json_encode([
            'controller' => 'SalesApiController',
            'action'     => $action,
            'request_id' => $requestId,
            'context'    => $context,
            'message'    => $e->getMessage(),
            'class'      => get_class($e),
        ]);

        log_message('error', '[SalesApiController] ' . $payload);
    }
}
