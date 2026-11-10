<?php

namespace App\Libraries;

class Theme
{
    protected string $theme    = 'cakra';
    protected string $layout   = 'default';
    protected string $baseView = 'baseView';
    protected string $view;
    protected ?string $title = null;
    protected array $meta    = [];
    protected array $css     = [];
    protected array $js      = [
        'head'   => [],
        'footer' => [],
    ];
    protected array $jsQueue = [
        'header' => [],
        'footer' => [],
    ];
    protected array $inlineCSS   = [];
    protected array $cssQueue = [];
    protected array $inlineJS = [
        'head'   => [],
        'footer' => [],
    ];
    protected array $footerJS          = [];
    protected array $modals          = [];
    protected int  $jsCounter          = 0;
    protected array $plugins           = [];
    protected array $data              = [];
    protected array $sections          = [];
    protected array $protectedPartials = [];
    protected array $removedPartials   = [];
    protected array $partials          = [];
    protected array $breadcrumbs       = [];
    protected bool $breadcrumbEnabled  = true;
    protected array $classes           = [];
    protected ?string $forcedTheme     = null;
    protected ?string $forcedLayout    = null;
    protected ?string $forcedBaseView  = null;
    public string $defaultTheme        = 'viho';
    public string $defaultFolder       = '';
    protected array $modalShells        = [];
    protected array $breadcrumbDefaults = [
        'wrapper'      => '%s',
        'list_tag'     => 'ol',
        'list_class'   => 'breadcrumb',
        'item_class'   => 'breadcrumb-item',
        'active_class' => 'active',
        'separator'    => '',
    ];

    public function __construct()
    {

        // $config = config(ThemeConfig::class);
        $this->theme = $this->defaultTheme;
        $this->jsQueue['header'] = $this->jsQueue['header'] ?? [];
        $this->jsQueue['footer'] = $this->jsQueue['footer'] ?? [];
    }

    public function setTheme(string $theme): self
    {
        $this->theme       = $theme;
        $this->forcedTheme = $theme;

        return $this;
    }
    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getModule($folder = ''): string
    {
        $module = $this->detectModule();
        $path   = ("modules/{$module}/Views/{$folder}/");

        return $path;
    }

    // URL ke folder asset theme saat ini
    public function assetUrl(string $path = ''): string
    {
        $basePath = 'themes/' . $this->theme . '/';

        return ($basePath . ltrim($path, '/'));
    }
    // URL ke folder asset theme saat ini
    public function assetWithUrl(string $path = ''): string
    {
        $basePath = 'themes/' . $this->theme . $this->defaultFolder . '/';

        return base_url($basePath . ltrim($path, '/'));
    }

    // URL ke folder asset theme saat ini
    public function getAsset(string $path = ''): string
    {
        $basePath = 'themes/' . $this->theme . $this->defaultFolder . '/';

        return base_url($basePath . ltrim($path, '/'));
    }

    // Path fisik ke file asset di theme
    public function assetPath(string $path = ''): string
    {
        $basePath = FCPATH . 'themes/' . $this->theme . $this->defaultFolder . '/';

        return $basePath . ltrim($path, '/');
    }

    // URL asset theme dengan versi (timestamp) jika ada
    public function asset(string $path, bool $versioned = true): string
    {
        $url  = $this->assetUrl($path);
        $path = $this->assetPath($path);

        $version = ($versioned && file_exists($path)) ? '?v=' . filemtime($path) : '';

        return $url . $version;
    }

    // URL ke asset dalam modul tertentu
    public function moduleAssetUrl(string $module, string $path = ''): string
    {
        return base_url("modules/{$module}{$this->defaultFolder}/" . ltrim($path, '/'));
    }

    // Path fisik ke asset dalam modul
    public function moduleAssetPath(string $module, string $path = ''): string
    {
        return FCPATH . "modules/{$module}{$this->defaultFolder}/" . ltrim($path, '/');
    }

