<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */
if (!function_exists('normalize_url')) {
    function normalize_url(?string $url): array
    {

        $url = trim($url ?? '');

        // 1. Kosong atau fake link
        if (
            $url === '' || $url === '#' || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')
        ) {
            return ['', ''];
        }

        // 2. URL eksternal / protokol khusus
        if (
            filter_var($url, FILTER_VALIDATE_URL) !== false || preg_match('/^(mailto:|tel:|ftp:)/i', $url)
        ) {
            return [$url, ' target="_blank" rel="noopener noreferrer"'];
        }

        // 3. URL mengandung slash → biarkan (anggap sudah lengkap)
        // if (str_contains($url, '/')) {
        //     return [$url, ''];
        // }

        // 4. URL polos (tidak ada slash) → site_url()
        return [site_url($url), ''];
    }
}
if (!function_exists('pp')) {
    function pp(string|array $string)
    {
        echo '<pre>';
        print_r($string);
        echo '</pre>';
        // return [site_url($url), ''];
    }
}
