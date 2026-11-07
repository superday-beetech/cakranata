<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;

class PermissionsController extends BackendController
{
    /**
     * Halaman Permission Management.
     */
    public function index()
    {
        $this->theme->setTitle('Permission Management');

        $this->data = [
            'title' => 'Permission Management',
            'apiBase' => base_url('api/v1/rbac/permissions/')
        ];

        return $this->render(
            'index',
            $this->data
        );
    }
}
