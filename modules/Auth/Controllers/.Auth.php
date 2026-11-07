<?php

namespace Modules\Auth\Controllers;

use App\Core\BackendController;

class Auth extends BackendController
{
    public function index(): string
    {
        return 'Hello';
        // return view('Modules\Auth\Views\auth');
    }
}
