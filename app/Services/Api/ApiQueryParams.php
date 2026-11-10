<?php

declare(strict_types=1);

namespace App\Services\Api;

class ApiQueryParams
{
    public function __construct(
        protected ApiRequest $request
    ) {
    }

    public function search(
        string $default = ''
    ): string {
        return trim(
            (string) $this->request->query(
                'search',
                $default
            )
        );
    }

    public function sort(
        string $default = 'id'
    ): string {
        $sort = trim(
            (string) $this->request->query(
                'sort',
                $default
            )
        );

        return $sort !== '' ? $sort : $default;
    }

    public function order(
        string $default = 'asc'
    ): string {
        $order = strtolower(
            trim(
                (string) $this->request->query(
                    'order',
                    $default
                )
            )
        );

        return in_array(
            $order,
            ['asc', 'desc'],
            true
        )
            ? $order
            : $default;
    }

    public function all(): array
    {
        return [
            'search' => $this->search(),
            'sort'   => $this->sort(),
            'order'  => $this->order(),
        ];
    }
}
