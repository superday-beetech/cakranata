<?php

declare(strict_types=1);

if (! function_exists('vite_asset')) {
    function vite_asset(string $entry = 'resources/js/app.js'): string
    {
        $hotFile = FCPATH . 'hot';

        if (is_file($hotFile)) {
            $hotUrl = rtrim(
                trim((string) file_get_contents($hotFile)),
                '/'
            );

            return $hotUrl . '/' . ltrim($entry, '/');
        }

        $manifestPath = FCPATH . 'assets/build/manifest.json';

        if (! is_file($manifestPath)) {
            throw new RuntimeException(
                'Manifest Vite tidak ditemukan. Jalankan npm run build.'
            );
        }

        $manifest = json_decode(
            (string) file_get_contents($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! isset($manifest[$entry]['file'])) {
            throw new RuntimeException(
                "Entry Vite '{$entry}' tidak ditemukan di manifest."
            );
        }

        return base_url(
            'assets/build/' . $manifest[$entry]['file']
        );
    }
}

if (! function_exists('vite_scripts')) {
    function vite_scripts(string $entry = 'resources/js/app.js'): string
    {
        $hotFile = FCPATH . 'hot';

        if (is_file($hotFile)) {
            $hotUrl = rtrim(
                trim((string) file_get_contents($hotFile)),
                '/'
            );

            return '<script type="module" src="'
                . esc($hotUrl . '/@vite/client', 'attr')
                . '"></script>'
                . "\n"
                . '<script type="module" src="'
                . esc($hotUrl . '/' . ltrim($entry, '/'), 'attr')
                . '"></script>';
        }

        return '<script type="module" src="'
            . esc(vite_asset($entry), 'attr')
            . '"></script>';
    }
}
