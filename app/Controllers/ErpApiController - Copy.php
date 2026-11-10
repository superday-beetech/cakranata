<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiController;

class ErpApiController extends ApiController
{


    public function index()
    {
        $menuModel = new \App\Models\AdminModel();

        $navigation = $menuModel->getAllNavigation('backend');

        return $this->respond([
            'status'  => 200,
            'error'   => false,
            'message' => 'Navigasi berhasil dimuat.',
            'data'    => [
                'menus' => $navigation,
            ],
        ]);
    }


    /* public function index()
    {
        helper('vite');
        $menuModel = new \App\Models\AdminModel();

        $data = ($menuModel->getNestedMenu('backend', 'sidebar'));
        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'HTTP verb berhasil dimuat.',
            'data'    => $data,
        ]);
    } */
}
