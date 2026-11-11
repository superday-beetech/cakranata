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

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->salesService = new SalesService();
    }

    /**
     * GET /api/v1/pharmacy/sales-api
     */
    public function index()
    {


        try {
            $result = $this->salesService->getSales([
                'page'     => $this->request->getGet('page'),
                'per_page' => $this->request->getGet('per_page'),
                'search'   => $this->request->getGet('search'),
                'status'   => $this->request->getGet('status'),
            ]);

            return $this->successResponse(
                $result,
                'Data penjualan berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message('error', '[SalesApiController::index] ' . $e->getMessage());

            return $this->errorResponse(
                'Gagal memuat data penjualan.',
                500
            );
        }
    }

    /**
     * GET /api/v1/pharmacy/sales-api/{id}
     */
    public function show($id = null)
    {
        try {
            $id = (int) $id;
            if ($id < 1) {
                return $this->errorResponse(
                    'ID penjualan tidak valid.',
                    400
                );
            }

            $sale = $this->salesService->getSaleDetail($id);

            if (!$sale) {
                return $this->errorResponse(
                    'Data penjualan tidak ditemukan.',
                    404
                );
            }

            return $this->successResponse(
                $sale,
                'Detail penjualan berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message('error', '[SalesApiController::show] ' . $e->getMessage());

            return $this->errorResponse(
                'Gagal memuat detail penjualan.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/sales-api
     */
    public function create()
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->errorResponse(
                'Format request tidak valid.',
                400
            );
        }

        try {
            $saleId = $this->salesService->createDraft(
                $data,
                $userId
            );

            $sale = $this->salesService->getSaleDetail($saleId);

            return $this->successResponse(
                $sale,
                'Draft penjualan berhasil dibuat.',
                201
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message('error', '[SalesApiController::create] ' . $e->getMessage());

            return $this->errorResponse(
                'Terjadi kesalahan saat membuat penjualan.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/sales-api/checkout
     * Body: field CreateSalePayload + objek "payment".
     */
    public function checkout()
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        try {
            $data = $this->request->getJSON(true);
        } catch (Throwable) {
            $data = null;
        }

        if (!is_array($data) || !is_array($data['payment'] ?? null)) {
            return $this->errorResponse('Format request tidak valid.', 400);
        }

        try {
            $saleId = $this->salesService->checkout(
                $data,
                $this->paymentFromRequest($data['payment']),
                $userId
            );

            return $this->successResponse(
                $this->salesService->getSaleDetail($saleId),
                'Penjualan berhasil diselesaikan.',
                201
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', '[SalesApiController::checkout] ' . $e->getMessage());

            return $this->errorResponse('Terjadi kesalahan saat menyelesaikan penjualan.', 500);
        }
    }

    /**
     * POST /api/v1/pharmacy/sales-api/{id}/post
     */
    public function post(int $id)
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        if ($id < 1) {
            return $this->errorResponse(
                'ID penjualan tidak valid.',
                400
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->errorResponse(
                'Format request tidak valid.',
                400
            );
        }

        try {
            $this->salesService->postSale(
                $id,
                $this->paymentFromRequest($data),
                $userId
            );

            return $this->successResponse(
                $this->salesService->getSaleDetail($id),
                'Penjualan berhasil diposting.'
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[SalesApiController::post] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Terjadi kesalahan saat memposting penjualan.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/sales-api/{id}/hold
     */
    public function hold(int $id)
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        if ($id < 1) {
            return $this->errorResponse(
                'ID penjualan tidak valid.',
                400
            );
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
            log_message(
                'error',
                '[SalesApiController::hold] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Terjadi kesalahan saat menahan transaksi.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/sales-api/resume/{id}
     */
    public function resume(int $id)
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        if ($id < 1) {
            return $this->errorResponse(
                'ID penjualan tidak valid!.',
                400
            );
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
            log_message(
                'error',
                '[SalesApiController::resume] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Terjadi kesalahan saat melanjutkan transaksi.',
                500
            );
        }
    }

    public function cancel(int $id)
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        if ($id < 1) {
            return $this->errorResponse(
                'ID penjualan tidak valid.',
                400
            );
        }

        try {
            $this->salesService->cancelSale($id, $userId);

            $sale = $this->salesService->getSaleDetail($id);

            return $this->successResponse(
                $sale,
                'Transaksi penjualan berhasil dibatalkan.'
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[SalesApiController::cancel] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Terjadi kesalahan saat membatalkan penjualan.',
                500
            );
        }
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

    private function paymentFromRequest(array $data): array
    {
        return [
            'settlement_mode'   => $data['settlement_mode'] ?? 'paid',
            'amount'            => $data['applied_amount'] ?? $data['amount'] ?? 0,
            'amount_received'   => $data['amount_received'] ?? 0,
            'change_amount'     => $data['change_amount'] ?? 0,
            'payment_method'    => $data['payment_method'] ?? null,
            'reference_number'  => $data['reference_number'] ?? null,
            'due_date'          => $data['due_date'] ?? null,
            'payment_confirmed' => $data['payment_confirmed'] ?? false,
            'buyer_name'        => $data['buyer_name'] ?? null,
            'buyer_phone'       => $data['buyer_phone'] ?? null,
            'collateral_note'   => $data['collateral_note'] ?? null,
        ];
    }
}
