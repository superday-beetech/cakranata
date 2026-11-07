<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Pharmacy\Services\ReturnsService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class ReturnsApiController extends ApiController
{
    private ReturnsService $returnsService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->returnsService = new ReturnsService();
    }

    /**
     * Daftar Purchase Return.
     */
    public function listPurchase(): ResponseInterface
    {
        try {
            $data = $this->returnsService->getPurchaseReturns();

            return $this->successResponse(
                $data,
                'Daftar Purchase Return berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::listPurchase] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil daftar Purchase Return.',
                500
            );
        }
    }

    /**
     * Detail Purchase Return.
     */
    public function showPurchase(int $id): ResponseInterface
    {
        try {
            $data = $this->returnsService->getPurchaseReturnDetail($id);

            return $this->successResponse(
                $data,
                'Detail Purchase Return berhasil diambil.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::showPurchase] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil detail Purchase Return.',
                500
            );
        }
    }


    /**
     * GET /pharmacy/returns-api/list-sales
     */
    public function listSales()
    {
        try {
            $result = $this->returnsService->getSalesReturns();

            return $this->successResponse(
                $result,
                'Daftar sales return berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::listSales] {message}',
                ['message' => $e->getMessage()]
            );

            return $this->errorResponse(
                'Gagal mengambil daftar sales return.',
                500
            );
        }
    }

    /**
     * GET /pharmacy/returns-api/show-sales/{id}
     */
    public function showSales(int $id)
    {
        try {
            $result = $this->returnsService->getSalesReturnDetail($id);

            return $this->successResponse(
                $result,
                'Detail sales return berhasil dimuat.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::showSales] {message}',
                ['message' => $e->getMessage()]
            );

            return $this->errorResponse(
                'Gagal mengambil detail sales return.',
                500
            );
        }
    }

    /**
     * Membuat draft purchase return.
     */
    public function createPurchase(): ResponseInterface
    {
        try {
            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->errorResponse(
                    'Request body harus berupa JSON object.',
                    400
                );
            }

            $userId = (int) auth()->id();

            if ($userId < 1) {
                return $this->errorResponse(
                    'User tidak terautentikasi.',
                    401
                );
            }

            $returnId = $this->returnsService->createPurchaseReturn(
                $data,
                $userId
            );

            return $this->successResponse(
                ['return_id' => $returnId],
                'Purchase Return berhasil dibuat.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::createPurchaseReturn] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal membuat Purchase Return.',
                500
            );
        }
    }

    /**
     * Posting purchase return.
     */
    public function postPurchase(int $id): ResponseInterface
    {
        try {
            if ($id < 1) {
                return $this->errorResponse(
                    'Purchase Return ID tidak valid.',
                    422
                );
            }

            $userId = (int) auth()->id();

            if ($userId < 1) {
                return $this->errorResponse(
                    'User tidak terautentikasi.',
                    401
                );
            }

            $result = $this->returnsService->postPurchaseReturn(
                $id,
                $userId
            );

            return $this->successResponse(
                $result,
                'Purchase Return berhasil diposting.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::postPurchaseReturn] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memposting Purchase Return.',
                500
            );
        }
    }

    /**
     * Membuat draft sales return.
     */
    public function createSales(): ResponseInterface
    {
        try {
            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->errorResponse(
                    'Request body harus berupa JSON object.',
                    400
                );
            }

            $userId = (int) auth()->id();

            if ($userId < 1) {
                return $this->errorResponse(
                    'User tidak terautentikasi.',
                    401
                );
            }

            $returnId = $this->returnsService->createSalesReturn(
                $data,
                $userId
            );

            return $this->successResponse(
                ['return_id' => $returnId],
                'Sales Return berhasil dibuat.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::createSalesReturn] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal membuat Sales Return.',
                500
            );
        }
    }

    /**
     * Posting sales return.
     */
    public function postSales(int $id): ResponseInterface
    {
        try {
            if ($id < 1) {
                return $this->errorResponse(
                    'Sales Return ID tidak valid.',
                    422
                );
            }

            $userId = (int) auth()->id();

            if ($userId < 1) {
                return $this->errorResponse(
                    'User tidak terautentikasi.',
                    401
                );
            }

            $result = $this->returnsService->postSalesReturn(
                $id,
                $userId
            );

            return $this->successResponse(
                $result,
                'Sales Return berhasil diposting.'
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ReturnsApiController::postSalesReturn] '
                    . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memposting Sales Return.',
                500
            );
        }
    }
}
