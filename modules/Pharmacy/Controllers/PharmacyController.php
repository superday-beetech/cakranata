<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers;

use App\Core\BackendController;
use Throwable;

class PharmacyController extends BackendController
{
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);
    }

    public function index()
    {
        return 'index';
    }
}
