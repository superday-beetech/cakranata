<?php

declare(strict_types=1);

namespace Modules\Backend\Controllers;

use App\Core\BackendController;
use App\Services\PermissionHealthCheckService;

class PermissionHealthCheckController extends BackendController
{
    public function index()
    {
        $healthCheck = new PermissionHealthCheckService();
        $result = $healthCheck->inspect();

        return $this->render('permissions/health', [
            'title'  => 'Permission Health Check',
            'result' => $result,
        ]);
    }
}
