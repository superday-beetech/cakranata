<?php

declare(strict_types=1);

namespace App\Services\Api;

use CodeIgniter\HTTP\IncomingRequest;

class ApiRequest
{
    public function __construct(
        protected IncomingRequest $request
    ) {
    }

    /**
     * Ambil payload JSON/body sebagai array.
     */
    public function data(): array
    {
        $data = $this->request->getJSON(true);

        if (is_array($data)) {
            return $data;
        }

        $data = $this->request->getPost();

        return is_array($data) ? $data : [];
    }

    /**
     * Ambil satu nilai dari payload.
     */
    public function input(
        string $key,
        mixed $default = null
    ): mixed {
        $data = $this->data();

        return $data[$key] ?? $default;
    }

    /**
     * Apakah request menggunakan JSON?
     */
    public function isJson(): bool
    {
        $contentType = strtolower(
            $this->request->getHeaderLine('Content-Type')
        );

        return str_contains(
            $contentType,
            'application/json'
        );
    }

    /**
     * Ambil query parameter.
     */
    public function query(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        if ($key === null) {
            return $this->request->getGet();
        }

        return $this->request->getGet($key)
            ?? $default;
    }

    /**
     * Ambil HTTP method.
     */
    public function method(): string
    {
        return strtoupper(
            $this->request->getMethod()
        );
    }
}
