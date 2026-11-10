<?php

namespace App\Libraries;

use App\Libraries\MenuRenderer;
use App\Models\MenuModel;
use App\Models\AdminModel;
use Config\Menu as MenuConfig;

class MenuBuilder
{
    protected array $menu                     = [];
    protected array $activeUri                = [];
    protected string $activeUrl               = '';
    protected array $preset                   = [];
    protected int $maxDepth                   = 0; // 0 = unlimited
    protected string $depthOverflowFallback = '...';
    private array $resolvedUrls             = [];

    /**
     * Mapping field menu dari DB ke struktur yang digunakan.
     * @var array<string, string>
     */
    protected array $fieldMap = [];

    public function __construct(?MenuConfig $menuConfig = null)
    {
        $this->activeUrl = current_url();
        $this->activeUri = explode('/', trim(uri_string(), '/'));
        $this->setPreset();
    }

    public function setPreset($config = null, string $type = 'frontend'): static
    {
        $type           = strtolower(trim($type));
        $allowedPresets = ['frontend', 'footer', 'backend'];

        if (!in_array($type, $allowedPresets)) {
            throw new \InvalidArgumentException("Invalid preset type: {$type}");
        }

        if ($config instanceof MenuConfig) {
            $this->preset = $config->getPreset($type);
        } elseif (is_array($config) && isset($config['presets'][$type])) {
            $this->preset = require $config['presets'][$type];
        } else {
            $presetFile = APPPATH . "Libraries/Menus/Presets/{$type}.php";
            if (file_exists($presetFile)) {
                $this->preset = require $presetFile;
            } else {
                throw new \RuntimeException("Preset not found: {$type}");
            }
        }

        return $this;
    }

