<?php

declare(strict_types=1);

namespace App\Services\Api;

class ApiPagination
{
    public function normalize(
        mixed $page = 1,
        mixed $perPage = 10
    ): array {
        $page = filter_var(
            $page,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => 1,
                    'min_range' => 1,
                ],
            ]
        );

        $perPage = filter_var(
            $perPage,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => 10,
                    'min_range' => 1,
                ],
            ]
        );

        // Batas maksimum untuk mencegah request terlalu besar.
        $perPage = min($perPage, 100);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    public function meta(
        int $page,
        int $perPage,
        int $total
    ): array {
        $totalPages = $total > 0
            ? (int) ceil($total / $perPage)
            : 0;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_previous' => $page > 1,
        ];
    }
}
