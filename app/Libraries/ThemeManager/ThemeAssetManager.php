<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

class ThemeAssetManager
{
    protected array $css = [];

    protected array $inlineCSS = [];

    protected array $cssQueue = [];

    protected array $js = [
        'head'   => [],
        'footer' => [],
    ];

    protected array $jsQueue = [
        'header' => [],
        'footer' => [],
    ];

    protected array $inlineJS = [
        'head'   => [],
        'footer' => [],
    ];

    protected array $plugins = [];

    protected int $jsCounter = 0;
    protected $assetManager;

    protected ThemeViewManager $viewManager;
    protected string $defaultFolder;

    public function __construct(
        ThemeViewManager $viewManager,
        string $defaultFolder = ''
    ) {
        $this->viewManager = $viewManager;
        $this->defaultFolder = $defaultFolder;
    }
    /*
    |--------------------------------------------------------------------------
    | CSS
    |--------------------------------------------------------------------------
    */

    public function addCss(string|array $href, string $Folder = '', array|bool $options = []): self
    {
        $files = is_array($href) ? $href : [$href];

        foreach ($files as $file) {
            $filename   = '';
            $folder     = $Folder;
            $attributes = [];
            $versioned  = true;

            if (is_array($file)) {
                $filename = $file[0] ?? '';
                $folder   = $file[1] ?? $Folder;
                $third    = $file[2] ?? null;

                if (is_array($third)) {
                    $versioned = $third['versioned'] ?? true;
                    unset($third['versioned']);
                    $attributes = $third;
                } elseif (is_string($third) && str_contains($third, '=')) {
                    preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $third, $matches, PREG_SET_ORDER);
                    foreach ($matches as $match) {
                        $attributes[$match[1]] = $match[2];
                    }
                } elseif (is_bool($third)) {
                    $versioned = $third;
                }
            } else {
                $filename = $file;
                // Ambil option global
                if (is_array($options)) {
                    $versioned = $options['versioned'] ?? true;
                    $fileOptions = $options;

                    unset($fileOptions['versioned']);
                    $attributes = $options;
                } elseif (is_bool($options)) {
                    $versioned = $options;
                }
            }

            // Handle external URLs
            if (filter_var($filename, FILTER_VALIDATE_URL)) {
                $alreadyAdded = in_array($filename, array_column($this->css, 'href'));
                if (!$alreadyAdded) {
                    $this->css[] = ['href' => $filename, 'attr' => $attributes];
                }

                continue;
            }

            $file     = preg_replace('/\.css$/', '', $filename);
            $fileName = $file . '.css';
            $theme = $this->viewManager->getTheme();
            if ($folder) {
                if (str_contains($folder, '/')) {
                    $f = explode('/', $folder);
                    if (strtolower($f[0]) === 'plugins') {
                        $filePath = "themes/{$theme}{$this->defaultFolder}/plugins/{$f[1]}/{$fileName}";
                    } elseif (strtolower($f[0]) === 'vendor') {
                        $filePath = "themes/{$theme}{$this->defaultFolder}/vendor/{$f[1]}/{$fileName}";
                    } else {
                        $trimFolder = trim($folder, '/');
                        $filePath = "themes/{$theme}{$trimFolder}/{$fileName}";
                    }
                } else {
                    $filePath = rtrim($folder, '/') . '/' . $fileName;
                }
            } else {
                $filePath = "themes/{$theme}{$this->defaultFolder}/css/{$fileName}";
            }

            $fullPath  = FCPATH . $filePath;
            $version   = ($versioned && file_exists($fullPath)) ? filemtime($fullPath) : '';
            $finalHref = base_url($filePath) . ($version ? '?v=' . $version : '');

            // Hindari duplikat
            $alreadyAdded = in_array($fileName, array_map(fn($item) => basename(strtok($item['href'], '?')), $this->css));

            if (!$alreadyAdded) {
                $this->css[] = ['href' => $finalHref, 'attr' => $attributes];
            }
        }

