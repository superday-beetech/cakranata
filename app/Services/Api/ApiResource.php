<?php

declare(strict_types=1);

namespace App\Services\Api;

class ApiResource
{
    public function id(mixed $id): ?int
    {
        if (
            $id === null
            || $id === ''
            || ! ctype_digit((string) $id)
        ) {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }
}
