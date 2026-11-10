<?php

use App\Libraries\MenuBuilder;
use App\Libraries\Menus\MenuRenderer;

if (!function_exists('render_menu')) {
    function render_menu(?string $preset = null, ?string $where = null): string
    {
        global $lastMenuRenderTime;
        $start = microtime(true);

        $builder = new MenuBuilder();

        // Konfigurasi preset
        $configs = [
            'backend'          => ['preset' => 'adminlte',       'table' => 'tbl_menus',      'group' => 1],
            'admin'            => ['preset' => 'eduport_admin',  'table' => 'tbl_menu_front', 'group' => 3],
            'eduport_footer'   => ['preset' => 'eduport_footer', 'table' => 'tbl_menu_front', 'group' => 1],
            'eduport_sitelink' => ['preset' => 'eduport_sitelink', 'table' => 'tbl_menu_front', 'group' => 4],
        ];

        if (!isset($configs[$preset])) {
            return '<!-- Invalid preset -->';
        }

        $conf = $configs[$preset];
        $builder->setPreset($conf['preset']);

        $menuTree = $builder->buildFront([
            'table'    => $conf['table'],
            'group'    => $conf['group'],
            'position' => $where,
        ]);

        $rendererClass = '\\App\\Libraries\\Menus\\MenuRenderer';
        if (!class_exists($rendererClass)) {
            return '<!-- Menu renderer not found -->';
        }

        $renderer = new $rendererClass($builder->getPreset(), $builder->getFieldMap());
        $menuHtml = $renderer->buildMenu($menuTree);

        $end                = microtime(true);
        $lastMenuRenderTime = number_format(($end - $start) * 1000, 2);

        return $menuHtml;
    }
}

if (!function_exists('render_menu_cached')) {
    /**
     * Render menu dengan HTML dari cache (atau build ulang jika belum ada).
     *
     * @param string|null $preset  Preset menu yang ingin digunakan (wajib ada di config)
     * @param bool        $useRole Aktifkan suffix role untuk cache key
     *
     * @return string Rendered HTML
     */
    function render_menu_cached(?string $preset = 'eduport', bool $useRole = false): string
    {
        helper('auth');

        $role     = $useRole ? (auth()->user()->role ?? 'guest') : 'all';
        $cacheKey = "menu_html_{$preset}_{$role}";

        if ($html = cache($cacheKey)) {
            return $html;
        }

        $builder = new MenuBuilder();

        // Load config preset dari Config\Menu
        $configs = config('Menu')->presets ?? [];

        if (!isset($configs[$preset])) {
            return "<!-- Invalid preset: {$preset} -->";
        }

        $conf = $configs[$preset];

        $builder->setPreset($conf['preset']);
        $tree = $builder->buildFront([
            'table'    => $conf['table']    ?? 'tbl_menu_front',
            'group'    => $conf['group']    ?? 1,
            'position' => $conf['position'] ?? 'menu_atas',
        ]);
        if (!is_array($tree)) {
            return "<!-- Invalid menu tree for preset: {$preset} -->";
        }
        $renderer = new MenuRenderer($builder->getPreset(), $builder->getFieldMap());
        $html     = $renderer->buildMenu($tree);

        cache()->save($cacheKey, $html, 3600); // 1 jam

        return $html;
    }
}

if (!function_exists('clear_menu_cache')) {
    function clear_menu_cache()
    {
        $roles  = ['admin', 'editor', 'user', 'guest', 'all'];
        $themes = ['eduport', 'admin', 'backend', 'eduport_footer'];

        foreach ($themes as $theme) {
            foreach ($roles as $role) {
                cache()->delete("menu_html_{$theme}_{$role}");
            }
        }
    }
}

