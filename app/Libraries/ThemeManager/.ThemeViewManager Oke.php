<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

class ThemeViewManager
{
    protected string $theme = 'viho';
    protected string $layout = 'default';
    protected string $baseView = 'baseView';

    protected string $defaultTheme = 'viho';
    protected string $defaultLayout = 'default';
    protected string $defaultBaseView = 'baseView';

    protected ?string $forcedTheme = null;
    protected ?string $forcedLayout = null;
    protected ?string $forcedBaseView = null;

    public function __construct(
        string $theme = 'viho',
        string $layout = 'default',
        string $baseView = 'baseView'
    ) {
        $this->theme = $theme;
        $this->layout = $layout;
        $this->baseView = $baseView;
    }

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    */


    public function setTheme(string $theme): self
    {
        $theme = trim($theme);
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $theme)) {
            throw new \InvalidArgumentException(
                'Invalid theme name.'
            );
        }
        if ($theme === '') {
            throw new \InvalidArgumentException(
                'Theme name cannot be empty.'
            );
        }

        $this->theme = $theme;
        $this->forcedTheme = $theme;

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
        $layout = trim($layout);

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $layout)) {
            throw new \InvalidArgumentException(
                'Invalid layout name.'
            );
        }


        $this->layout = $layout;
        $this->forcedLayout = $layout;

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
        $baseView = trim(str_replace('\\', '/', $baseView), '/');

        if (
            $baseView === ''
            || str_contains($baseView, "\0")
            || preg_match('~(?:^|/)\.\.?(?:/|$)~', $baseView)
        ) {
            throw new \InvalidArgumentException(
                'Invalid base view path.'
            );
        }

        $this->baseView = $baseView;
        $this->forcedBaseView = $baseView;

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

        $controller = explode('::', $controller, 2)[0];
        $controller = str_replace('/', '\\', $controller);

        if (preg_match(
            '~^Modules\\\\([a-zA-Z0-9_]+)(?:\\\\|$)~',
            $controller,
            $matches
        )) {
            return $matches[1];
        }
        return 'App';
    }




    public function getModule(string $folder = ''): string
    {
        $module = $this->detectModule();

        $path = "Modules/{$module}/Views";
        $folder = trim(str_replace('\\', '/', $folder), '/');

        if ($folder !== '') {
            $path .= '/' . $folder;
        }

        return $path . '/';
    }



    /*
    public function getModule($folder = ''): string
    {
        $module = $this->detectModule();
        $path   = ("modules/{$module}/Views/{$folder}/");
        if ($folder !== '') {
            $path .= trim($folder, '/') . '/';
        }
        return $path;
    } */

    /*
    |--------------------------------------------------------------------------
    | View resolution
    |--------------------------------------------------------------------------
    */


    public function locateView(string $view): string
    {
        $module = $this->detectModule();
        $view = trim(str_replace('\\', '/', $view), '/');

        if (
            $view === ''
            || str_contains($view, "\0")
            || preg_match('~(?:^|/)\.\.?(?:/|$)~', $view)
        ) {
            throw new \InvalidArgumentException(
                'Invalid view path.'
            );
        }
        $paths = [
            ROOTPATH . "Modules/{$module}/Views/themes/{$this->theme}/{$view}.php",
            ROOTPATH . "Modules/{$module}/Views/{$view}.php",
            APPPATH . "Views/themes/{$this->theme}/{$view}.php",
            APPPATH . "Views/{$view}.php",
        ];

        foreach ($paths as $path) {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

            if (! is_file($path)) {
                continue;
            }

            $relativePath = substr($path, strlen(ROOTPATH));
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            $relativePath = preg_replace('/\.php$/i', '', $relativePath);

            if (str_starts_with($relativePath, 'Modules/')) {
                return str_replace('/', '\\', $relativePath);
            }

            if (str_starts_with($relativePath, 'app/Views/')) {
                return substr($relativePath, strlen('app/Views/'));
            }

            return $relativePath;
        }

        return "themes/{$this->theme}/{$view}";
    }


    /*
    |--------------------------------------------------------------------------
    | Theme URL and asset paths
    |--------------------------------------------------------------------------
    */

    public function getUrlTheme(string $defaultFolder = ''): string
    {
        $folder = trim($defaultFolder, '/');

        $path = "themes/{$this->theme}";

        if ($folder !== '') {
            $path .= "/{$folder}";
        }

        return base_url($path);
    }

    public function assetUrl(
        string $asset,
        string $defaultFolder = ''
    ): string {
        return $this->getUrlTheme($defaultFolder)
            . '/'
            . ltrim($asset, '/');
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
        $folder = trim($defaultFolder, '/');
        $path = ROOTPATH . "public/themes/{$this->theme}";

        if ($folder !== '') {
            $path .= "/{$folder}";
        }

        return rtrim($path, '/') . '/' . ltrim($asset, '/');
    }

    public function asset(
        string $asset,
        string $defaultFolder = ''
    ): string {
        return $this->assetPath($asset, $defaultFolder);
    }

    public function moduleAssetUrl(
        string $asset,
        string $folder = ''
    ): string {
        $module = $this->detectModule();
        $path = "modules/{$module}";

        if ($folder !== '') {
            $path .= '/' . trim($folder, '/');
        }

        return base_url(
            $path . '/' . ltrim($asset, '/')
        );
    }

    public function moduleAssetPath(
        string $asset,
        string $folder = ''
    ): string {
        $module = $this->detectModule();
        $path = ROOTPATH . 'public/modules/' . $module;

        if ($folder !== '') {
            $path .= '/' . trim($folder, '/');
        }

        return rtrim($path, '/') . '/' . ltrim($asset, '/');
    }

    public function moduleAsset(
        string $asset,
        string $folder = ''
    ): string {
        return $this->moduleAssetPath($asset, $folder);
    }
}
