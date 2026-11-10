<?php

declare(strict_types=1);

namespace App\Traits;

trait ApiResponseTrait
{
    protected function apiSuccess(
        string $message,
        mixed $data = null,
        int $statusCode = 200
    ) {
        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON([
                'status'  => true,
                'message' => $message,
                'data'    => $data,
            ]);
    }

    protected function apiError(
        string $message,
        int $statusCode = 400,
        mixed $errors = null,
        mixed $data = null
    ) {
        $response = [
            'status'  => false,
            'message' => $message,
            'data'    => $data,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON($response);
    }

    protected function apiUnauthorized(
        string $message = 'Autentikasi diperlukan.'
    ) {
        return $this->apiError($message, 401);
    }

    protected function apiForbidden(
        string $message = 'Anda tidak memiliki izin untuk mengakses resource ini.'
    ) {
        return $this->apiError($message, 403);
    }

    protected function apiNotFound(
        string $message = 'Data tidak ditemukan.'
    ) {
        return $this->apiError($message, 404);
    }

    protected function apiValidationError(
        mixed $errors,
        string $message = 'Data tidak valid.'
    ) {
        return $this->apiError($message, 422, $errors);
    }

    protected function apiServerError(
        string $message = 'Terjadi kesalahan pada server.'
    ) {
        return $this->apiError($message, 500);
    }
}