if (!function_exists('view_exists')) {
    function view_exists(string $name): bool
    {
        $paths = config('View')->paths ?? [APPPATH . 'Views/'];
        foreach ($paths as $path) {
            if (is_file($path . $name . '.php')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('safe_view')) {
    function safe_view($view, $data = [])
    {
        return view_exists($view) ? view($view, $data) : "<!-- view $view not found -->";
    }
}

if (!function_exists('normalize_menu_data')) {
    function normalize_menu_data(array $rows, array $preset): array
    {
        return array_map(function ($row) use ($preset) {
            return [
                'id'      => $row[$preset['menu_id']]      ?? null,
                'parent'  => $row[$preset['menu_parent']]  ?? null,
                'label'   => $row[$preset['menu_label']]   ?? '',
                'url'     => $row[$preset['menu_url']]     ?? '#',
                'icon'    => $row[$preset['menu_icon']]    ?? '',
                'badge'   => $row[$preset['menu_badge']]   ?? '',
                'order'   => $row[$preset['menu_order']]   ?? 0,
                'content' => $row[$preset['menu_content']] ?? null,
            ];
        }, $rows);
    }
}
// cache()->delete('footer_menu_eduport_footer_footer');

if (!function_exists('menu_render_time')) {
    function menu_render_time(): string
    {
        global $lastMenuRenderTime;

        return $lastMenuRenderTime !== null ? "{$lastMenuRenderTime} ms" : '';
    }
}

if (!function_exists('normalize_urlss')) {
    /**
     * Normalisasi URL untuk menu:
     * - Tetap jika `#`, `javascript:`, atau `mailto:` dll.
     * - Full URL (http/https/ftp) → target _blank
     * - Internal → site_url()
     *
     * @param  string|null $url
     * @return array       [url, target_attr]
     */
    function normalize_urlss(?string $url): array
    {
        $url = trim($url ?? '');

        // Fake links or JS
        if (
            $url === '#' || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')
        ) {
            return [$url, ''];
        }

        // External URLs or special protocols
        if (
            filter_var($url, FILTER_VALIDATE_URL) !== false || preg_match('/^(mailto:|tel:|ftp:)/i', $url)
        ) {
            return [$url, ' target="_blank" rel="noopener noreferrer"'];
        }

        // Internal URL
        return [site_url($url), ''];
    }
}

if (!function_exists('normalize_urls')) {
    function normalize_urls(string $url): array
    {
        $isExternal   = filter_var($url, FILTER_VALIDATE_URL) !== false;
        $isJavascript = str_starts_with($url, 'javascript:');
        $isHash       = str_starts_with($url, '#');

        if ($isExternal || $isJavascript || $isHash) {
            return [$url, $isExternal ? ' target="_blank" rel="noopener noreferrer"' : ''];
        }

        return [site_url($url), ''];
    }
}
if (! function_exists('slug')) {
    // sumber https://gist.github.com/sgmurphy/3098978
    /**
     * Create a web friendly URL slug from a string.
     *
     * Although supported, transliteration is discouraged because
     *     1) most web browsers support UTF-8 characters in URLs
     *     2) transliteration causes a loss of information
     *
     * @author Sean Murphy <sean@iamseanmurphy.com>
     * @copyright Copyright 2012 Sean Murphy. All rights reserved.
     * @license http://creativecommons.org/publicdomain/zero/1.0/
     *
     * @param  string $str
     * @param  array  $options
     * @return string
     */
    function slug($str, $options = [])
    {
        // Make sure string is in UTF-8 and strip invalid UTF-8 characters
        $str = mb_convert_encoding((string) $str, 'UTF-8', mb_list_encodings());

        $defaults = [
            'delimiter'     => '-',
            'limit'         => null,
            'lowercase'     => true,
            'replacements'  => ['/[%\'"``]/' => ''],
            'transliterate' => false,
        ];

        // Merge options
        $options = array_merge($defaults, $options);

        $char_map = [
            // Latin
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ő' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ű' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ő' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ű' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',

            // Latin symbols
            '©' => '(c)',

            // Greek
            'Α' => 'A',
            'Β' => 'B',
            'Γ' => 'G',
            'Δ' => 'D',
            'Ε' => 'E',
            'Ζ' => 'Z',
            'Η' => 'H',
            'Θ' => '8',
            'Ι' => 'I',
            'Κ' => 'K',
            'Λ' => 'L',
            'Μ' => 'M',
            'Ν' => 'N',
            'Ξ' => '3',
            'Ο' => 'O',
            'Π' => 'P',
            'Ρ' => 'R',
            'Σ' => 'S',
            'Τ' => 'T',
            'Υ' => 'Y',
            'Φ' => 'F',
            'Χ' => 'X',
            'Ψ' => 'PS',
            'Ω' => 'W',
            'Ά' => 'A',
            'Έ' => 'E',
            'Ί' => 'I',
            'Ό' => 'O',
            'Ύ' => 'Y',
            'Ή' => 'H',
            'Ώ' => 'W',
            'Ϊ' => 'I',
            'Ϋ' => 'Y',
            'α' => 'a',
            'β' => 'b',
            'γ' => 'g',
            'δ' => 'd',
            'ε' => 'e',
            'ζ' => 'z',
            'η' => 'h',
            'θ' => '8',
            'ι' => 'i',
            'κ' => 'k',
            'λ' => 'l',
            'μ' => 'm',
            'ν' => 'n',
            'ξ' => '3',
            'ο' => 'o',
            'π' => 'p',
            'ρ' => 'r',
            'σ' => 's',
            'τ' => 't',
            'υ' => 'y',
            'φ' => 'f',
            'χ' => 'x',
            'ψ' => 'ps',
            'ω' => 'w',
            'ά' => 'a',
            'έ' => 'e',
            'ί' => 'i',
            'ό' => 'o',
            'ύ' => 'y',
            'ή' => 'h',
            'ώ' => 'w',
            'ς' => 's',
            'ϊ' => 'i',
            'ΰ' => 'y',
            'ϋ' => 'y',
            'ΐ' => 'i',

            // Turkish
            'Ş' => 'S',
            'İ' => 'I',
            'Ç' => 'C',
            'Ü' => 'U',
            'Ö' => 'O',
            'Ğ' => 'G',
            'ş' => 's',
            'ı' => 'i',
            'ç' => 'c',
            'ü' => 'u',
            'ö' => 'o',
            'ğ' => 'g',

            // Russian
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'Yo',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'J',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Sh',
            'Ъ' => '',
            'Ы' => 'Y',
            'Ь' => '',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya',
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'j',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sh',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',

            // Ukrainian
            'Є' => 'Ye',
            'І' => 'I',
            'Ї' => 'Yi',
            'Ґ' => 'G',
            'є' => 'ye',
            'і' => 'i',
            'ї' => 'yi',
            'ґ' => 'g',

            // Czech
            'Č' => 'C',
            'Ď' => 'D',
            'Ě' => 'E',
            'Ň' => 'N',
            'Ř' => 'R',
            'Š' => 'S',
            'Ť' => 'T',
            'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c',
            'ď' => 'd',
            'ě' => 'e',
            'ň' => 'n',
            'ř' => 'r',
            'š' => 's',
            'ť' => 't',
            'ů' => 'u',
            'ž' => 'z',

            // Polish
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'e',
            'Ł' => 'L',
            'Ń' => 'N',
            'Ó' => 'o',
            'Ś' => 'S',
            'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',

            // Latvian
            'Ā' => 'A',
            'Č' => 'C',
            'Ē' => 'E',
            'Ģ' => 'G',
            'Ī' => 'i',
            'Ķ' => 'k',
            'Ļ' => 'L',
            'Ņ' => 'N',
            'Š' => 'S',
            'Ū' => 'u',
            'Ž' => 'Z',
            'ā' => 'a',
            'č' => 'c',
            'ē' => 'e',
            'ģ' => 'g',
            'ī' => 'i',
            'ķ' => 'k',
            'ļ' => 'l',
            'ņ' => 'n',
            'š' => 's',
            'ū' => 'u',
            'ž' => 'z'
        ];

        // Make custom replacements
        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);

        // Transliterate characters to ASCII
        if ($options['transliterate']) {
            $str = str_replace(array_keys($char_map), $char_map, $str);
        }

        // Replace non-alphanumeric characters with our delimiter
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

        // Remove duplicate delimiters
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

        // Truncate slug to max. characters
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');

        // Remove delimiter from ends
        $str = trim($str, $options['delimiter']);

        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }
}
