<?php

use Config\Services;
use Modules\Rbac\Services\AccessControlService;

if (! function_exists('access')) {
    function access(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return service('accessControl')->hasPermission(
            (int) $user->id,
            $permission
        );
    }
}

if (! function_exists('user_access')) {
    function user_access(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return service('accessControl')->hasDirectPermission(
            (int) $user->id,
            $permission
        );
    }
}

if (!function_exists('vite_tag')) {
    function vite_tag(string $entry): string
    {
        $server = 'http://localhost:5173';
        $isDev = false;

        // Cek apakah Vite Dev Server sedang aktif di localhost
        $handle = @fsockopen('localhost', 5173, $errno, $errstr, 0.1);
        if ($handle) {
            is_resource($handle) && fclose($handle);
            $isDev = true;
        }

        if ($isDev) {
            // MODE DEVELOPMENT (Localhost dengan Hot-Reload aktif)
            return '<script type="module" src="' . $server . '/@vite/client"></script>' .
                '<script type="module" src="' . $server . '/' . $entry . '"></script>';
        } else {
            // MODE PRODUCTION (Hosting / Live Server membaca file manifest)
            $manifestPath = FCPATH . 'assets/dist/.vite/manifest.json';
            if (!file_exists($manifestPath)) {
                return '<!-- Vite Manifest not found. Jalankan "npm run build" terlebih dahulu. -->';
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            $asset = $manifest[$entry] ?? null;

            if (!$asset) {
                return '<!-- Vite Entry not found dalam manifest. -->';
            }

            $html = '<script type="module" src="' . base_url('assets/dist/' . $asset['file']) . '"></script>';

            if (!empty($asset['css'])) {
                foreach ($asset['css'] as $cssFile) {
                    $html .= '<link rel="stylesheet" href="' . base_url('assets/dist/' . $cssFile) . '">';
                }
            }
            return $html;
        }
    }
}
if (!function_exists('app_setting')) {
    /**
     * Ambil setting: auto convert true/false, on/off, active/inactive, 1/0 menjadi boolean.
     * Value lain tetap dikembalikan apa adanya (string/int).
     */
    function app_setting($name, $default = null)
    {
        $value = service('app_settings')->get($name, $default);
        // Handle social media dengan base URL
        $socialBaseUrls = [
            'facebook'  => 'https://facebook.com/',
            'instagram' => 'https://instagram.com/',
            'twitter'   => 'https://twitter.com/',
            'linkedin'  => 'https://linkedin.com/in/',
            'youtube'   => 'https://youtube.com/',
            'tiktok'    => 'https://tiktok.com/@',
            'github'    => 'https://github.com/',
        ];

        if (array_key_exists($name, $socialBaseUrls)) {
            // Jika user isi full URL, jangan ubah
            if (!empty($value) && !str_starts_with($value, 'http')) {
                return $socialBaseUrls[$name] . ltrim($value, '/');
            }

            return $value;
        }

        if (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':
                case 'on':
                case 'active':
                case '1':
                    return true;
                case 'false':
                case 'off':
                case 'inactive':
                case '0':
                    return false;
            }
        }

        return $value;
    }
}


if (!function_exists('view_exists')) {
    /**
     * Memeriksa apakah file view ada di dalam sistem CI4 (termasuk modul & tema)
     */
    function view_exists(string $viewName): bool
    {
        // 1. Cek menggunakan File Locator bawaan CI4
        $locator = Services::locator();

        // Normalisasi format backslash (\) ke slash (/) atau sebaliknya jika perlu
        $normalizedPath = str_replace(['\\', '.'], '/', $viewName);

        // Cek apakah file view ditemukan di dalam namespace views (termasuk modules)
        $files = $locator->locateFile($normalizedPath, 'Views', 'php');

        if (!empty($files)) {
            return true;
        }

        // 2. Fallback cek manual ke folder APPPATH/Views
        $fullPath = APPPATH . 'Views/' . ltrim($normalizedPath, '/') . '.php';
        if (is_file($fullPath)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('segment')) {
    /**
     * Get URI segment by number.
     *
     * @param  int         $number  Segment number (1-based)
     * @param  mixed       $default Default value if not exist
     * @return string|null
     */
    function segment(int $number, $default = null)
    {
        $uri = Services::request()->getUri();
        //  $uri = \Config\Services::request()->getUri();
        $segments = $uri->getSegments(); // ambil semua segmen

        // return $uri->getSegment($number) ?? $default;
        // Cek apakah segmen tersebut ada
        return isset($segments[$number - 1]) ? $segments[$number - 1] : $default;
    }
}


if (! function_exists('get_upload_url')) {
    /**
     * Get base URL ke folder upload.
     * @param  string $subPath contoh: 'halaman' atau 'uploads/halaman'
     * @return string
     */
    function get_upload_url(string $subPath = ''): string
    {
        $subPath = preg_replace('#^uploads/#', '', trim($subPath, '/'));

        return base_url('uploads/' . ($subPath ? $subPath . '' : ''));
    }
}

if (! function_exists('mod_lang')) {
    /**
     * Helper singkat untuk memanggil translation dari Modules CI4
     *
     * @param string $key Format: 'NamaModul.NamaFile.Key' (Cth: 'Auth.Users.title')
     * @param array $params Parameter opsional untuk placeholder
     * @param string|null $locale Kode bahasa opsional (misal: 'id')
     * @return string
     */
    function mod_lang(string $key, array $params = [], ?string $locale = null): string
    {
        // Otomatis menambahkan prefix 'Modules.' jika belum ada
        if (strpos($key, 'Modules.') !== 0) {
            $key = 'Modules.' . $key;
        }

        return lang($key, $params, $locale);
    }
}


if (! function_exists('module_lang')) {
    /**
     * Memuat terjemahan dari module HMVC.
     *
     * Contoh:
     * module_lang('Auth', 'Users', 'title');
     */
    function module_lang(
        string $module,
        string $file,
        string $key
    ): string {
        static $cache = [];

        // Validasi nama module dan file untuk mencegah path traversal.
        foreach ([$module, $file] as $name) {
            if (! preg_match('/\A[A-Za-z][A-Za-z0-9_-]*\z/', $name)) {
                return $key;
            }
        }

        $locale = service('language')->getLocale();

        $cacheKey = $module . '|' . $file . '|' . $locale;

        if (! array_key_exists($cacheKey, $cache)) {
            $basePath = ROOTPATH
                . 'Modules' . DIRECTORY_SEPARATOR
                . $module . DIRECTORY_SEPARATOR
                . 'Language' . DIRECTORY_SEPARATOR;

            $path = $basePath
                . $locale . DIRECTORY_SEPARATOR
                . $file . '.php';

            // Fallback ke bahasa Inggris.
            if (! is_file($path)) {
                $path = $basePath
                    . 'en' . DIRECTORY_SEPARATOR
                    . $file . '.php';
            }

            $translations = [];

            if (is_file($path)) {
                $loaded = require $path;

                if (is_array($loaded)) {
                    $translations = $loaded;
                }
            }

            $cache[$cacheKey] = $translations;
        }

        return $cache[$cacheKey][$key] ?? $key;
    }
}
