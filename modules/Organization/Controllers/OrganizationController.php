<?php

declare(strict_types=1);

namespace Modules\Organization\Controllers;

use App\Core\BackendController;

class OrganizationController extends BackendController
{
    public function index()
    {

        $this->plugins();
        return $this->render('index');
    }
}
