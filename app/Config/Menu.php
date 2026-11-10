<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Menu extends BaseConfig
{
    public string $defaultPreset = 'backend';
    public ?bool $cacheEnabled = false; // Ubah ke false untuk mematikan cache secara global
    public ?int $cacheTTL = 3600;      // Waktu kedaluwarsa cache dalam detik (1 jam)
    public ?string $cacheKey = 'app_dynamic_menus';
    public function __construct()
    {
        parent::__construct();

        // 1. Cek dari Database via service('app_settings') (Prioritas ke-1 / Tertinggi)
        $dbCacheEnabled = null;
        $dbCacheTtl     = null;

        try {
            $settings = service('app_settings');
            $dbCacheEnabled = $settings->get('menu_cache_enabled');
            $dbCacheTtl     = $settings->get('menu_cache_ttl');
        } catch (\Throwable $e) {
            // Abaikan jika service / database belum siap
        }

        // 2. Tentukan cacheEnabled: Cek DB -> Cek .env -> Gunakan Default Properti
        if ($dbCacheEnabled !== null && $dbCacheEnabled !== '') {
            $this->cacheEnabled = filter_var($dbCacheEnabled, FILTER_VALIDATE_BOOLEAN);
        } else {
            // Cek dari .env (jika di database kosong/tidak ada)
            $envCache = env('menu.cacheEnabled');
            if ($envCache !== null) {
                $this->cacheEnabled = filter_var($envCache, FILTER_VALIDATE_BOOLEAN);
            }
            // Jika di .env juga tidak ada, biarkan tetap menggunakan nilai deklarasi default ($this->cacheEnabled = false)
        }

        // 3. Tentukan cacheTTL: Cek DB -> Cek .env -> Gunakan Default Properti
        if ($dbCacheTtl !== null && $dbCacheTtl !== '') {
            $this->cacheTTL = (int) $dbCacheTtl;
        } else {
            // Cek dari .env
            $envTtl = env('menu.cacheTTL');
            if ($envTtl !== null) {
                $this->cacheTTL = (int) $envTtl;
            }
            // Jika di .env juga tidak ada, biarkan tetap menggunakan nilai deklarasi default ($this->cacheTTL = 3600)
        }
    }

    protected ?array $defaultFieldMap = [
        'default' => [
            'menu_id'         => 'id',
            'menu_parent'     => 'parent_id',
            'menu_label'      => 'title',
            'menu_label_slug' => 'title_slug',
            'menu_icon'       => 'icon',
            'menu_url'        => 'url',
            'menu_order'      => 'sequence',
            'menu_position'   => 'position',
            'menu_badge'      => null,
            'menu_content'    => null,
            'menu_megamenu'   => null,
        ]
    ];

    public array $fieldMap = [
        'backend' => [
            'menu_id'         => 'id',
            'menu_parent'     => 'parent_id',
            'menu_label'      => 'title',
            'menu_label_slug' => 'title_slug',
            'menu_icon'       => 'icon',
            'menu_url'        => 'url',
            'menu_order'      => 'sequence',
            'menu_position'   => 'position',
            'menu_badge'      => null,
            'menu_content'    => null,
            'menu_megamenu'   => null,
        ],
        'frontend' => [
            'menu_id'         => 'id_menu',
            'menu_parent'     => 'id_parent',
            'menu_label'      => 'nama_menu',
            'menu_label_slug' => 'nama_menu_slug',
            'menu_icon'       => 'menu_icon',
            'menu_url'        => 'link',
            'menu_order'      => 'sort_order',
            'menu_position'   => 'position',
            'menu_badge'      => 'badge',
            'menu_content'    => 'content',
            'menu_megamenu'   => 'megamenu'
        ],
        'footer' => [
            'menu_id'         => 'id_menu',
            'menu_parent'     => 'id_parent',
            'menu_label'      => 'nama_menu',
            'menu_label_slug' => 'nama_menu_slug',
            'menu_icon'       => 'menu_icon',
            'menu_url'        => 'link',
            'menu_order'      => 'sort_order',
            'menu_position'   => 'position',
            'menu_badge'      => 'badge',
            'menu_content'    => 'content',
            'menu_megamenu'   => 'megamenu'
        ],
        'sitelink' => [
            'menu_id'         => 'id_menu',
            'menu_parent'     => 'id_parent',
            'menu_label'      => 'nama_menu',
            'menu_label_slug' => 'nama_menu_slug',
            'menu_icon'       => 'menu_icon',
            'menu_url'        => 'link',
            'menu_order'      => 'sort_order',
            'menu_position'   => 'position',
            'menu_badge'      => 'badge',
            'menu_content'    => 'content',
            'menu_megamenu'   => 'megamenu'
        ],
        'adminmart' => [
            'menu_id'         => 'id',
            'menu_parent'     => 'parent_id',
            'menu_label'      => 'title',
            'menu_label_slug' => 'title_slug',
            'menu_icon'       => 'icon',
            'menu_url'        => 'url',
            'menu_order'      => 'sequence',
            'menu_position'   => 'position',
            'menu_badge'      => null,
            'menu_content'    => null,
            'menu_megamenu'   => null,
        ],
        'adminmart_horizontal' => [
            'menu_id'         => 'id',
            'menu_parent'     => 'parent_id',
            'menu_label'      => 'title',
            'menu_label_slug' => 'title_slug',
            'menu_icon'       => 'icon',
            'menu_url'        => 'url',
            'menu_order'      => 'sequence',
            'menu_position'   => 'position',
            'menu_badge'      => null,
            'menu_content'    => null,
            'menu_megamenu'   => null,
        ],
    ];

    public array $presets = [
        'backend'              => APPPATH . 'Libraries/Menus/Presets/adminlte.php',
        'frontend'             => APPPATH . 'Libraries/Menus/Presets/eduport.php',
        'frontend_admin'       => APPPATH . 'Libraries/Menus/Presets/frontend_admin.php',
        'footer'               => APPPATH . 'Libraries/Menus/Presets/footer.php',
        'sitelink'             => APPPATH . 'Libraries/Menus/Presets/sitelink.php',
        'adminmart'            => APPPATH . 'Libraries/Menus/Presets/adminmart.php',
        'adminmart_horizontal' => APPPATH . 'Libraries/Menus/Presets/adminmart_horizontal.php',
    ];

    public function get(?string $preset = null): array
    {
        $preset ??= $this->defaultPreset;

        if (!isset($this->presets[$preset])) {
            throw new \InvalidArgumentException("Preset menu [$preset] tidak ditemukan.");
        }

        return require $this->presets[$preset];
    }

    /**
     * Ambil preset dengan fallback ke defaultPreset jika tidak terdaftar,
     * dan fallback ke file fisik bawaan jika path preset tidak ditemukan.
     */
    public function getPreset(string $name = ''): array
    {
        $name = strtolower(trim($name));

        // 1. Fallback jika nama preset kosong atau tidak ada di daftar array
        if (empty($name) || !isset($this->presets[$name])) {
            $name = $this->defaultPreset;
        }

        $presetFile = $this->presets[$name] ?? APPPATH . "Libraries/Menus/Presets/{$name}.php";

        // 2. Fallback jika file fisik preset tidak ada di direktori
        if (!file_exists($presetFile)) {
            // Gunakan file default atau kembalikan array kosong jika benar-benar tidak ada
            $presetFile = APPPATH . 'Libraries/Menus/Presets/adminlte.php';
        }

        $presetConfig = file_exists($presetFile) ? require $presetFile : [];

        // 3. Ambil field map dengan fallback ke default field map
        $field = $this->fieldMap[$name] ?? $this->defaultFieldMap;

        return array_merge($field, $presetConfig);
    }

    /**
     * Ambil field map dengan fallback ke defaultFieldMap
     */
    public function getFieldMap(string $type = ''): array
    {
        $type = strtolower(trim($type));

        return $this->fieldMap[$type] ?? $this->defaultFieldMap;
    }
}