    // URL asset modul dengan versi
    public function moduleAsset(string $module, string $path = '', bool $versioned = true): string
    {
        $url      = $this->moduleAssetUrl($module, $path);
        $filePath = $this->moduleAssetPath($module, $path);

        $version = ($versioned && file_exists($filePath)) ? '?v=' . filemtime($filePath) : '';

        return $url . $version;
    }

    public function setLayout(string $layout): self
    {
        $this->layout       = $layout;
        $this->forcedLayout = $layout;

        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function setBaseView(string $baseView): self
    {
        $this->baseView       = $baseView;
        $this->forcedBaseView = $baseView;

        return $this;
    }

    public function getBaseView(): string
    {
        return $this->baseView;
    }

    public function wasThemeOverridden(): bool
    {
        return $this->forcedTheme !== null;
    }

    public function wasLayoutOverridden(): bool
    {
        return $this->forcedLayout !== null;
    }

    public function wasBaseViewOverridden(): bool
    {
        return $this->forcedBaseView !== null;
    }

    public function resetThemeOverrides(): self
    {
        $this->forcedTheme  = null;
        $this->forcedLayout = null;

        return $this;
    }

    public function setView(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getUrlTheme(): string
    {
        return base_url("themes/{$this->theme}");
    }

    public function addMeta($name, string $content = '', string $type = 'name', array $extraAttributes = []): self
    {
        if (is_array($name) && array_keys($name) === range(0, count($name) - 1)) {
            foreach ($name as $meta) {
                $this->addMeta($meta); // Rekursif
            }

            return $this;
        }

        // Jika array satu item
        if (is_array($name)) {
            $meta = $name;
            $tag  = $meta['tag'] ?? 'meta';

            if ($tag === 'meta') {
                if (isset($meta['charset'])) {
                    $key = 'charset:' . $meta['charset'];
                } elseif (isset($meta['name'])) {
                    $key = 'name:' . $meta['name'];
                } elseif (isset($meta['property'])) {
                    $key = 'property:' . $meta['property'];
                } elseif (isset($meta['http-equiv'])) {
                    $key = 'http-equiv:' . $meta['http-equiv'];
                } else {
                    $key = sha1(json_encode($meta));
                }
            } elseif ($tag === 'link') {
                $rel  = $meta['rel']  ?? 'link';
                $href = $meta['href'] ?? '';
                $key  = 'rel:' . $rel . '|href:' . $href;
            } else {
                $key = sha1(json_encode($meta));
            }

            $this->meta[$key] = $meta;

            return $this;
        }

        // Jika string (mode klasik)
        $type = strtolower($type);
        if ($type === 'charset') {
            $key              = 'charset:' . $name;
            $this->meta[$key] = [
                'tag'     => 'meta',
                'charset' => $name,
            ];
        } elseif ($type === 'link') {
            $key              = 'rel:' . $name . '|href:' . $content;
            $this->meta[$key] = array_merge([
                'tag'  => 'link',
                'rel'  => $name,
                'href' => $content,
            ], $extraAttributes);
        } else {
            $key              = $type . ':' . $name;
            $this->meta[$key] = array_merge([
                'tag'     => 'meta',
                $type     => $name,
                'content' => $content,
            ], $extraAttributes);
        }

        return $this;
    }

    public function setMeta(string $name, string $content): self
    {
        $this->meta[$name] = $content;

        return $this;
    }

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
                    unset($options['versioned']);
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

            if ($folder) {
                if (str_contains($folder, '/')) {
                    $f = explode('/', $folder);
                    if (strtolower($f[0]) === 'plugins') {
                        $filePath = 'themes/' . $this->theme . $this->defaultFolder . '/plugins/' . $f[1] . '/' . $fileName;
                    } elseif (strtolower($f[0]) === 'vendor') {
                        $filePath = 'themes/' . $this->theme . $this->defaultFolder . '/vendor/' . $f[1] . '/' . $fileName;
                    } else {
                        $filePath = 'themes/' . $this->theme . '/' . trim($folder, '/') . '/' . $fileName;
                    }
                } else {
                    $filePath = rtrim($folder, '/') . '/' . $fileName;
                }
            } else {
                $filePath = 'themes/' . $this->theme . $this->defaultFolder . '/css/' . $fileName;
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
        $this->css       = [];
        $this->inlineCSS = [];

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
                    unset($options['versioned']);
                    $attributes = $options;
                } elseif (is_bool($options)) {
                    $versioned = $options;
                }
            }

            if (filter_var($filename, FILTER_VALIDATE_URL)) {
                $finalSrc = $filename;
            } else {
                $cleanFile = preg_replace('/\.js$/', '', $filename) . '.js';

                if ($folder) {
                    if (str_contains($folder, '/')) {
                        $f = explode('/', $folder);
                        if (strtolower($f[0]) === 'plugins') {
                            $filePath = "themes/{$this->theme}{$this->defaultFolder}/plugins/{$f[1]}/{$cleanFile}";
                        } else {
                            $filePath = "themes/{$this->theme}/" . trim($folder, '/') . "/{$cleanFile}";
                        }
                    } else {
                        $filePath = rtrim($folder, '/') . "/{$cleanFile}";
                    }
                } else {
                    $filePath = "themes/{$this->theme}{$this->defaultFolder}/js/{$cleanFile}";
                }

                $fullPath  = FCPATH . $filePath;
                $version   = ($versioned && file_exists($fullPath)) ? filemtime($fullPath) : '';
                $finalSrc  = base_url($filePath) . ($version ? '?v=' . $version : '');
            }

            // Pastikan posisi antrean valid (header/footer)
            $targetPos = in_array($filePosition, ['header', 'footer']) ? $filePosition : 'footer';
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
        }

