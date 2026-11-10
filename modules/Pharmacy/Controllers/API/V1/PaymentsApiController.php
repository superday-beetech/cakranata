<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Pharmacy\Services\PaymentService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PaymentsApiController extends ApiController
{
    private PaymentService $paymentService;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);
        $this->paymentService = new PaymentService();
    }

    public function methods(): ResponseInterface
    {
        try {
            return $this->successResponse(
                $this->paymentService->getMethods(),
                'Metode pembayaran berhasil dimuat.'
            );
        } catch (Throwable $e) {
            return $this->handlePaymentError($e, 'Gagal memuat metode pembayaran.');
        }
    }

    public function show(): ResponseInterface
    {
        $id = (int) $this->request->getGet('id');

        try {
            $payment = $this->paymentService->getPayment($id);

            if (!$payment) {
                return $this->errorResponse('Pembayaran tidak ditemukan.', 404);
            }

            return $this->successResponse($payment, 'Detail pembayaran berhasil dimuat.');
        } catch (Throwable $e) {
            return $this->handlePaymentError($e, 'Gagal memuat pembayaran.');
        }
    }

    public function manual(): ResponseInterface
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->errorResponse('Format request tidak valid.', 400);
        }

        try {
            $id = $this->paymentService->createManualPayment($data, $userId);
            return $this->successResponse(
                ['id' => $id, 'payment' => $this->paymentService->getPayment($id)],
                'Pembayaran manual berhasil dicatat.',
                201
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', '[PaymentsApiController::manual] ' . $e->getMessage());
            return $this->errorResponse('Gagal mencatat pembayaran manual.', 500);
        }
    }

    public function verify(): ResponseInterface
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->errorResponse('Format request tidak valid.', 400);
        }

        $decision = (string) ($data['decision'] ?? '');

        if (!in_array($decision, ['approve', 'reject'], true)) {
            return $this->errorResponse('Keputusan verifikasi harus approve atau reject.', 422);
        }

        try {
            $this->paymentService->verifyManualPayment(
                (int) ($data['payment_id'] ?? 0),
                $decision === 'approve',
                $userId,
                isset($data['notes']) ? (string) $data['notes'] : null
            );

            $id = (int) $data['payment_id'];

            return $this->successResponse(
                $this->paymentService->getPayment($id),
                'Verifikasi pembayaran berhasil diproses.'
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', '[PaymentsApiController::verify] ' . $e->getMessage());
            return $this->errorResponse('Gagal memverifikasi pembayaran.', 500);
        }
    }

    private function handlePaymentError(Throwable $e, string $message): ResponseInterface
    {
        if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        log_message('error', '[PaymentsApiController] ' . $e->getMessage());
        return $this->errorResponse($message, 500);
    }
}
