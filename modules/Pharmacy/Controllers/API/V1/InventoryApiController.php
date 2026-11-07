<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Modules\Pharmacy\Services\InventoryService;
use RuntimeException;
use Throwable;

class InventoryApiController extends ApiController
{
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->inventoryService = new InventoryService();
    }

    /**
     * Stock Overview
     */
    public function stockOverview(): ResponseInterface
    {
        return $this->respondWithService(
            fn() => $this->inventoryService->getStockOverview(
                $this->request->getGet()
            ),
            'Stock overview berhasil dimuat.'
        );
    }

    /**
     * Product Batches
     */
    public function batches(): ResponseInterface
    {
        return $this->respondWithService(
            fn() => $this->inventoryService->getBatches(
                $this->request->getGet()
            ),
            'Daftar batch berhasil dimuat.'
        );
    }

    public function batch(int $id): ResponseInterface
    {
        if ($id < 1) {
            return $this->errorResponse('Batch ID tidak valid.', 422);
        }

        return $this->respondWithService(
            fn() => $this->inventoryService->getBatchDetail($id),
            'Detail batch berhasil dimuat.'
        );
    }

    /**
     * Stock Movements
     */
    public function movements(): ResponseInterface
    {
        return $this->respondWithService(
            fn() => $this->inventoryService->getMovements(
                $this->request->getGet()
            ),
            'Riwayat pergerakan stok berhasil dimuat.'
        );
    }

    /**
     * FEFO Allocation
     */
    public function fefo(int $productId): ResponseInterface
    {
        $quantity = $this->request->getGet('quantity');

        if (
            $productId < 1 ||
            $quantity === null ||
            !is_numeric($quantity) ||
            !is_finite((float) $quantity) ||
            (float) $quantity <= 0
        ) {
            return $this->errorResponse(
                'Product ID atau quantity tidak valid.',
                422
            );
        }

        return $this->respondWithService(
            fn() => $this->inventoryService->getFefoAllocation(
                $productId,
                (float) $quantity
            ),
            'Alokasi stok FEFO berhasil diperoleh.'
        );
    }

    /**
     * Goods Receipt Posting
     */
    public function postGoodsReceipt(int $receiptId): ResponseInterface
    {
        if ($receiptId < 1) {
            return $this->errorResponse(
                'Goods Receipt ID tidak valid.',
                422
            );
        }

        $data = $this->getJsonBody();

        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $batchBreakdown = $data['batch_breakdown'] ?? null;

        if (!is_array($batchBreakdown) || $batchBreakdown === []) {
            return $this->errorResponse(
                'Batch breakdown wajib diisi.',
                422
            );
        }

        $userId = $this->getAuthenticatedUserId();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        return $this->respondWithService(
            fn() => $this->inventoryService->postGoodsReceipt(
                $receiptId,
                $batchBreakdown,
                $userId
            ),
            'Goods Receipt berhasil diposting.'
        );
    }

    /**
     * Stock Opname List
     */
    public function stockOpnames(): ResponseInterface
    {
        return $this->respondWithService(
            fn() => $this->inventoryService->getOpnames(
                $this->request->getGet()
            ),
            'Daftar stock opname berhasil dimuat.'
        );
    }

    /**
     * Stock Opname Detail
     */
    public function opname(int $id): ResponseInterface
    {
        if ($id < 1) {
            return $this->errorResponse(
                'Stock Opname ID tidak valid.',
                422
            );
        }

        return $this->respondWithService(
            fn() => $this->inventoryService->getOpnameDetail($id),
            'Detail stock opname berhasil dimuat.'
        );
    }

    /**
     * Create Stock Opname
     */
    public function createStockOpname(): ResponseInterface
    {
        $data = $this->getJsonBody();

        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $userId = $this->getAuthenticatedUserId();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        return $this->respondWithService(
            fn() => [
                'id' => $this->inventoryService->createStockOpname(
                    $data,
                    $userId
                ),
            ],
            'Stock opname berhasil dibuat.'
        );
    }

    /**
     * Save Stock Opname Counts
     */
    public function saveStockOpname(int $id): ResponseInterface
    {
        if ($id < 1) {
            return $this->errorResponse(
                'Stock Opname ID tidak valid.',
                422
            );
        }

        $data = $this->getJsonBody();

        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $userId = $this->getAuthenticatedUserId();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        return $this->respondWithService(
            fn() => $this->inventoryService->saveStockOpnameCounts(
                $id,
                $data,
                $userId
            ),
            'Hasil penghitungan stock opname berhasil disimpan.'
        );
    }

    /**
     * Post Stock Opname
     */
    public function postStockOpname(int $id): ResponseInterface
    {
        if ($id < 1) {
            return $this->errorResponse(
                'Stock Opname ID tidak valid.',
                422
            );
        }

        $userId = $this->getAuthenticatedUserId();

        if ($userId < 1) {
            return $this->errorResponse(
                'User tidak terautentikasi.',
                401
            );
        }

        return $this->respondWithService(
            fn() => $this->inventoryService->postStockOpname(
                $id,
                $userId
            ),
            'Stock opname berhasil diposting.'
        );
    }

    /**
     * Read JSON request body.
     */
    private function getJsonBody(): array|ResponseInterface
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
     * Get authenticated user ID.
     */
    private function getAuthenticatedUserId(): int
    {
        return (int) auth()->id();
    }

    /**
     * Standard service response handler.
     */
    private function respondWithService(
        callable $callback,
        string $message
    ): ResponseInterface {
        try {
            return $this->successResponse(
                $callback(),
                $message
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[InventoryApiController] {message}',
                ['message' => $e->getMessage()]
            );

            return $this->errorResponse(
                'Terjadi kesalahan saat memproses inventory.',
                500
            );
        }
    }
}
