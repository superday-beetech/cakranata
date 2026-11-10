<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

use InvalidArgumentException;

class ThemeViewManager
{
    protected string $theme;
    protected string $layout;
    protected string $baseView;

    protected string $defaultTheme;
    protected string $defaultLayout;
    protected string $defaultBaseView;

    protected ?string $forcedTheme = null;
    protected ?string $forcedLayout = null;
    protected ?string $forcedBaseView = null;
    protected int $viewCacheTtl = 300;
    // protected string $viewCachePrefix = 'theme_view:';
    protected string $viewCachePrefix = 'theme_view_';

    public function __construct(
        string $theme = 'viho',
        string $layout = 'default',
        string $baseView = 'baseView'
    ) {
        $theme    = $this->normalizeName($theme, 'theme');
        $layout   = $this->normalizeName($layout, 'layout');
        $baseView = $this->normalizeRelativePath($baseView, 'base view');

        $this->defaultTheme    = $theme;
        $this->defaultLayout   = $layout;
        $this->defaultBaseView = $baseView;

        $this->theme    = $theme;
        $this->layout   = $layout;
        $this->baseView = $baseView;
    }

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    */

    public function setTheme(string $theme): self
    {
        $this->theme = $this->normalizeName($theme, 'theme');
        $this->forcedTheme = $this->theme;

        return $this;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function wasThemeOverridden(): bool
    {
        return $this->forcedTheme !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    */

    public function setLayout(string $layout): self
    {
        $this->layout = $this->normalizeName($layout, 'layout');
        $this->forcedLayout = $this->layout;

        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function wasLayoutOverridden(): bool
    {
        return $this->forcedLayout !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Base view
    |--------------------------------------------------------------------------
    */

    public function setBaseView(string $baseView): self
    {
        $this->baseView = $this->normalizeRelativePath(
            $baseView,
            'base view'
        );

        $this->forcedBaseView = $this->baseView;

        return $this;
    }

    public function getBaseView(): string
    {
        return $this->baseView;
    }

    public function wasBaseViewOverridden(): bool
    {
        return $this->forcedBaseView !== null;
    }

    public function resetThemeOverrides(): self
    {
        $this->theme    = $this->defaultTheme;
        $this->layout   = $this->defaultLayout;
        $this->baseView = $this->defaultBaseView;

        $this->forcedTheme    = null;
        $this->forcedLayout   = null;
        $this->forcedBaseView = null;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Module detection
    |--------------------------------------------------------------------------
    */

    public function detectModule(): string
    {
        $router = service('router');

        $controller = trim(
            (string) ($router?->controllerName() ?? ''),
            '\\/'
        );

        // Hilangkan method jika formatnya Class::method.
        $controller = explode('::', $controller, 2)[0];

        // Normalisasi pemisah namespace.
        $controller = str_replace('/', '\\', $controller);

        $segments = explode('\\', $controller);

        if (
            count($segments) >= 2
            && $segments[0] === 'Modules'
            && preg_match('/^[A-Za-z0-9_]+$/', $segments[1])
        ) {
            return $segments[1];
        }

        return 'App';
    }

    public function getModule(string $folder = ''): string
    {
        $module = $this->detectModule();

        $path = "Modules/{$module}/Views";

        $folder = $folder === ''
            ? ''
            : $this->normalizeRelativePath($folder, 'module view folder');

        if ($folder !== '') {
            $path .= '/' . $folder;
        }

        return $path . '/';
    }

    /*
    |--------------------------------------------------------------------------
    | View resolution
    |--------------------------------------------------------------------------
    */


    public function locateViewOld(string $view): string
    {
        $module = $this->detectModule();
        $view   = $this->normalizeRelativePath($view, 'view');

        $cacheKey = $this->viewCachePrefix . hash('sha256', json_encode([
            'module' => $module,
            'theme'  => $this->theme,
            'view'   => $view,
        ], JSON_THROW_ON_ERROR));

        $cache = \Config\Services::cache();
        $cached = $cache->get($cacheKey);

        if (
            is_array($cached)
            && isset($cached['result'], $cached['file'])
            && is_string($cached['result'])
            && is_string($cached['file'])
            && is_file($cached['file'])
        ) {
            return $cached['result'];
        }

        $result = $this->resolveViewPath($module, $view);

        // Jangan cache fallback jika file view belum ditemukan.
        if ($result['file'] !== null) {
            $cache->save($cacheKey, $result, $this->viewCacheTtl);
        } else {
            $cache->delete($cacheKey);
        }

        return $result['result'];
    }


    public function locateView(string $view): string
    {
        // Cache dinonaktifkan: langsung cari file view.
        if (! (bool) service('settings')->get('App.cacheTheme')) {
            return $this->locateViewNonCache($view);
        }

        $module = $this->detectModule();
        $view   = $this->normalizeRelativePath($view, 'view');

        $cacheKey = $this->viewCachePrefix . hash('sha256', json_encode([
            'module' => $module,
            'theme'  => $this->theme,
            'view'   => $view,
        ], JSON_THROW_ON_ERROR));

        $cache  = \Config\Services::cache();
        $cached = $cache->get($cacheKey);

        if (
            is_array($cached)
            && isset($cached['result'], $cached['file'])
            && is_string($cached['result'])
            && is_string($cached['file'])
            && is_file($cached['file'])
        ) {
            return $cached['result'];
        }

        $result = $this->resolveViewPath($module, $view);

        if ($result['file'] !== null) {
            $cache->save($cacheKey, $result, $this->viewCacheTtl);
        } else {
            $cache->delete($cacheKey);
        }

        return $result['result'];
    }

    /**
     * Resolusi view tanpa menggunakan cache.
     */
    public function locateViewNonCache(string $view): string
    {
        $module = $this->detectModule();
        $view   = $this->normalizeRelativePath($view, 'view');

        $result = $this->resolveViewPath($module, $view);

        return $result['result'];
    }

    /**
     * Menentukan lokasi view sesuai urutan prioritas yang berlaku.
     *
     * @return array{result: string, file: ?string}
     */
    protected function resolveViewPath(string $module, string $view): array
    {
        $paths = [
            [
                'path' => ROOTPATH
                    . "Modules/{$module}/Views/themes/{$this->theme}/{$view}.php",
                'type' => 'module',
            ],
            [
                'path' => ROOTPATH
                    . "Modules/{$module}/Views/{$view}.php",
                'type' => 'module',
            ],
            [
                'path' => APPPATH
                    . "Views/themes/{$this->theme}/{$view}.php",
                'type' => 'app',
            ],
            [
                'path' => APPPATH
                    . "Views/{$view}.php",
                'type' => 'app',
            ],
        ];

        foreach ($paths as $candidate) {
            $path = str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $candidate['path']
            );

            if (! is_file($path)) {
                continue;
            }

            $relativePath = preg_replace(
                '/\.php$/i',
                '',
                $candidate['type'] === 'module'
                    ? substr($path, strlen(ROOTPATH))
                    : substr(
                        $path,
                        strlen(APPPATH . 'Views' . DIRECTORY_SEPARATOR)
                    )
            );

            $relativePath = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                $relativePath
            );

            return [
                'result' => $candidate['type'] === 'module'
                    ? str_replace('/', '\\', $relativePath)
                    : $relativePath,
                'file' => $path,
            ];
        }

        return [
            'result' => "themes/{$this->theme}/{$view}",
            'file' => null,
        ];
    }

    /**
     * Menghapus cache resolusi untuk view tertentu.
     */
    public function clearViewCache(string $view): self
    {
        $module = $this->detectModule();
        $view   = $this->normalizeRelativePath($view, 'view');

        $cacheKey = $this->viewCachePrefix . hash('sha256', json_encode([
            'module' => $module,
            'theme'  => $this->theme,
            'view'   => $view,
        ], JSON_THROW_ON_ERROR));

        \Config\Services::cache()->delete($cacheKey);

        return $this;
    }


    public function locateViewNonCacheOld(string $view): string
    {
        $module = $this->detectModule();

        $view = $this->normalizeRelativePath($view, 'view');

        $paths = [
            [
                'path' => ROOTPATH
                    . "Modules/{$module}/Views/themes/{$this->theme}/{$view}.php",
                'type' => 'module',
            ],
            [
                'path' => ROOTPATH
                    . "Modules/{$module}/Views/{$view}.php",
                'type' => 'module',
            ],
            [
                'path' => APPPATH
                    . "Views/themes/{$this->theme}/{$view}.php",
                'type' => 'app',
            ],
            [
                'path' => APPPATH
                    . "Views/{$view}.php",
                'type' => 'app',
            ],
        ];

        foreach ($paths as $candidate) {
            $path = str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $candidate['path']
            );

            if (! is_file($path)) {
                continue;
            }

            $relativePath = preg_replace(
                '/\.php$/i',
                '',
                $candidate['type'] === 'module'
                    ? substr($path, strlen(ROOTPATH))
                    : substr($path, strlen(APPPATH . 'Views' . DIRECTORY_SEPARATOR))
            );

            $relativePath = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                $relativePath
            );

            if ($candidate['type'] === 'module') {
                return str_replace('/', '\\', $relativePath);
            }

            return $relativePath;
        }

        // Pertahankan fallback theme yang digunakan sebelumnya.
        return "themes/{$this->theme}/{$view}";
    }

    /*
    |--------------------------------------------------------------------------
    | Theme URL and asset paths
    |--------------------------------------------------------------------------
    */

    public function getUrlTheme(string $defaultFolder = ''): string
    {
        $path = "themes/{$this->theme}";
        $folder = $defaultFolder === ''
            ? ''
            : $this->normalizeRelativePath($defaultFolder, 'theme asset folder');

        if ($folder !== '') {
            $path .= '/' . $folder;
        }

        return base_url($path);
    }

    public function assetUrl(
        string $asset,
        string $defaultFolder = ''
    ): string {
        $asset = $this->normalizeRelativePath($asset, 'asset');

        return rtrim($this->getUrlTheme($defaultFolder), '/')
            . '/'
            . $asset;
    }

    public function assetWithUrl(
        string $asset,
        string $defaultFolder = ''
    ): string {
        return $this->assetUrl($asset, $defaultFolder);
    }

    public function getAsset(
        string $asset,
        string $defaultFolder = ''
    ): string {
        return $this->assetUrl($asset, $defaultFolder);
    }

    public function assetPath(
        string $asset,
        string $defaultFolder = ''
    ): string {
        $asset = $this->normalizeRelativePath($asset, 'asset');

        $path = ROOTPATH . 'public/themes/' . $this->theme;

        $folder = $defaultFolder === ''
            ? ''
            : $this->normalizeRelativePath($defaultFolder, 'theme asset folder');

        if ($folder !== '') {
            $path .= '/' . $folder;
        }

        return rtrim($path, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $asset);
    }

    public function asset(
        string $asset,
        string $defaultFolder = ''
    ): string {
        return $this->assetPath($asset, $defaultFolder);
    }

    /*
    |--------------------------------------------------------------------------
    | Module asset paths
    |--------------------------------------------------------------------------
    */

    public function moduleAssetUrl(
        string $asset,
        string $folder = ''
    ): string {
        $module = $this->detectModule();
        $asset = $this->normalizeRelativePath($asset, 'module asset');

        $path = "modules/{$module}";

        $folder = $folder === ''
            ? ''
            : $this->normalizeRelativePath($folder, 'module asset folder');

        if ($folder !== '') {
            $path .= '/' . $folder;
        }

        return base_url($path . '/' . $asset);
    }

    public function moduleAssetPath(
        string $asset,
        string $folder = ''
    ): string {
        $module = $this->detectModule();
        $asset = $this->normalizeRelativePath($asset, 'module asset');

        $path = ROOTPATH . 'public/modules/' . $module;

        $folder = $folder === ''
            ? ''
            : $this->normalizeRelativePath($folder, 'module asset folder');

        if ($folder !== '') {
            $path .= '/' . $folder;
        }

        return rtrim($path, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $asset);
    }

    public function moduleAsset(
        string $asset,
        string $folder = ''
    ): string {
        return $this->moduleAssetPath($asset, $folder);
    }

    /*
    |--------------------------------------------------------------------------
    | Internal validation
    |--------------------------------------------------------------------------
    */

    protected function normalizeName(
        string $value,
        string $type
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || ! preg_match('/^[A-Za-z0-9_-]+$/', $value)
        ) {
            throw new InvalidArgumentException(
                "Invalid {$type} name."
            );
        }

        return $value;
    }

    protected function normalizeRelativePath(
        string $value,
        string $type
    ): string {
        $value = trim(str_replace('\\', '/', $value), '/');

        if (
            $value === ''
            || str_contains($value, "\0")
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $value)
            || str_starts_with($value, '//')
            || preg_match('/^[A-Za-z]:/', $value)
        ) {
            throw new InvalidArgumentException(
                "Invalid {$type} path."
            );
        }

        return $value;
    }
}
