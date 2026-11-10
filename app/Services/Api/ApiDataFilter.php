<?php

declare(strict_types=1);

namespace App\Services\Api;

class ApiDataFilter
{
    public function only(
        array $data,
        array $allowed
    ): array {
        return array_intersect_key(
            $data,
            array_flip($allowed)
        );
    }
}
