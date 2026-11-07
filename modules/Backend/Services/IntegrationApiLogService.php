<?php

declare(strict_types=1);

namespace Modules\Backend\Services;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Throwable;

class IntegrationApiLogService
{
    private const TABLE = 'integration_api_logs';

    /**
     * Catat satu request API eksternal.
     *
     * $apiContext diisi dari hasil validasi API key yang tepercaya.
     * Jangan mengisinya langsung dari input pengguna.
     */
    public function record(
        RequestInterface $request,
        ResponseInterface $response,
        float $startedAt,
        ?array $apiContext = null,
        ?string $errorCode = null
    ): void {
        try {
            $uri = $request->getUri();

            // Simpan path saja; jangan simpan query string.
            $endpoint = '/' . ltrim($uri->getPath(), '/');
            $endpoint = substr($endpoint, 0, 500);

            $requestId = trim($request->getHeaderLine('X-Request-ID'));

            // Terima request ID eksternal hanya jika formatnya aman.
            if (
                $requestId === ''
                || ! preg_match('/^[a-zA-Z0-9._-]{1,64}$/D', $requestId)
            ) {
                $requestId = bin2hex(random_bytes(16));
            }

            $context = is_array($apiContext) ? $apiContext : [];

            $clientId = isset($context['client_id'])
                ? (int) $context['client_id']
                : null;

            $keyId = isset($context['key_id'])
                ? (int) $context['key_id']
                : null;
            $userAgent = substr(
                $request->getHeaderLine('User-Agent'),
                0,
                500
            );

            $durationMs = (int) round(
                max(0, microtime(true) - $startedAt) * 1000
            );

            $db = Database::connect();

            $db->table(self::TABLE)->insert([
                'client_id'   => $clientId,
                'api_key_id'  => $keyId,
                'request_id'  => $requestId,
                'http_method' => strtoupper($request->getMethod()),
                'endpoint'    => $endpoint,
                'http_status' => $response->getStatusCode(),
                'ip_address'  => $request->getIPAddress(),
                'user_agent'  => $userAgent,
                'duration_ms' => $durationMs,
                'error_code'  => $this->sanitizeErrorCode($errorCode),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Kegagalan logging tidak boleh membocorkan detail internal
            // atau mengubah respons API yang sedang diproses.
            log_message(
                'error',
                'Integration API audit log could not be written.'
            );
        }
    }

    private function sanitizeErrorCode(?string $errorCode): ?string
    {
        if ($errorCode === null || $errorCode === '') {
            return null;
        }

        // Hanya kode yang terstruktur; bukan pesan exception.
        if (! preg_match('/^[A-Z0-9_.-]{1,100}$/D', $errorCode)) {
            return 'API_ERROR';
        }

        return $errorCode;
    }
}
