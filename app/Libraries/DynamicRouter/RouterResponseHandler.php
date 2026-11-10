<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

use Closure;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class RouterResponseHandler
{
    public function __construct(
        private ResponseInterface $response,
        private Closure $isApiRequest,
        private $fallbackHandler = null,
        private $apiFallbackHandler = null,
        private $uiFallbackHandler = null,
    ) {}

    /**
     * Resolusi kode dan pesan error.
     */
    protected function resolveMessage(
        string $codeOrMessage,
        ?string $customMessage = null,
        array $replaceArgs = [],
    ): array {
        if ($customMessage !== null) {
            return [$codeOrMessage, $customMessage];
        }

        $langKey = 'Errors.' . $codeOrMessage;
        $message = lang($langKey);

        if (! is_string($message) || $message === $langKey) {
            $message = $codeOrMessage;
        }

        if ($replaceArgs !== [] && is_string($message)) {
            $message = sprintf($message, ...$replaceArgs);
        }

        return [$codeOrMessage, $message];
    }

    /**
     * Memastikan callback API/UI dipilih sesuai jenis request.
     * Fallback umum tetap dipertahankan untuk kompatibilitas.
     */
    private function respond(
        string $message,
        int $status,
        string $code,
        bool $includeCsrf = true,
    ) {
        try {
            $isApi = (bool) ($this->isApiRequest)();

            $handler = $isApi
                ? $this->apiFallbackHandler
                : $this->uiFallbackHandler;

            if (is_callable($handler)) {
                return $handler($message, $status, $code);
            }

            if (is_callable($this->fallbackHandler)) {
                return ($this->fallbackHandler)(
                    $message,
                    $status,
                    $code
                );
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Router response handler gagal: {error}',
                ['error' => $e->getMessage()]
            );
        }

        // Fallback aman jika callback tidak tersedia atau gagal.
        try {
            $isApi = (bool) ($this->isApiRequest)();
        } catch (Throwable) {
            $isApi = true;
        }

        if ($isApi) {
            $payload = [
                'status'  => $status,
                'error'   => true,
                'code'    => $code,
                'message' => $message,
            ];

            if ($includeCsrf) {
                $payload['csrf_token'] = csrf_hash();
            }

            return $this->response
                ->setStatusCode($status)
                ->setJSON($payload);
        }

        /*
         * Jangan menampilkan halaman putih berisi teks saja.
         * Jika UI callback belum terkonfigurasi, catat masalahnya
         * dan gunakan view error standar sebagai fallback.
         */
        log_message(
            'error',
            'UI error fallback tidak tersedia; status={status}, code={code}',
            [
                'status' => $status,
                'code'   => $code,
            ]
        );

        try {

            $html = view('errors/response', [
                'status'  => $status,
                'code'    => $code,
                'title'   => $this->getDefaultTitle($status),
                'message' => $message,
            ]);

            return $this->response
                ->setStatusCode($status)
                ->setContentType('text/html', 'UTF-8')
                ->setBody($html);
        } catch (Throwable $e) {
            log_message(
                'critical',
                'Theme error fallback gagal dirender: {error}',
                ['error' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode($status)
                ->setContentType('text/plain', 'UTF-8')
                ->setBody(esc($message));
        }
    }

    private function getDefaultTitle(int $status): string
    {
        return match ($status) {
            401 => 'Sesi Login Diperlukan',
            403 => 'Akses Tidak Diizinkan',
            404 => 'Halaman Tidak Ditemukan',
            405 => 'Metode Tidak Diizinkan',
            429 => 'Terlalu Banyak Permintaan',
            default => 'Terjadi Kesalahan',
        };
    }

    public function handleNotFound(
        string $codeOrMessage = 'NOT_FOUND',
        ?string $customMessage = null
    ) {
        [$code, $message] = $this->resolveMessage(
            $codeOrMessage,
            $customMessage
        );

        return $this->respond($message, 404, $code);
    }

    public function handleAccessDenied(
        string $codeOrMessage = 'ACCESS_DENIED',
        ?string $customMessage = null
    ) {
        [$code, $message] = $this->resolveMessage(
            $codeOrMessage,
            $customMessage
        );

        return $this->respond($message, 403, $code);
    }

    public function handleMethodNotAllowed(
        string $codeOrMessage = 'HTTP_VERB_NOT_ALLOWED',
        ?string $customMessage = null,
        ?string $prefix = null,
    ) {
        $currentMethod = strtoupper(
            service('request')->getMethod()
        );

        [$code, $message] = $this->resolveMessage(
            $codeOrMessage,
            $customMessage,
            [$currentMethod]
        );

        if ($prefix !== null) {
            $message = $prefix . ' : ' . $message;
        }

        return $this->respond(
            $message,
            405,
            $code,
            false
        );
    }


    public function handleUnauthenticated(
        string $codeOrMessage = 'AUTHENTICATION_REQUIRED',
        ?string $customMessage = null
    ) {
        [$code, $message] = $this->resolveMessage(
            $codeOrMessage,
            $customMessage
        );

        try {
            $isApi = (bool) ($this->isApiRequest)();
        } catch (Throwable) {
            $isApi = true;
        }

        // API tetap menggunakan mekanisme respons API yang tersedia.
        if ($isApi) {
            return $this->respond($message, 401, $code);
        }

        $request = service('request');

        // AJAX: kirim 401 JSON agar frontend dapat menampilkan modal.
        if (
            method_exists($request, 'isAJAX')
            && $request->isAJAX()
        ) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'    => 401,
                    'error'     => true,
                    'code'      => $code,
                    'message'   => $message,
                    'login_url' => site_url('auth/login'),
                ]);
        }

        // Request halaman biasa: arahkan ke halaman login.
        return redirect()->to(site_url('auth/login'));
    }

    /**
     * Menangani hasil diagnostik AccessChecker.
     */
    public function handleAccessDecision(array $decision)
    {
        $code = (string) ($decision['reason'] ?? 'ACCESS_DENIED');
        $message = (string) (
            $decision['message']
            ?? 'Permintaan tidak dapat diproses.'
        );
        $status = (int) ($decision['status'] ?? 403);

        if (! in_array($status, [401, 403, 404, 405, 429, 500], true)) {
            $status = 403;
        }

        // Production: jangan bocorkan alasan internal (endpoint terdaftar atau tidak,
        // status akun, dst.) ke klien. Alasan asli tetap tercatat di log.
        if (ENVIRONMENT === 'production') {
            log_message('info', 'DynamicRouter access decision: {reason} ({status})', ['reason' => $code, 'status' => $status]);

            [$code, $message] = match ($status) {
                401     => ['UNAUTHENTICATED', 'Autentikasi diperlukan.'],
                404     => ['NOT_FOUND', 'Sumber daya tidak ditemukan.'],
                405     => ['METHOD_NOT_ALLOWED', 'Metode tidak diizinkan.'],
                429     => ['TOO_MANY_REQUESTS', 'Terlalu banyak permintaan.'],
                500     => ['INTERNAL_ERROR', 'Terjadi kesalahan pada server.'],
                default => ['ACCESS_DENIED', 'Akses ditolak.'],
            };
        }

        return $this->respond($message, $status, $code);
    }
}
