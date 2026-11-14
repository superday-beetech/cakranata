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

        $manifestPath = FCPATH . 'acn/.vite/manifest.json';
        $hasManifest  = is_file($manifestPath);

        // Auto-deteksi: dev server hidup? (hanya dicek di non-production)
        $isDev = ENVIRONMENT !== 'production' && $this->isViteDevServerRunning();
        log_message('debug', 'VITE: env=' . ENVIRONMENT . ' dev=' . var_export($isDev, true) . ' manifest=' . var_export($hasManifest, true));
        // ==== KEPUTUSAN OTOMATIS ====
        // 1. Dev server hidup → pakai view dev (load dari :5173)
        if ($isDev) {
            return view('acn_dev');
        }
        if (! $hasManifest) {
            throw PageNotFoundException::forPageNotFound(
                'ERP frontend belum dibuild. Jalankan npm run build terlebih dahulu.'
            );
        }
        $manifestContent = file_get_contents($manifestPath);

        if ($manifestContent === false) {
            throw PageNotFoundException::forPageNotFound(
                'Manifest Vite tidak dapat dibaca.'
            );
        }

        $manifest = json_decode(
            $manifestContent,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($manifest)) {
            throw PageNotFoundException::forPageNotFound(
                'Format manifest Vite tidak valid.'
            );
        }

        $entry = $manifest['src/main.ts'] ?? null;

        if (
            ! is_array($entry)
            || empty($entry['isEntry'])
            || empty($entry['file'])
        ) {
            throw PageNotFoundException::forPageNotFound(
                'Entry point Vue tidak ditemukan dalam manifest Vite.'
            );
        }

        return view('acn_app', [
            'entry'    => $entry,
            'manifest' => $manifest,
        ]);

        // 3. Dua-duanya tidak ada → error jelas
        /* throw PageNotFoundException::forPageNotFound(
            'ERP frontend belum dibuild. Jalankan `npm run dev` atau `npm run build` dulu.'
        ); */
    }

    private function isViteDevServerRunning(): bool
    {
        $port = 5173;

        foreach (['127.0.0.1', 'localhost', '::1'] as $host) {
            $conn = @fsockopen($host, $port, $errno, $errstr, 0.2);
            if (is_resource($conn)) {
                fclose($conn);
                return true;
            }
        }
        return false;
    }


    /* public function index()
    {
        $mode = $this->request->getGet('mode');
        $uri = trim(service('uri')->getPath(), '/');

        if (
            auth()->loggedIn()
            && in_array($uri, ['acn', 'acn/login'], true)
        ) {
            return redirect()->to(site_url('acn/dashboard'));
        }

        $manifestPath = FCPATH . 'acn/.vite/manifest.json';
        $hasManifest = is_file($manifestPath);

        // Jika mode=d dipilih DAN manifest belum dibuild (murni development harian)
        if (is_null($mode) && ! $hasManifest) {
            return view('acn_dev');
        }
   if (! $hasManifest) {
            throw PageNotFoundException::forPageNotFound(
                'ERP frontend belum dibuild. Jalankan npm run build terlebih dahulu.'
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
    } */

    /* public function index()
    {
        $mode = $this->request->getGet('mode');
        $uri = trim(service('uri')->getPath(), '/');

        if (
            auth()->loggedIn()
            && in_array($uri, ['acn', 'acn/login'], true)
        ) {
            return redirect()->to(site_url('acn/dashboard'));
        }

        // Development: Vite Dev Server.
        // if (ENVIRONMENT === 'development') {
        if (is_null($mode)) {
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
    }*/
}
