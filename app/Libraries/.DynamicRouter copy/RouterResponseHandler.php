<?php

namespace App\Libraries\DynamicRouter;

use Closure;
use CodeIgniter\HTTP\ResponseInterface;

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
     * Helper privat untuk memetakan key error atau pesan kustom dengan parameter dinamis
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

        // Gunakan kode sebagai fallback jika terjemahan tidak tersedia.
        if ($message === $langKey) {
            $message = $codeOrMessage;
        }

        // Ganti placeholder seperti %s secara eksplisit.
        if ($replaceArgs !== [] && is_string($message)) {
            $message = sprintf($message, ...$replaceArgs);
        }

        return [$codeOrMessage, $message];
    }


    public function handleNotFound(string $codeOrMessage = 'NOT_FOUND', ?string $customMessage = null)
    {
        [$code, $message] = $this->resolveMessage($codeOrMessage, $customMessage);
        $isApi = ($this->isApiRequest)();

        $handler = $isApi
            ? $this->apiFallbackHandler
            : $this->uiFallbackHandler;

        if (is_callable($handler)) {
            return $handler($message, 404, $code);
        }

        if (is_callable($this->fallbackHandler)) {
            return ($this->fallbackHandler)($message, 404, $code);
        }

        if ($isApi) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'     => 404,
                    'error'      => true,
                    'code'       => $code,
                    'message'    => $message,
                    'csrf_token' => csrf_hash(),
                ]);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound($message);
    }

    public function handleAccessDenied(string $codeOrMessage = 'ACCESS_DENIED', ?string $customMessage = null)
    {
        [$code, $message] = $this->resolveMessage($codeOrMessage, $customMessage);

        if (($this->isApiRequest)()) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status'     => 403,
                    'error'      => true,
                    'code'       => $code,
                    'message'    => $message,
                    'csrf_token' => csrf_hash(),
                ]);
        }

        if (is_callable($this->uiFallbackHandler)) {
            return ($this->uiFallbackHandler)($message, 403, $code);
        }

        return $this->response
            ->setStatusCode(403)
            ->setBody($message);
    }

    public function handleMethodNotAllowed(
        string $codeOrMessage = 'HTTP_VERB_NOT_ALLOWED',
        ?string $customMessage = null,
        ?string $prefix = null,
    ) {
        $currentMethod = strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
        );

        [$code, $message] = $this->resolveMessage(
            $codeOrMessage,
            $customMessage,
            [$currentMethod],
        );

        if ($prefix !== null) {
            $message = $prefix . ' : ' . $message;
        }

        if (($this->isApiRequest)()) {
            return $this->response
                ->setStatusCode(405)
                ->setJSON([
                    'status'  => 405,
                    'error'   => true,
                    'code'    => $code,
                    'message' => $message,
                ]);
        }

        if (is_callable($this->uiFallbackHandler)) {
            return ($this->uiFallbackHandler)(
                $message,
                405,
                $code,
            );
        }

        return $this->response
            ->setStatusCode(405)
            ->setBody($message);
    }

    public function handleUnauthenticated(string $codeOrMessage = 'AUTHENTICATION_REQUIRED', ?string $customMessage = null)
    {
        [$code, $message] = $this->resolveMessage($codeOrMessage, $customMessage);

        if (($this->isApiRequest)()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'     => 401,
                    'error'      => true,
                    'code'       => $code,
                    'message'    => $message,
                    'csrf_token' => csrf_hash(),
                ]);
        }

        if (is_callable($this->uiFallbackHandler)) {
            return ($this->uiFallbackHandler)($message, 401, $code);
        }

        return redirect()->to(site_url('auth/login'));
    }
}
