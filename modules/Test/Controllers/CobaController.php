<?php

namespace Modules\Test\Controllers;

use App\Core\BackendController;

class CobaController extends BackendController
{
    public function index(): string
    {

        $result = 'HARUSNYA TAMPIL COBA';
        print_r($result);
        exit;
        /* echo json_encode(
            \App\Libraries\DynamicRouterBase::scanAllModules(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
        exit; */
        return view('Modules\Test\Views\test');
    }
}
