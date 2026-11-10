<?php

namespace App\Libraries\ThemeManager;

class ThemeAssetManager
{
    protected string $theme;
    protected string $defaultFolder;

    protected array $css = [];

    protected array $jsQueue = [
        'header' => [],
        'footer' => [],
    ];

    protected array $inlineCSS = [];

    protected array $inlineJS = [
        'header' => [],
        'footer' => [],
    ];

    protected int $jsCounter = 0;

    public function __construct(
        string $theme = 'viho',
        string $defaultFolder = ''
    ) {
        $this->theme = $theme;
        $this->defaultFolder = $defaultFolder;
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    public function setTheme(string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setDefaultFolder(string $folder): self
    {
        $this->defaultFolder = trim($folder, '/');

        return $this;
    }

    public function getDefaultFolder(): string
    {
        return $this->defaultFolder;
    }

    /*
    |--------------------------------------------------------------------------
    | Asset URL dan Path
    |--------------------------------------------------------------------------
    */

    public function assetUrl(string $path = ''): string
    {
        return 'themes/' . $this->theme . '/'
            . ltrim($path, '/');
    }

    public function assetWithUrl(string $path = ''): string
    {
        return base_url(
            'themes/' . $this->theme . $this->defaultFolder . '/'
                . ltrim($path, '/')
        );
    }

    public function getAsset(string $path = ''): string
    {
        return $this->assetWithUrl($path);
    }

    public function assetPath(string $path = ''): string
    {
        return FCPATH
            . 'themes/' . $this->theme . $this->defaultFolder . '/'
            . ltrim($path, '/');
    }

    public function asset(
        string $path,
        bool $versioned = true
    ): string {
        $url = $this->assetUrl($path);
        $filePath = $this->assetPath($path);

        if ($versioned && is_file($filePath)) {
            $url .= '?v=' . filemtime($filePath);
        }

        return $url;
    }

    public function moduleAssetUrl(
        string $module,
        string $path = ''
    ): string {
        return base_url(
            "modules/{$module}{$this->defaultFolder}/"
                . ltrim($path, '/')
        );
    }

    public function moduleAssetPath(
        string $module,
        string $path = ''
    ): string {
        return FCPATH
            . "modules/{$module}{$this->defaultFolder}/"
            . ltrim($path, '/');
    }

    public function moduleAsset(
        string $module,
        string $path = '',
        bool $versioned = true
    ): string {
        $url = $this->moduleAssetUrl($module, $path);
        $filePath = $this->moduleAssetPath($module, $path);

        if ($versioned && is_file($filePath)) {
            $url .= '?v=' . filemtime($filePath);
        }

        return $url;
    }

    /*
    |--------------------------------------------------------------------------
    | CSS
    |--------------------------------------------------------------------------
    */

    public function addCss(
        string|array $href,
        string $folder = '',
        array|bool $options = []
    ): self {
        $files = is_array($href) ? $href : [$href];

        foreach ($files as $item) {
            $filename = '';
            $itemFolder = $folder;
            $attributes = [];
            $versioned = true;

            if (is_array($item)) {
                $filename = $item[0] ?? '';
                $itemFolder = $item[1] ?? $folder;

                $third = $item[2] ?? null;

                if (is_array($third)) {
                    $attributes = $third;
                    $versioned = $attributes['versioned'] ?? true;
                    unset($attributes['versioned']);
                } elseif (is_bool($third)) {
                    $versioned = $third;
                } elseif (is_string($third)) {
                    $attributes = $this->parseAttributes($third);
                }
            } else {
                $filename = $item;

                if (is_array($options)) {
                    $attributes = $options;
                    $versioned = $attributes['versioned'] ?? true;
                    unset($attributes['versioned']);
                } elseif (is_bool($options)) {
                    $versioned = $options;
                }
            }

            if ($filename === '') {
                continue;
            }

            if (filter_var($filename, FILTER_VALIDATE_URL)) {
                $finalHref = $filename;
            } else {
                $filename = preg_replace('/\.css$/i', '', $filename) . '.css';

                $filePath = $this->resolveCssPath(
                    $filename,
                    $itemFolder
                );

                $fullPath = FCPATH . $filePath;

                $finalHref = base_url($filePath);

                if ($versioned && is_file($fullPath)) {
                    $finalHref .= '?v=' . filemtime($fullPath);
                }
            }

            $exists = false;

            foreach ($this->css as $existing) {
                if (($existing['href'] ?? '') === $finalHref) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $this->css[] = [
                    'href' => $finalHref,
                    'attr' => $attributes,
                ];
            }
        }

        return $this;
    }

    protected function resolveCssPath(
        string $filename,
        string $folder
    ): string {
        if ($folder === '') {
            return 'themes/' . $this->theme
                . $this->defaultFolder . '/css/' . $filename;
        }

        $folder = trim($folder, '/');
        $parts = explode('/', $folder);

        if (strtolower($parts[0]) === 'plugins' && isset($parts[1])) {
            return 'themes/' . $this->theme
                . $this->defaultFolder
                . '/plugins/' . $parts[1] . '/' . $filename;
        }

        if (strtolower($parts[0]) === 'vendor' && isset($parts[1])) {
            return 'themes/' . $this->theme
                . $this->defaultFolder
                . '/vendor/' . $parts[1] . '/' . $filename;
        }

        if (str_contains($folder, '/')) {
            return 'themes/' . $this->theme . '/'
                . $folder . '/' . $filename;
        }

        return $folder . '/' . $filename;
    }

    public function addInlineCSS(
        string|array $css,
        bool $prepend = false
    ): self {
        $items = is_array($css) ? $css : [$css];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }

            if (in_array($item, $this->inlineCSS, true)) {
                continue;
            }

            if ($prepend) {
                array_unshift($this->inlineCSS, $item);
            } else {
                $this->inlineCSS[] = $item;
            }
        }

        return $this;
    }

