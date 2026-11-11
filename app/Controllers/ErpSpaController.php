<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class ErpSpaController extends BaseController
{
    public function index()
    {
        $uri = trim(service('uri')->getPath(), '/');

        if (
            auth()->loggedIn()
            && in_array($uri, ['acn', 'acn/login'], true)
        ) {
            return redirect()->to(site_url('acn/dashboard'));
        }

        // Development: Vite Dev Server.
        if (ENVIRONMENT === 'development') {
            return view('acn_dev');
        }

        // Production: Vite Manifest.
        $manifestPath = FCPATH . 'acn/.vite/manifest.json';

        if (! is_file($manifestPath)) {
            throw PageNotFoundException::forPageNotFound(
                'ERP frontend belum dibuild.'
            );
        }

        $manifest = json_decode(
            file_get_contents($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $entry = $manifest['src/main.ts'] ?? null;

        if (! is_array($entry) || empty($entry['isEntry'])) {
            throw PageNotFoundException::forPageNotFound(
                'Entry point Vue tidak ditemukan.'
            );
        }

        return view('acn_prod', [
            'entry' => $entry,
            'manifest' => $manifest,
        ]);
    }
}
