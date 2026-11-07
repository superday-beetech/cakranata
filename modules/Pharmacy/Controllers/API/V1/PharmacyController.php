<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Pharmacy\Services\DashboardService;
use Throwable;

class PharmacyController extends ApiController
{
    private DashboardService $dashboardService;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);
        $this->dashboardService = new DashboardService();
    }

    /**
     * GET /api/v1/pharmacy/dashboard
     */
    public function index(): ResponseInterface
    {
        echo 's';
        try {
            [$from, $to] = $this->getDateRange();

            $data = $this->dashboardService->getOverview($from, $to);

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/stock
     */
    public function stock()
    {
        try {
            $data = $this->dashboardService->getStockSummary();

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/procurement
     */
    public function procurement(): ResponseInterface
    {
        try {
            [$from, $to] = $this->getDateRange();

            $data = $this->dashboardService->getProcurementSummary(
                $from,
                $to
            );

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/distribution
     */
    public function distribution(): ResponseInterface
    {
        try {
            [$from, $to] = $this->getDateRange();

            $data = $this->dashboardService->getDistributionSummary(
                $from,
                $to
            );

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/finance
     */
    public function finance(): ResponseInterface
    {
        try {
            [$from, $to] = $this->getDateRange();

            $data = $this->dashboardService->getFinanceSummary(
                $from,
                $to
            );

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/outstanding
     */
    public function outstanding(): ResponseInterface
    {
        try {
            $data = $this->dashboardService->getOutstandingSummary();

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/returns
     */
    public function returns(): ResponseInterface
    {
        try {
            [$from, $to] = $this->getDateRange();

            $data = $this->dashboardService->getReturnsSummary(
                $from,
                $to
            );

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/expiring-batches?days=30
     */
    public function expiringBatches(): ResponseInterface
    {
        try {
            $days = (int) ($this->request->getGet('days') ?? 30);
            $days = max(1, min($days, 365));

            $data = $this->dashboardService->getExpiringBatches($days);

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/cash-flow
     */
    public function cashFlow(): ResponseInterface
    {
        try {
            [$from, $to] = $this->getDateRange();

            $data = $this->dashboardService->getCashFlowChart(
                $from,
                $to
            );

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET /api/v1/pharmacy/dashboard/top-selling?limit=10
     */
    public function topSelling(): ResponseInterface
    {
        try {
            [$from, $to] = $this->getDateRange();

            $limit = (int) ($this->request->getGet('limit') ?? 10);
            $limit = max(1, min($limit, 100));

            $data = $this->dashboardService->getTopSellingProducts(
                $from,
                $to,
                $limit
            );

            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }
    /**
     * Resolve reporting period from query parameters.
     *
     * Defaults to current month until today.
     *
     * @return array{0: string, 1: string}
     */
    private function getDateRange(): array
    {
        $from = $this->request->getGet('from')
            ?? date('Y-m-01');

        $to = $this->request->getGet('to')
            ?? date('Y-m-d');

        $fromDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $toDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);

        if (
            !$fromDate ||
            !$toDate ||
            $fromDate->format('Y-m-d') !== $from ||
            $toDate->format('Y-m-d') !== $to ||
            $from > $to
        ) {
            throw new \InvalidArgumentException(
                'Format periode tidak valid. Gunakan from dan to dengan format YYYY-MM-DD.'
            );
        }

        return [$from, $to];
    }

    private function handleError(Throwable $e): ResponseInterface
    {
        log_message('error', '[Pharmacy Dashboard] {message}', [
            'message' => $e->getMessage(),
        ]);

        if ($e instanceof \InvalidArgumentException) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        }

        return $this->errorResponse(
            'Gagal mengambil data dashboard.',
            500
        );
    }
}

/*

STANDAR REPONSE DARI ControllerApi
 protected function successResponse(
        $data = null,
        string $message = 'Success',
        int $code = 200
    ) {
        return $this->respond([
            'status'     => $code,
            'error'      => false,
            'message'    => $message,
            'data'       => $data,
            'csrf_token' => $this->getCsrfToken(),
        ], $code);
    }

    protected function errorResponse(
        string|array $message = 'Error',
        int $code = 400,
        $errors = null
    ) {
        $response = [
            'status'     => $code,
            'error'      => true,
            'message'    => $message,
            'csrf_token' => $this->getCsrfToken(),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->respond($response, $code);
    }

    */
