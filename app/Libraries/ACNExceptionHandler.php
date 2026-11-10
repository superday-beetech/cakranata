<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class ACNExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        // Untuk request browser HTML, tampilkan halaman debug ACN.
        if (
            $request instanceof IncomingRequest
            && str_contains($request->getHeaderLine('Accept'), 'text/html')
            && ENVIRONMENT === 'development'
        ) {
            $this->render(
                $exception,
                $statusCode,
                APPPATH . 'Views/errors/html/error_exception.php',
            );

            exit($exitCode);
        }

        // Request API/JSON dan production tidak menampilkan detail debug.
        (new \CodeIgniter\Debug\ExceptionHandler(config('Exceptions')))
            ->handle($exception, $request, $response, $statusCode, $exitCode);
    }
}
