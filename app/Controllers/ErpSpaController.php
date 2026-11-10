<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class ErpSpaController extends BaseController
{
    /* public function index()
    {
        $file = FCPATH . 'acn/index.html';

        if (! is_file($file)) {
            throw PageNotFoundException::forPageNotFound(
                'ERP frontend belum dibuild.'
            );
        }

        return $this->response
            ->setContentType('text/html')
            ->setBody(file_get_contents($file));
    } */
    public function index()
    {
        /*  dd([
            'loggedIn' => auth()->loggedIn(),
            'userId'   => auth()->id(),
            'session'  => session()->get(),
        ]); */

        $uri = trim(service('uri')->getPath(), '/');

        // Hanya redirect jika membuka halaman utama ACN.
        if (
            auth()->loggedIn()
            && in_array($uri, [
                'acn',
                'acn/login',
            ], true)
        ) {
            return redirect()->to(site_url('acn/dashboard'));
        }

        // Development: gunakan Vite Dev Server.
        if (ENVIRONMENT === 'development') {
            return view('acn_dev');
        }

        // Production: gunakan hasil build Vue.
        $file = FCPATH . 'acn/index.html';

        if (! is_file($file)) {
            throw PageNotFoundException::forPageNotFound(
                'ERP frontend belum dibuild.'
            );
        }

        return $this->response
            ->setContentType('text/html')
            ->setBody(file_get_contents($file));
    }
}
