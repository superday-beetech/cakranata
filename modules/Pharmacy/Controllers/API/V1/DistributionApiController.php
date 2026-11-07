<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Modules\Pharmacy\Models\Distribution\DistributionOrderModel;
use Modules\Pharmacy\Services\DistributionService;
use Throwable;

class DistributionApiController extends ApiController
{
    protected DistributionService $distributionService;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->distributionService = new DistributionService();
    }

    /**
     * GET /api/v1/pharmacy/distribution-api
     */
    public function index(): ResponseInterface
    {
        try {
            $model = new DistributionOrderModel();

            $customerId = $this->request->getGet('customer_id');
            $status = $this->request->getGet('status');
            $search = trim((string) $this->request->getGet('search'));

            if ($customerId !== null && $customerId !== '') {
                if (
                    !ctype_digit((string) $customerId)
                    || (int) $customerId < 1
                ) {
                    return $this->errorResponse(
                        'Customer ID tidak valid.',
                        422
                    );
                }

                $model->where('customer_id', (int) $customerId);
            }

            if ($status !== null && $status !== '') {
                $model->where('status', $status);
            }

            if ($search !== '') {
                $model->groupStart()
                    ->like('order_number', $search)
                    ->groupEnd();
            }

            $data = $model
                ->orderBy('order_date', 'DESC')
                ->findAll(100);

            return $this->successResponse(
                $data,
                'Daftar Distribution Order berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[DistributionApiController::index] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil daftar Distribution Order.',
                500
            );
        }
    }

    /**
     * GET /api/v1/pharmacy/distribution-api/show/{id}
     */
    public function show($id = null): ResponseInterface
    {
        try {
            if (
                $id === null
                || !ctype_digit((string) $id)
                || (int) $id < 1
            ) {
                return $this->errorResponse(
                    'Distribution Order ID tidak valid.',
                    422
                );
            }

            $data = $this->distributionService->getOrderDetail(
                (int) $id
            );

            return $this->successResponse(
                $data,
                'Detail Distribution Order berhasil diambil.'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            if (
                str_contains(
                    $e->getMessage(),
                    'Pesanan distribusi tidak ditemukan.'
                )
            ) {
                return $this->errorResponse(
                    'Distribution Order tidak ditemukan.',
                    404
                );
            }

            log_message(
                'error',
                '[DistributionApiController::show] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil detail Distribution Order.',
                500
            );
        }
    }

    /**
     * POST /api/v1/pharmacy/distribution-api/create
     */
    public function create(): ResponseInterface
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

            $orderId = $this->distributionService
                ->createDistributionOrder(
                    $data,
                    $userId
                );

            return $this->successResponse(
                [
                    'id' => $orderId,
                ],
                'Distribution Order berhasil dibuat.'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[DistributionApiController::create] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal membuat Distribution Order.',
                500
            );
        }
    }
}