    public function setMenu(array $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function setMaxDepth(int $depth): static
    {
        $this->maxDepth = $depth;

        return $this;
    }

    public function setDepthOverflowFallback(string $label = '...'): static
    {
        $this->depthOverflowFallback = $label;

        return $this;
    }

    public function getPreset(): array
    {
        return $this->preset;
    }

    public function getFieldMap(): array
    {
        return $this->fieldMap;
    }

    /**
     * Bangun menu data tree (dengan dukungan Cache On/Off) untuk Frontend
     */
    public function buildFront(array $params = []): string
    {
        $table    = $params['table']    ?? '';
        $group    = $params['group']    ?? '';
        $position = $params['position'] ?? '';
        $preset   = $params['preset']   ?? 'frontend';
        $depth    = $params['depth']    ?? 0;

        $menuModel = new MenuModel();

        if (!method_exists($menuModel, 'getNestedMenu')) {
            throw new \RuntimeException('Model ' . get_class($menuModel) . ' does not support nested menu.');
        }

        $config = config('Menu');
        $this->fieldMap = $config->getFieldMap($preset);
        $this->preset   = $config->getPreset($preset);

        // --- PENERAPAN CACHE FRONTEND ---
        $cacheKey = $config->cacheKey . '_' . $preset . '_' . $group . '_' . $position;

        if ($config->cacheEnabled) {
            $raw = cache()->remember($cacheKey, $config->cacheTTL, function () use ($menuModel, $group, $position) {
                return $menuModel->getNestedMenu($group, $position);
            });
        } else {
            if (cache()->get($cacheKey)) {
                cache()->delete($cacheKey);
            }
            $raw = $menuModel->getNestedMenu($group, $position);
        }
        // --------------------------------

        $processed = $this->process($raw);

        return (new MenuRenderer($this->preset, $this->fieldMap))
            ->buildMenu($processed, $depth);
    }

    /**
     * Bangun menu data tree (dengan dukungan Cache On/Off) untuk Backend
     */
    public function buildBackend(array $params = []): string
    {
        $table    = $params['table']    ?? '';
        $group    = $params['section']  ?? '';
        $position = $params['position'] ?? '';
        $preset   = $params['preset']   ?? 'frontend';
        $depth    = $params['depth']    ?? 0;

        $menuModel = new AdminModel();

        if (!method_exists($menuModel, 'getNestedMenu')) {
            throw new \RuntimeException('Model ' . get_class($menuModel) . ' does not support nested menu.');
        }

        $config = config('Menu');
        $this->fieldMap = $config->getFieldMap($preset);
        $this->preset   = $config->getPreset($preset);

        // --- PENERAPAN CACHE BACKEND ---
        $cacheKey = $config->cacheKey . '_backend_' . $group . '_' . $position;

        if ($config->cacheEnabled) {
            $raw = cache()->remember($cacheKey, $config->cacheTTL, function () use ($menuModel, $group, $position) {
                return $menuModel->getNestedMenu($group, $position);
            });
        } else {
            if (cache()->get($cacheKey)) {
                cache()->delete($cacheKey);
            }
            $raw = $menuModel->getNestedMenu($group, $position);
        }
        // --------------------------------

        $this->menu = $this->process($raw);

        return (new MenuRenderer($this->preset, $this->fieldMap))
            ->buildMenu($this->menu, $depth);
    }

    /**
     * Method helper untuk menghapus/membersihkan cache menu secara manual
     */
    public static function clearCache(): void
    {
        $config = config('Menu');
        // Bersihkan cache umum atau Anda bisa sesuaikan dengan pola key-nya
        cache()->deleteMatching($config->cacheKey . '*');
    }

    private function process(array $items): array
    {
        $currentUrl  = rtrim($this->activeUrl, '/');
        $currentPath = trim(parse_url($currentUrl, PHP_URL_PATH), '/');
        $currentSegs = explode('/', $currentPath);

        foreach ($items as &$item) {
            $urlField = $item[$this->fieldMap['menu_url']] ?? '';
            $key      = is_string($urlField) ? $urlField : '';
            $url      = $this->resolvedUrls[$key] ??= site_url($key);

            $item['url_resolved'] = $url;

            $isFake = $urlField === '#' || strpos($urlField, '#') === 0;

            if (!$isFake) {
                $menuPath = trim(parse_url(rtrim($url, '/'), PHP_URL_PATH), '/');
                $menuSegs = explode('/', $menuPath);

                $isMatch = true;
                foreach ($menuSegs as $i => $seg) {
                    if (!isset($currentSegs[$i]) || $currentSegs[$i] !== $seg) {
                        $isMatch = false;
                        break;
                    }
                }

                $item['active'] = $isMatch;
            } else {
                $item['active'] = false;
            }

            if (!empty($item['children'])) {
                $item['children']        = $this->process($item['children']);
                $item['has_active_child'] = $this->hasActiveChild($item['children']);
                if ($item['has_active_child']) {
                    $item['active'] = true;
                }
            } else {
                $item['has_active_child'] = false;
            }
        }

        return $items;
    }

    private function hasActiveChild(array $children): bool
    {
        foreach ($children as $c) {
            if (!empty($c['active']) || !empty($c['has_active_child'])) {
                return true;
            }
        }

        return false;
    }

    public function filterByAccess(?callable $accessChecker = null): static
    {
        $checkAccess = $accessChecker ?? function ($item) {
            $user = auth()->user();

            if (empty($item['permission']) && empty($item['group_name']) && empty($item['roles'])) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            if ($user->inGroup('superadmin')) {
                return true;
            }

            if (!empty($item['permission']) && $user->can($item['permission'])) {
                return true;
            }

            if (!empty($item['group_name'])) {
                $allowedGroups = array_map('trim', explode(',', $item['group_name']));
                if ($user->inGroup(...$allowedGroups)) {
                    return true;
                }
            }

            if (!empty($item['roles'])) {
                $roles = array_map('trim', explode(',', $item['roles']));
                if ($user->inGroup(...$roles)) {
                    return true;
                }
            }

            return false;
        };

        $this->menu = $this->filterRecursive($this->menu, $checkAccess);

        return $this;
    }

    private function filterRecursive(array $items, callable $checkAccess): array
    {
        $filtered = [];
        foreach ($items as $item) {
            $item['children'] = isset($item['children']) ? $this->filterRecursive($item['children'], $checkAccess) : [];
            if ($checkAccess($item) || !empty($item['children'])) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }
}
