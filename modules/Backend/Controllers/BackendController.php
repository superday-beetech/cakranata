<?php

namespace Modules\Backend\Controllers;

use App\Core\BackendController as BaseController;

class BackendController extends BaseController
{
    public function index()
    {
        return $this->render('test');
    }

    public function dashboard()
    {
        return $this->render('test');
    }
}
