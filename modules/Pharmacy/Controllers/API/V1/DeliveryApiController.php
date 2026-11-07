<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Modules\Pharmacy\Services\DeliveryService;
use RuntimeException;
use Throwable;

class DeliveryApiController extends ApiController
{
    protected DeliveryService $deliveryService;

    public function __construct()
    {
        $this->deliveryService = new DeliveryService();
    }

    /**
     * GET /pharmacy/delivery-api
     *
     * Daftar dokumen pengiriman.
     */
    public function index(): ResponseInterface
    {
        try {
            $result = $this->deliveryService->getDeliveries([
                'status' => $this->request->getGet('status'),
                'distribution_order_id' => $this->request->getGet(
                    'distribution_order_id'
                ),
                'search' => $this->request->getGet('search'),
                'page' => $this->request->getGet('page'),
                'per_page' => $this->request->getGet('per_page'),
            ]);

            return $this->successResponse(
                $result,
                'Daftar pengiriman berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[DeliveryApiController::index] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil daftar pengiriman.',
                500
            );
        }
    }

    /**
     * GET /pharmacy/delivery-api/show/{id}
     *
     * Detail dokumen pengiriman.
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $deliveryId = filter_var(
                $id,
                FILTER_VALIDATE_INT
            );

            if ($deliveryId === false || $deliveryId < 1) {
                return $this->errorResponse(
                    'ID pengiriman tidak valid.',
                    400
                );
            }

            $result = $this->deliveryService->getDeliveryDetail(
                (int) $deliveryId
            );

            return $this->successResponse(
                $result,
                'Detail pengiriman berhasil diambil.'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                404
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[DeliveryApiController::show] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil detail pengiriman.',
                500
            );
        }
    }

    /**
     * POST /pharmacy/delivery-api/create
     *
     * Membuat dokumen pengiriman berstatus draft.
     */
    public function create(): ResponseInterface
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
                'Request body harus berupa JSON object.',
                400
            );
        }

        try {
            $deliveryId = $this->deliveryService->createDelivery(
                $data,
                $userId
            );

            return $this->successResponse(
                [
                    'id' => $deliveryId,
                ],
                'Dokumen pengiriman berhasil dibuat.',
                201
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[DeliveryApiController::create] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal membuat dokumen pengiriman.',
                500
            );
        }
    }

    /**
     * POST /pharmacy/delivery-api/post/{id}
     *
     * Memposting pengiriman dan mengurangi stok batch.
     */
    public function post($id = null): ResponseInterface
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        $deliveryId = filter_var(
            $id,
            FILTER_VALIDATE_INT
        );

        if ($deliveryId === false || $deliveryId < 1) {
            return $this->errorResponse(
                'ID pengiriman tidak valid.',
                400
            );
        }

        $data = $this->request->getJSON(true);

        if ($data === null) {
            $data = [];
        }

        if (!is_array($data)) {
            return $this->errorResponse(
                'Request body harus berupa JSON object.',
                400
            );
        }

        $allocations = $data['allocations'] ?? [];

        if (!is_array($allocations)) {
            return $this->errorResponse(
                'Format alokasi batch tidak valid.',
                422
            );
        }

        try {
            $result = $this->deliveryService->postDelivery(
                (int) $deliveryId,
                $userId,
                $allocations
            );

            return $this->successResponse(
                $result,
                'Pengiriman berhasil diposting.'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[DeliveryApiController::post] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memposting pengiriman.',
                500
            );
        }
    }
}