        return $this;
    }

    public function addInlineCSS(string|array $css, bool $prepend = false): self
    {
        $items = is_array($css) ? $css : [$css];

        foreach ($items as $line) {
            if (!in_array($line, $this->inlineCSS, true)) {
                $prepend
                    ? array_unshift($this->inlineCSS, $line)
                    : $this->inlineCSS[] = $line;
            }
        }

        return $this;
    }

    /**
     * Hapus CSS berdasarkan nama file (tanpa .css dan tanpa versi query)
     */
    public function removeCss(string $filename): self
    {
        $filename = preg_replace('/\.css$/', '', $filename);

        $this->css = array_filter($this->css, function ($item) use ($filename) {
            $basename = basename(strtok($item['href'], '?'), '.css');

            return $basename !== $filename;
        });

        return $this;
    }

    /**
     * Ganti CSS tertentu dengan yang baru
     */
    public function replaceCss(string $oldFilename, string $newFilename, string $folder = '', array|bool $options = []): self
    {
        $this->removeCss($oldFilename);

        return $this->addCss($newFilename, $folder, $options);
    }
    /**
     * Hapus semua file CSS dan inline CSS
     */
    public function removeAllCss(): self
    {
        $this->css = [];
        $this->inlineCSS = [];
        $this->cssQueue = [];

        return $this;
    }

    public function renderCss(?array $cssQueue = null): string
    {
        // $queue = $cssQueue ?? []; // pakai parameter jika dikirim, kalau tidak pakai internal
        $queue = $cssQueue ?? $this->css; // pakai parameter jika dikirim, kalau tidak pakai internal

        $output = '';

        foreach ($queue as $item) {
            $href = htmlspecialchars($item['href']);
            $attr = $item['attr'] ?? [];

            // Default rel=stylesheet jika tidak ada
            if (!array_key_exists('rel', array_change_key_case($attr, CASE_LOWER))) {
                $attr['rel'] = 'stylesheet';
            }

            // Buat string atribut
            $attrString = '';
            foreach ($attr as $key => $val) {
                if (is_bool($val)) {
                    if ($val) {
                        $attrString .= ' ' . htmlspecialchars($key);
                    }
                } else {
                    $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
                }
            }

            $output .= "<link href=\"{$href}\"{$attrString}>" . PHP_EOL;
            if (strpos($attrString, 'rel="preload"') !== false) {
                $output .= "<noscript><link href=\"{$href}\" rel=\"stylesheet\"></noscript>" . PHP_EOL;
            }
        }

        // Inline CSS
        if (!empty($this->inlineCSS)) {
            $output .= "<style>\n" . implode("\n", $this->inlineCSS) . "\n</style>" . PHP_EOL;
        }

        return $output;
    }

    protected function renderCssTag(
        string $href,
        array $attributes = []
    ): string {
        $html = '<link rel="stylesheet" href="'
            . esc($href, 'attr')
            . '"';

        foreach ($attributes as $name => $value) {
            if ($name === 'href' || $name === 'rel') {
                continue;
            }

            if (! is_string($name) || ! preg_match(
                '/^[a-zA-Z_:][a-zA-Z0-9_.:-]*$/',
                $name
            )) {
                continue;
            }

            if (is_bool($value)) {
                if ($value) {
                    $html .= ' ' . $name;
                }

                continue;
            }

            if (is_scalar($value)) {
                $html .= ' ' . $name . '="'
                    . esc((string) $value, 'attr')
                    . '"';
            }
        }

        return $html . ">\n";
    }

    public function addCssQueue(string|array $css): self
    {
        foreach ((array) $css as $item) {
            if (! in_array($item, $this->cssQueue, true)) {
                $this->cssQueue[] = $item;
            }
        }

        return $this;
    }

    public function removeCssQueue(string $css): self
    {
        $this->cssQueue = array_values(array_filter(
            $this->cssQueue,
            static fn($item) => $item !== $css
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | JavaScript
    |--------------------------------------------------------------------------
    */

    public function addJs(string|array $src, string $folder = '', array|bool $options = [], string $position = 'footer'): self
    {
        $files = is_array($src) ? $src : [$src];

        foreach ($files as $file) {
            $filename     = '';
            $attributes   = [];
            $filePosition = $position;
            $versioned    = true;

            if (is_array($file)) {
                $filename     = $file[0] ?? '';
                $folder       = $file[1] ?? $folder;
                $third        = $file[2] ?? null;
                $filePosition = $file[3] ?? $position;

                if (is_array($third)) {
                    $versioned = $third['versioned'] ?? true;
                    unset($third['versioned']);
                    $attributes = $third;
                } elseif (is_string($third) && str_contains($third, '=')) {
                    preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $third, $matches, PREG_SET_ORDER);
                    foreach ($matches as $match) {
                        $attributes[$match[1]] = $match[2];
                    }
                    preg_match_all('/\b(async|defer|nomodule)\b/', $third, $boolAttrs);
                    foreach ($boolAttrs[1] ?? [] as $boolAttr) {
                        $attributes[$boolAttr] = true;
                    }
                } elseif (is_bool($third)) {
                    $versioned = $third;
                }
            } else {
                $filename = $file;
                if (is_array($options)) {
                    $versioned = $options['versioned'] ?? true;
                    $fileOptions = $options;

                    unset($fileOptions['versioned']);
                    $attributes = $options;
                } elseif (is_bool($options)) {
                    $versioned = $options;
                }
            }

            if (filter_var($filename, FILTER_VALIDATE_URL)) {
                $finalSrc = $filename;
            } else {
                $cleanFile = preg_replace('/\.js$/', '', $filename) . '.js';
                $theme = $this->viewManager->getTheme();
                if ($folder) {
                    if (str_contains($folder, '/')) {
                        $f = explode('/', $folder);
                        if (strtolower($f[0]) === 'plugins') {
                            $filePath = "themes/{$theme}{$this->defaultFolder}/plugins/{$f[1]}/{$cleanFile}";
                        } else {
                            $filePath = "themes/{$theme}/" . trim($folder, '/') . "/{$cleanFile}";
                        }
                    } else {
                        $filePath = rtrim($folder, '/') . "/{$cleanFile}";
                    }
                } else {
                    $filePath = "themes/{$theme}{$this->defaultFolder}/js/{$cleanFile}";
                }

                $fullPath  = FCPATH . $filePath;
                $version   = ($versioned && file_exists($fullPath)) ? filemtime($fullPath) : '';
                $finalSrc  = base_url($filePath) . ($version ? '?v=' . $version : '');
            }

            // Pastikan posisi antrean valid (header/footer)
            $targetPos = in_array($filePosition, ['header', 'footer']) ? $filePosition : 'footer';
            // pp($this->getJsQueue());
            if (!isset($this->jsQueue[$targetPos])) {
                $this->jsQueue[$targetPos] = [];
            }
            // Hindari Duplikat
            $alreadyAdded = in_array($finalSrc, array_column($this->jsQueue[$targetPos], 'src'));
            if (!$alreadyAdded) {
                $this->jsQueue[$targetPos][] = [
                    'src'   => $finalSrc,
                    'attr'  => $attributes,
                    'order' => ++$this->jsCounter,
                ];
            }
            // dd($alreadyAdded);
        }
        return $this;
    }

    public function replaceJs(
        string $oldJs,
        string $newJs,
        string $position = 'footer'
    ): self {
        $this->removeJs($oldJs, $position);

        $this->addJs($newJs, '', [], $position);

        return $this;
    }

    public function removeJs(
        string $js,
        string $position = 'footer'
    ): self {
        $queuePosition = $this->normalizeQueuePosition($position);

        $target = $this->normalizeJsIdentifier($js);

        $this->jsQueue[$queuePosition] = array_values(
            array_filter(
                $this->jsQueue[$queuePosition],
                function (array $item) use ($target): bool {
                    return $this->normalizeJsIdentifier(
                        (string) ($item['src'] ?? '')
                    ) !== $target;
                }
            )
        );

        return $this;
    }

    public function removeAllJs(): self
    {
        $this->js = [
            'head'   => [],
            'footer' => [],
        ];

        $this->jsQueue = [
            'header' => [],
            'footer' => [],
        ];

        $this->inlineJS = [
            'head'   => [],
            'footer' => [],
        ];

        $this->jsCounter = 0;

        return $this;
    }


    public function addInlineJS(
        string $script,
        string $position = 'footer'
    ): self {
        $position = $this->normalizeInlinePosition($position);

        $this->inlineJS[$position] ??= [];

        $this->inlineJS[$position][] = $script;

        return $this;
    }

    /* public function addInlineJS(string|array $script, string $position = 'footer'): self
    {
        $scripts   = is_array($script) ? $script : [$script];
        $targetPos = in_array($position, ['header', 'footer']) ? $position : 'footer';

        // ✅ AMANKAN: Pastikan key array header/footer sudah diinisialisasi
        if (!isset($this->inlineJS[$targetPos]) || !is_array($this->inlineJS[$targetPos])) {
            $this->inlineJS[$targetPos] = [];
        }

        foreach ($scripts as $s) {
            if (!in_array($s, $this->inlineJS[$targetPos], true)) {
                $this->inlineJS[$targetPos][] = $s;
            }
        }

        return $this;
    }
 */
    public function addInlineJsOnce(
        string $key,
        string $js,
        string $position = 'footer'
    ): self {
        $position = $this->normalizeInlinePosition($position);

        $this->inlineJS[$position] ??= [];

        $this->inlineJS[$position][$key] = $js;

        return $this;
    }
    public function renderInlineJS(string $position = 'footer'): string
    {
        $position = $this->normalizeInlinePosition($position);
        $scripts = $this->inlineJS[$position] ?? [];

        if ($scripts === []) {
            return '';
        }

        $output = '';

        foreach ($scripts as $script) {
            if (! is_string($script) || $script === '') {
                continue;
            }

            $output .= "<script>{$script}</script>" . PHP_EOL;
        }

        return $output;
    }

    protected function normalizeInlinePosition(string $position): string
    {
        return in_array(
            strtolower($position),
            ['head', 'header'],
            true
        ) ? 'head' : 'footer';
    }

    public function renderJs(string $position = 'footer'): string
    {

        if (empty($this->jsQueue[$position])) {
            return '';
        }

        // Urut berdasarkan order
        usort($this->jsQueue[$position], fn($a, $b) => $a['order'] <=> $b['order']);

        $output = '';


        foreach ($this->jsQueue[$position] as $item) {
            $src  = htmlspecialchars($item['src']);
            $attr = is_array($item['attr'] ?? null) ? $item['attr'] : [];

            $attrString = '';
            foreach ($attr as $key => $val) {
                if (is_bool($val)) {
                    if ($val) {
                        $attrString .= " $key";
                    }
                } else {
                    $attrString .= sprintf(' %s="%s"', $key, htmlspecialchars($val, ENT_QUOTES));
                }
            }

            $output .= "<script src=\"{$src}\"{$attrString}></script>\n";
        }

        // Inline JS jika ada
        // if (!empty($this->inlineJS[$position])) {
        //     $output .= implode("\n", array_map(
        //         fn ($script) => "<script>{$script}</script>",
        //         $this->inlineJS[$position]
        //     ));
        // }

        return $output;
    }

    public function getJsQueue(): array
    {
        return $this->jsQueue;
    }

    public function addJsQueue(
        string|array $js,
        string $position = 'footer'
    ): self {
        $position = $this->normalizeQueuePosition($position);

        foreach ((array) $js as $item) {
            if (! in_array($item, $this->jsQueue[$position], true)) {
                $this->jsQueue[$position][] = $item;
            }
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    */

    public function addPlugin(string|array $plugins): self
    {
        foreach ((array) $plugins as $plugin) {
            if (! in_array($plugin, $this->plugins, true)) {
                $this->plugins[] = $plugin;
            }
        }

        return $this;
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function removePlugin(string $plugin): self
    {
        $this->plugins = array_values(array_filter(
            $this->plugins,
            static fn($item) => $item !== $plugin
        ));

        return $this;
    }

    public function clearPlugins(): self
    {
        $this->plugins = [];

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function enableTooltip(): self
    {
        $this->addInlineJS(
            "document.querySelectorAll('[data-bs-toggle=\"tooltip\"]').forEach(
                function (element) {
                    new bootstrap.Tooltip(element);
                }
            );"
        );

        return $this;
    }

    protected function normalizeJsPosition(string $position): string
    {
        return in_array($position, ['head', 'header'], true)
            ? 'head'
            : 'footer';
    }

    protected function normalizeQueuePosition(string $position): string
    {
        return in_array($position, ['head', 'header'], true)
            ? 'header'
            : 'footer';
    }

    public function getCss(): array
    {
        return $this->css;
    }

    public function getInlineCSS(): array
    {
        return $this->inlineCSS;
    }

    public function getJs(): array
    {
        return $this->js;
    }

    public function getInlineJS(): array
    {
        return $this->inlineJS;
    }

    protected function normalizeJsIdentifier(string $value): string
    {
        $path = parse_url($value, PHP_URL_PATH);

        $identifier = basename(
            is_string($path) && $path !== '' ? $path : $value
        );

        return preg_replace('/\.js$/i', '', $identifier) ?? $identifier;
    }
}
