<?php

declare(strict_types=1);

namespace App\Validation\Api;

class ValidationRules
{
    public static function requiredString(
        int $maxLength = 255
    ): array {
        return [
            'rules' => "required|string|max_length[{$maxLength}]",
        ];
    }

    public static function optionalString(
        int $maxLength = 255
    ): array {
        return [
            'rules' => "permit_empty|string|max_length[{$maxLength}]",
        ];
    }

    public static function requiredEmail(): array
    {
        return [
            'rules' => 'required|valid_email|max_length[254]',
        ];
    }

    public static function optionalEmail(): array
    {
        return [
            'rules' => 'permit_empty|valid_email|max_length[254]',
        ];
    }

    public static function requiredInteger(): array
    {
        return [
            'rules' => 'required|integer',
        ];
    }

    public static function optionalInteger(): array
    {
        return [
            'rules' => 'permit_empty|integer',
        ];
    }

    public static function requiredPositiveInteger(): array
    {
        return [
            'rules' => 'required|is_natural_no_zero',
        ];
    }

    public static function optionalPositiveInteger(): array
    {
        return [
            'rules' => 'permit_empty|is_natural_no_zero',
        ];
    }

    public static function requiredBoolean(): array
    {
        return [
            'rules' => 'required|in_list[0,1]',
        ];
    }
}
