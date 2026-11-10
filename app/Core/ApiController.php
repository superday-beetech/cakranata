<?php

namespace App\Core;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;


class ApiController extends ResourceController
{
    protected $format = 'json';

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);
    }

    private function getCsrfToken(): string
    {
        return csrf_hash();
    }

    /**
     * Standard Success Response Format
     */
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

    /**
     * Standard Error Response Format
     */
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
}