        return $this;
    }


    public function replaceJs(string $oldFilename, string $newFilename, string $folder = '', array|bool $options = []): self
    {
        $this->removeJs($oldFilename);

        return $this->addJS($newFilename, $folder, $options);
    }
    /**
     * Hapus semua file CSS dan inline CSS
     */
    public function removeAllJs(): self
    {
        $position                  = 'footer';
        $this->jsQueue             = [];
        $this->inlineJS[$position] = [];

        return $this;
    }

    public function removeJs(string $targetSrc, string $position = 'footer'): self
    {
        if (!isset($this->jsQueue[$position])) {
            return $this;
        }

        $cleanTarget = basename(preg_replace('/\.js$/', '', $targetSrc)) . '.js';

        $this->jsQueue[$position] = array_filter(
            $this->jsQueue[$position],
            function ($item) use ($cleanTarget) {
                return basename(strtok($item['src'], '?')) !== $cleanTarget;
            }
        );

        return $this;
    }

    public function enableTooltip(): void
    {
        // Inisialisasi tooltip
        $this->addInlineJs(<<<'JS'
            document.addEventListener("DOMContentLoaded", function () {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function (el) {
                new bootstrap.Tooltip(el);
                });
            });
        JS, 'footer');
    }
    public function addInlineJS(string|array $script, string $position = 'footer'): self
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

    public function addInlineJsOnce(string|array $script, string $position = 'footer'): self
    {
        $scripts = is_array($script) ? $script : [$script];

        if (!isset($this->inlineJS[$position])) {
            $this->inlineJS[$position] = [];
        }

        foreach ($scripts as $s) {
            if (!in_array($s, $this->inlineJS[$position])) {
                $this->inlineJS[$position][] = $s;
            }
        }

        return $this;
    }

    public function renderInlineJS(string $position = 'footer'): string
    {
        if (empty($this->inlineJS[$position])) {
            return '';
        }

        return implode("\n", array_map(fn($script) => "<script>{$script}</script>", $this->inlineJS[$position]));
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

    public function addPlugin(string $name): self
    {
        $this->plugins[] = $name;

        return $this;
    }

    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function getData(?array $key = null)
    {
        return $key !== null ? $this->data[$key] ?? null : $this->data;
    }

    public function setVar(string $key, $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function getVar(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function addClass(string $section, string $class): self
    {
        $this->classes[$section] ??= [];
        if (!in_array($class, $this->classes[$section])) {
            $this->classes[$section][] = $class;
        }

        return $this;
    }

    public function removeClass(string $section, string $class): self
    {
        if (isset($this->classes[$section])) {
            $index = array_search($class, $this->classes[$section]);
            if ($index !== false) {
                unset($this->classes[$section][$index]);
                // Reindex array untuk mencegah lubang
                $this->classes[$section] = array_values($this->classes[$section]);
            }
        }

        return $this;
    }

    public function replaceClass(string $section, string $oldClass, string $newClass): self
    {
        if (isset($this->classes[$section])) {
            $index = array_search($oldClass, $this->classes[$section]);
            if ($index !== false) {
                $this->classes[$section][$index] = $newClass;
            } else {
                // Kalau class lama tidak ditemukan, tambahkan class baru (opsional)
                $this->addClass($section, $newClass);
            }
        } else {
            // Kalau section belum ada, langsung tambahkan class baru
            $this->addClass($section, $newClass);
        }

        return $this;
    }

    public function clearClasses(string $section): self
    {
        if (isset($this->classes[$section])) {
            unset($this->classes[$section]);
        }

        return $this;
    }
    /**
     * Ambil class yang sudah dikumpulkan sebagai string
     */
    public function getClass(string $section): string
    {
        return isset($this->classes[$section])
            ? implode(' ', $this->classes[$section])
            : '';
    }

    public function addPartialView(string $name, array $data = []): self
    {
        if (!empty($data)) {
            // Render sekarang di server, simpan HTML-nya
            $viewPath = $this->locateView("themes/{$this->theme}/partials/{$name}");
            if (is_file(ROOTPATH . $viewPath . '.php')) {
                $data['urlTheme']      = $this->getUrlTheme();
                $this->data[$name]     = view($viewPath, $data);
                $this->partials[$name] = true; // flag aktif
            } else {
                $this->data[$name]     = "<!-- Partial {$name} not found -->";
                $this->partials[$name] = false;
            }
        } else {
            // Tidak render sekarang; hanya flag supaya layout nanti yang render
            $this->partials[$name] = true;
        }

        return $this;
    }

    public function removePartial(string $name): self
    {
        if (in_array($name, $this->protectedPartials, true)) {
            // Diblokir penghapusan
            return $this;
        }
        $this->removedPartials[] = $name;
        unset($this->data[$name]);

        return $this;
    }

    public function wasPartialRemoved(string $name): bool
    {
        return in_array($name, $this->removedPartials, true);
    }

    public function addPartial(string $name, array $data = [], bool $enabled = true, $location = 'partials'): self
    {
        $viewPath              = "themes/{$this->theme}/{$location}/{$name}";
        $this->partials[$name] = [
            'view'    => $viewPath,
            'data'    => $data,
            'enabled' => $enabled,
        ];

        return $this;
    }

    public function hasPartial(string $name): bool
    {
        return !empty($this->partials[$name]['enabled']);
    }

    public function getPartialData(string $name): array
    {
        return $this->partials[$name]['data'] ?? [];
    }

    public function getPartialView(string $name): ?string
    {
        return $this->partials[$name]['view'] ?? null;
    }

    public function renderPartial(string $name): ?string
    {
        if (!$this->hasPartial($name)) {
            return null;
        }

        $view = $this->getPartialView($name);

        if ($view && view_exists($view)) {
            return view($view, $this->getPartialData($name));
        }

        return "<!-- Partial '{$name}' view not found -->";
    }

    public function getPartial(string $name): ?string
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        return null;
    }

    public function protectPartials(array $names): self
    {
        $this->protectedPartials = array_unique(array_merge($this->protectedPartials, $names));

        return $this;
    }

    public function setPartial(string $section, bool $enabled = true)
    {
        if (isset($this->partials[$section])) {
            $this->partials[$section]['enabled'] = $enabled;
        }

        return $this;
    }


    public function render(string $view, array $params = []): string
    {
        if (empty($this->breadcrumbs)) {
            $this->addCrumbFromDynamic();
        }
        $defaultData = [
            'theme'   => $this->theme,
            'layout'  => $this->layout,
            'judul'   => ucwords(str_replace('-', ' ', segment(2, ''))) ?? '',
            'title'   => $this->title ?? ucfirst(segment(2, '')) ?? '',
            'plugins' => $this->plugins,
        ];

        foreach ($this->classes as $section => $classList) {
            $key             = $section . 'Class';
            $defaultData[$key] = implode(' ', $classList);
        }

        $defaultData = array_filter($defaultData, function ($val) {
            if (is_array($val)) {
                return !empty($val); // array kosong akan dibuang
            }

            return $val !== null && $val !== '';
        });

        $this->data = array_merge($this->data, $defaultData, $params);

        $layoutPath = $this->locateView("layouts/{$this->layout}");

        $this->data['_layout_override'] = $layoutPath;

        $this->data['_content_view'] = $this->locateView($view);

        $return = $this->locateView($this->baseView);
        $returnView = view($return, $this->data);
        // print_r($returnView);
        // exit;

        return $returnView;
    }

    protected function locateView(string $view): string
    {
        $module = $this->detectModule();
        $view   = trim(str_replace('\\', '/', $view), '/');

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

            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen(ROOTPATH)));

            $relativePath = preg_replace('/\.php$/i', '', $relativePath);

            if (str_starts_with($relativePath, 'Modules/')) {
                return str_replace('/', '\\', $relativePath);
            }

            // View aplikasi: gunakan path relatif terhadap app/Views.
            if (str_starts_with($relativePath, 'app/Views/')) {
                return substr($relativePath, strlen('app/Views/'));
            }

            return $relativePath;
        }
        // Pertahankan fallback Theme lama.
        return "themes/{$this->theme}/{$view}";
    }

    protected function detectModule(): string
    {
        $router     = service('router');
        $controller = trim((string) ($router?->controllerName() ?? ''), '\\');

        // Hilangkan method jika formatnya Class::method
        $controller = explode('::', $controller, 2)[0];

        if (preg_match('~^Modules\\\\([a-zA-Z0-9_]+)(?:\\\\|$)~', $controller, $matches)) {
            return $matches[1];
        }

        // Debug sementara
        // log_message('debug', 'detectModule: controller=' . $controller);
        //
        return 'App';
    }

    public function breadcrumb(bool $enabled = false): self
    {
        $this->breadcrumbEnabled = $enabled;

        return $this;
    }

    public function isBreadcrumbEnabled(): bool
    {
        return $this->breadcrumbEnabled;
    }

    public function addCrumb(array $breadcrumbs): self
    {
        if ($this->isAssoc($breadcrumbs)) {
            foreach ($breadcrumbs as $label => $url) {
                $url                 = $this->normalizeUrl($url, $label);
                $this->breadcrumbs[] = ['label' => $label, 'url' => $url];
            }
        } else {
            foreach ($breadcrumbs as $item) {
                if (isset($item['label'])) {
                    $label               = $item['label'];
                    $url                 = $this->normalizeUrl($item['url'] ?? null, $label);
                    $this->breadcrumbs[] = ['label' => $label, 'url' => $url];
                } else {
                    foreach ($item as $label => $url) {
                        $url                 = $this->normalizeUrl($url, $label);
                        $this->breadcrumbs[] = ['label' => $label, 'url' => $url];
                    }
                }
            }
        }

        return $this;
    }

    public function addModalShell(string $id, array $options = [])
    {
        $this->modalShells[] = array_merge(
            [
                'id'         => $id,
                'header'     => 'Default',
                'size'       => 'lg',
                'form'       => false,
                'action'     => '',
                'method'     => 'post',
                'form_id'    => 'form_' . $id,
                'submit'     => false,
                'submitText' => 'Simpan',
                'keyboard'   => false,
                'backdrop'   => 'static',
                'dynamic'    => true,
            ],
            $options
        );
    }
    public function renderModals(): string
    {
        $output = '';
        foreach ($this->modals as $modal) {
            $viewPath = $this->locateView($modal['view']);
            if (is_file(APPPATH . str_replace('\\', '/', $viewPath) . '.php')) {
                $output .= view($viewPath, $modal['data'] ?? []);
            }
        }

        return $output;
    }

    public function renderModalShells(): string
    {
        $module     = $this->detectModule();
        $output     = '';
        $hasDynamic = false;

        if (!empty($this->modalShells)) {
            foreach ($this->modalShells as $shell) {
                $viewPath = "Modules\\{$module}\\Views\\partials\\modal_shell";

                if (!is_file(ROOTPATH . "Modules/{$module}/Views/partials/modal_shell.php")) {
                    $viewPath = "themes/{$this->theme}/partials/modal_shell"; // fallback ke theme global
                }

                if (!empty($shell['dynamic'])) {
                    $hasDynamic = true;
                }

                $output .= view($viewPath, $shell);
            }
        }

        // Inject JS hanya jika ada modal dynamic
        // $this->addInlineJS("console.log('✅ openModalWithContent injected');");
        if ($hasDynamic) {
            $this->addJs('custom_js');
        }

        return $output;
    }

    public function renderModalShellsInject(): string
    {
        $output = '';

        foreach ($this->modalShells as $shell) {
            $id   = $shell['id'];
            $size = $shell['size'];

            $sizeClass = match ($size) {
                'sm'         => 'modal-sm',
                'lg'         => 'modal-lg',
                'xl'         => 'modal-xl',
                'fullscreen' => 'modal-fullscreen',
                default      => '',
            };

            $output .= <<<HTML
        <div class="modal fade" id="{$id}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog {$sizeClass}">
            <div class="modal-content">
                <div class="modal-body" id="{$id}Content">
                    <div class="text-muted text-center py-4">Memuat konten Inject...</div>
                </div>
            </div>
        </div>
        </div>
        HTML;
        }

        return $output;
    }

    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function normalizeUrl(?string $url, string $label): string
    {
        if (empty($url) && $label !== '') {
            $slug = strtolower(trim(preg_replace('/\s+/', '-', $label)));

            return site_url($slug);
        }

        return !empty($url) ? site_url(trim($url, '/')) : site_url();
    }

    public function addCrumbFromDynamic(array $segments = []): self
    {
        if (empty($segments)) {
            $segments = service('uri')->getSegments();
        }

        $breadcrumbs = [];

        $module     = $segments[0] ?? '';
        $controller = $segments[1] ?? '';
        $method     = $segments[2] ?? 'index';

        $moduleName = ucfirst($this->kebabToCamel($module));
        $class      = "\\Modules\\{$moduleName}\\DynamicRouter";
        $classFront = [];
        // $classFront = '\\App\\Controllers\\FrontDynamicRouter';

        $dynamicRouter =  new $class();
        // $dynamicRouter = class_exists($class) ? new $class() : new $classFront();

        $controllerClass = ucfirst($controller);
        if (!str_ends_with($controllerClass, 'Controller')) {
            $controllerClass .= 'Controller';
        }

        if ($dynamicRouter && !in_array($controllerClass, $dynamicRouter->getAllowedControllers() ?? [])) {
            $controllerClass = $dynamicRouter->getDefaultController();
            $method          = $segments[2] ?? 'index';
        }

        if (!$moduleName) {
            $breadcrumbs[] = [
                'label' => 'Beranda',
                'url'   => '/',
            ];
        }

        $accumulated = '';

        if ($module && strtolower($module) !== 'home') {
            $accumulated   = $module;
            $breadcrumbs[] = [
                'label' => ucwords(str_replace(['-', '_'], ' ', $module)),
                'url'   => $accumulated,
            ];
        }

        if (!empty($controller) && strtolower($controller) !== strtolower($module)) {
            $accumulated .= '/' . $controller;
            $breadcrumbs[] = [
                'label' => ucwords(str_replace(['-', '_'], ' ', $controller)),
                'url'   => $accumulated,
            ];
        }

        if (!empty($method) && $method !== 'index') {
            $accumulated .= '/' . $method;
            $breadcrumbs[] = [
                'label' => ucwords(str_replace(['-', '_'], ' ', $method)),
                'url'   => $accumulated,
            ];
        }

        if (!empty($breadcrumbs)) {
            $breadcrumbs[array_key_last($breadcrumbs)]['url'] = null;
        }

        return $this->addCrumb($breadcrumbs);
    }

    protected function kebabToCamel(string $string): string
    {
        return lcfirst(
            str_replace(
                ' ',
                '',
                ucwords(str_replace('-', '', $string))
            )
        );
    }
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function breadcrumbHTML(array $options = []): string
    {
        if (array_key_exists('enabled', $options) && $options['enabled'] === false) {
            return '';
        }

        if (empty($this->breadcrumbs)) {
            return '';
        }

        if (!$this->isBreadcrumbEnabled()) {
            return '';
        }

        if (empty($this->breadcrumbs)) {
            $this->addCrumbFromDynamic(); // Auto generate jika belum ada
        }

        // Gabungkan opsi
        $config = array_merge($this->breadcrumbDefaults, $options);

        $listTag = $listTag = isset($config['list_tag']) ? $config['list_tag'] : 'ol';
        $html    = "<{$listTag} class=\"{$config['list_class']}\">";

        $lastIndex = count($this->breadcrumbs) - 1;

        foreach ($this->breadcrumbs as $i => $item) {
            $label = esc($item['label'] ?? '');
            $url   = $item['url'] ?? null;

            $itemClass = $config['item_class'];
            if ($i === $lastIndex) {
                $itemClass .= ' ' . $config['active_class'];
            }

            $html .= '<li class="' . trim($itemClass) . '">';

            if ($url && $i !== $lastIndex) {
                $html .= '<a href="' . esc($url) . '">' . $label . '</a>';
            } else {
                $html .= $label;
            }

            $html .= '</li>';

            if ($config['separator'] && $i !== $lastIndex) {
                $html .= '<li class="breadcrumb-separator">' . $config['separator'] . '</li>';
            }
        }

        $html .= "</{$listTag}>";

        return sprintf($config['wrapper'], $html);
    }

    public function metaHTML(array $options = []): string
    {
        if (empty($this->meta)) {
            return '';
        }

        $wrapper = $options['wrapper'] ?? '%s';
        $html    = '';

        foreach ($this->meta as $meta) {
            $tag = $meta['tag'] ?? 'meta';
            // Deteksi charset dengan key khusus
            if (isset($meta['charset'])) {
                $html .= '<meta charset="' . esc($meta['charset']) . '">' . PHP_EOL;

                continue;
            }

            if ($tag === 'link') {
                $html .= '<link';
            } else {
                $html .= '<meta';
            }

            foreach ($meta as $key => $value) {
                if ($key === 'tag') {
                    continue;
                }
                $html .= ' ' . esc($key) . '="' . esc($value) . '"';
            }

            $html .= '>' . PHP_EOL;
        }

        return sprintf($wrapper, $html);
    }
}
