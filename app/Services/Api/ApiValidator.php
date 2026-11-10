<?php

declare(strict_types=1);

namespace App\Services\Api;

class ApiValidator
{
    public function validate(
        array $data,
        array $rules
    ): bool {
        $validation = service('validation');

        $validation->setRules($rules);

        return $validation->run($data);
    }

    public function errors(): array
    {
        return service('validation')->getErrors();
    }

    public function validated(): array
    {
        return service('validation')->getValidated();
    }
}