    public function removeCss(string $filename): self
    {
        $filename = preg_replace('/\.css$/i', '', $filename);

        $this->css = array_values(array_filter(
            $this->css,
            static function (array $item) use ($filename): bool {
                $path = parse_url($item['href'] ?? '', PHP_URL_PATH) ?? '';
                $basename = pathinfo($path, PATHINFO_FILENAME);

                return $basename !== $filename;
            }
        ));

        return $this;
    }

    public function replaceCss(
        string $oldFilename,
        string $newFilename,
        string $folder = '',
        array|bool $options = []
    ): self {
        $this->removeCss($oldFilename);

        return $this->addCss($newFilename, $folder, $options);
    }

    public function removeAllCss(): self
    {
        $this->css = [];
        $this->inlineCSS = [];

        return $this;
    }

    public function renderCss(?array $cssQueue = null): string
    {
        $queue = $cssQueue ?? $this->css;
        $output = '';

        foreach ($queue as $item) {
            $href = htmlspecialchars(
                (string) ($item['href'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            );

            $attributes = $item['attr'] ?? [];

            if (! is_array($attributes)) {
                $attributes = [];
            }

            $hasRel = false;

            foreach ($attributes as $key => $value) {
                if (strtolower((string) $key) === 'rel') {
                    $hasRel = true;
                    break;
                }
            }

            if (! $hasRel) {
                $attributes['rel'] = 'stylesheet';
            }

            $attrString = $this->renderAttributes($attributes);

            $output .= "<link href=\"{$href}\"{$attrString}>" . PHP_EOL;

            if (($attributes['rel'] ?? '') === 'preload') {
                $output .= '<noscript><link href="'
                    . $href
                    . '" rel="stylesheet"></noscript>'
                    . PHP_EOL;
            }
        }

        if ($this->inlineCSS !== []) {
            $output .= "<style>\n"
                . implode("\n", $this->inlineCSS)
                . "\n</style>\n";
        }

        return $output;
    }

    /*
    |--------------------------------------------------------------------------
    | JavaScript
    |--------------------------------------------------------------------------
    */

    public function addJs(
        string|array $src,
        string $folder = '',
        array|bool $options = [],
        string $position = 'footer'
    ): self {
        $files = is_array($src) ? $src : [$src];

        foreach ($files as $item) {
            $filename = '';
            $itemFolder = $folder;
            $attributes = [];
            $versioned = true;
            $itemPosition = $position;

            if (is_array($item)) {
                $filename = $item[0] ?? '';
                $itemFolder = $item[1] ?? $folder;

                $third = $item[2] ?? null;
                $itemPosition = $item[3] ?? $position;

                if (is_array($third)) {
                    $attributes = $third;
                    $versioned = $attributes['versioned'] ?? true;
                    unset($attributes['versioned']);
                } elseif (is_bool($third)) {
                    $versioned = $third;
                } elseif (is_string($third)) {
                    $attributes = $this->parseAttributes($third);
                }
            } else {
                $filename = $item;

                if (is_array($options)) {
                    $attributes = $options;
                    $versioned = $attributes['versioned'] ?? true;
                    unset($attributes['versioned']);
                } elseif (is_bool($options)) {
                    $versioned = $options;
                }
            }

            if ($filename === '') {
                continue;
            }

            if (filter_var($filename, FILTER_VALIDATE_URL)) {
                $finalSrc = $filename;
            } else {
                $filename = preg_replace('/\.js$/i', '', $filename) . '.js';

                $filePath = $this->resolveJsPath(
                    $filename,
                    $itemFolder
                );

                $fullPath = FCPATH . $filePath;

                $finalSrc = base_url($filePath);

                if ($versioned && is_file($fullPath)) {
                    $finalSrc .= '?v=' . filemtime($fullPath);
                }
            }

            $targetPosition = in_array(
                $itemPosition,
                ['header', 'footer'],
                true
            ) ? $itemPosition : 'footer';

            $exists = false;

            foreach ($this->jsQueue[$targetPosition] as $existing) {
                if (($existing['src'] ?? '') === $finalSrc) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $this->jsQueue[$targetPosition][] = [
                    'src' => $finalSrc,
                    'attr' => $attributes,
                    'order' => ++$this->jsCounter,
                ];
            }
        }

        return $this;
    }

    protected function resolveJsPath(
        string $filename,
        string $folder
    ): string {
        if ($folder === '') {
            return 'themes/' . $this->theme
                . $this->defaultFolder . '/js/' . $filename;
        }

        $folder = trim($folder, '/');
        $parts = explode('/', $folder);

        if (strtolower($parts[0]) === 'plugins' && isset($parts[1])) {
            return 'themes/' . $this->theme
                . $this->defaultFolder
                . '/plugins/' . $parts[1] . '/' . $filename;
        }

        return 'themes/' . $this->theme . '/'
            . $folder . '/' . $filename;
    }

    public function replaceJs(
        string $oldFilename,
        string $newFilename,
        string $folder = '',
        array|bool $options = []
    ): self {
        $this->removeJs($oldFilename);

        return $this->addJs($newFilename, $folder, $options);
    }

    public function removeJs(
        string $targetSrc,
        string $position = 'footer'
    ): self {
        if (! isset($this->jsQueue[$position])) {
            return $this;
        }

        $target = basename(
            preg_replace('/\.js$/i', '', $targetSrc)
        ) . '.js';

        $this->jsQueue[$position] = array_values(array_filter(
            $this->jsQueue[$position],
            static function (array $item) use ($target): bool {
                $path = parse_url($item['src'] ?? '', PHP_URL_PATH) ?? '';

                return basename($path) !== $target;
            }
        ));

        return $this;
    }

    public function removeAllJs(): self
    {
        $this->jsQueue = [
            'header' => [],
            'footer' => [],
        ];

        $this->inlineJS = [
            'header' => [],
            'footer' => [],
        ];

        return $this;
    }

    public function addInlineJS(
        string|array $script,
        string $position = 'footer'
    ): self {
        $position = in_array(
            $position,
            ['header', 'footer'],
            true
        ) ? $position : 'footer';

        $scripts = is_array($script) ? $script : [$script];

        foreach ($scripts as $item) {
            if (
                is_string($item)
                && ! in_array($item, $this->inlineJS[$position], true)
            ) {
                $this->inlineJS[$position][] = $item;
            }
        }

        return $this;
    }

    public function addInlineJsOnce(
        string|array $script,
        string $position = 'footer'
    ): self {
        return $this->addInlineJS($script, $position);
    }

    public function renderInlineJS(string $position = 'footer'): string
    {
        if (empty($this->inlineJS[$position])) {
            return '';
        }

        $output = '';

        foreach ($this->inlineJS[$position] as $script) {
            $output .= "<script>\n{$script}\n</script>\n";
        }

        return $output;
    }

    public function renderJs(string $position = 'footer'): string
    {
        if (empty($this->jsQueue[$position])) {
            return '';
        }

        $queue = $this->jsQueue[$position];

        usort(
            $queue,
            static fn(array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0)
        );

        $output = '';

        foreach ($queue as $item) {
            $src = htmlspecialchars(
                (string) ($item['src'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            );

            $attributes = is_array($item['attr'] ?? null)
                ? $item['attr']
                : [];

            $output .= '<script src="' . $src . '"'
                . $this->renderAttributes($attributes)
                . "></script>\n";
        }

        return $output;
    }

    public function getCssQueue(): array
    {
        return $this->css;
    }

    public function getJsQueue(): array
    {
        return $this->jsQueue;
    }

    public function getInlineCSS(): array
    {
        return $this->inlineCSS;
    }

    public function getInlineJS(): array
    {
        return $this->inlineJS;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function parseAttributes(string $input): array
    {
        $attributes = [];

        preg_match_all(
            '/([\w:-]+)\s*=\s*"([^"]*)"/',
            $input,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        preg_match_all(
            '/(?<![\w:-])(async|defer|nomodule)(?![\w:-])/',
            $input,
            $booleanMatches
        );

        foreach ($booleanMatches[1] ?? [] as $attribute) {
            $attributes[$attribute] = true;
        }

        return $attributes;
    }

    protected function renderAttributes(array $attributes): string
    {
        $output = '';

        foreach ($attributes as $key => $value) {
            if (
                ! is_string($key)
                || ! preg_match('/^[a-zA-Z_:][a-zA-Z0-9:._-]*$/', $key)
            ) {
                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            $escapedKey = htmlspecialchars(
                $key,
                ENT_QUOTES,
                'UTF-8'
            );

            if ($value === true) {
                $output .= ' ' . $escapedKey;
                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $escapedValue = htmlspecialchars(
                (string) $value,
                ENT_QUOTES,
                'UTF-8'
            );

            $output .= ' ' . $escapedKey
                . '="' . $escapedValue . '"';
        }

        return $output;
    }
}
