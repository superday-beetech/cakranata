<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

class ThemeBreadcrumbManager
{
    protected array $breadcrumbs = [];

    protected bool $breadcrumbEnabled = true;

    protected array $breadcrumbDefaults = [
        'wrapper'      => '%s',
        'list_tag'     => 'ol',
        'list_class'   => 'breadcrumb',
        'item_class'   => 'breadcrumb-item',
        'active_class' => 'active',
        'separator'    => '',
    ];

    public function breadcrumb(bool $enabled = false): self
    {
        $this->breadcrumbEnabled = $enabled;

        return $this;
    }

    public function isBreadcrumbEnabled(): bool
    {
        return $this->breadcrumbEnabled;
    }

    /*   public function addCrumb(array $breadcrumbs): self
    {
        if ($this->isAssoc($breadcrumbs)) {
            foreach ($breadcrumbs as $label => $url) {
                $this->breadcrumbs[] = [
                    'label' => (string) $label,
                    'url'   => $this->normalizeUrl($url, (string) $label),
                ];
            }

            return $this;
        }

        foreach ($breadcrumbs as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['label'])) {
                $label = (string) $item['label'];

                $this->breadcrumbs[] = [
                    'label' => $label,
                    'url'   => $this->normalizeUrl($item['url'] ?? null, $label),
                ];

                continue;
            }

            foreach ($item as $label => $url) {
                $label = (string) $label;

                $this->breadcrumbs[] = [
                    'label' => $label,
                    'url'   => $this->normalizeUrl($url, $label),
                ];
            }
        }

        return $this;
    }
 */

    public function addCrumb(
        string|array $breadcrumbs,
        ?string $url = null
    ): self {
        // Mendukung addCrumb('Dashboard', '/dashboard').
        if (is_string($breadcrumbs)) {
            $label = $breadcrumbs;

            $this->breadcrumbs[] = [
                'label' => $label,
                'url'   => $this->normalizeUrl($url, $label),
            ];

            return $this;
        }

        if ($this->isAssoc($breadcrumbs)) {
            foreach ($breadcrumbs as $label => $itemUrl) {
                $label = (string) $label;

                $this->breadcrumbs[] = [
                    'label' => $label,
                    'url'   => $this->normalizeUrl($itemUrl, $label),
                ];
            }

            return $this;
        }

        foreach ($breadcrumbs as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['label'])) {
                $label = (string) $item['label'];

                // URL null yang eksplisit berarti item tanpa tautan.
                $itemUrl = array_key_exists('url', $item)
                    && $item['url'] === null
                    ? null
                    : $this->normalizeUrl(
                        $item['url'] ?? null,
                        $label
                    );

                $this->breadcrumbs[] = [
                    'label' => $label,
                    'url'   => $itemUrl,
                ];

                continue;
            }

            foreach ($item as $label => $itemUrl) {
                $label = (string) $label;

                $this->breadcrumbs[] = [
                    'label' => $label,
                    'url'   => $this->normalizeUrl($itemUrl, $label),
                ];
            }
        }

        return $this;
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

        $moduleName = ucfirst($this->kebabToCamel((string) $module));
        $class      = "\\Modules\\{$moduleName}\\Routers\\DynamicRouter";

        // Pertahankan penggunaan DynamicRouter seperti implementasi lama.
        $dynamicRouter = new $class();

        $controllerClass = ucfirst((string) $controller);

        if (! str_ends_with($controllerClass, 'Controller')) {
            $controllerClass .= 'Controller';
        }

        if (
            ! in_array(
                $controllerClass,
                $dynamicRouter->getAllowedControllers() ?? [],
                true
            )
        ) {
            $controllerClass = $dynamicRouter->getDefaultController();
            $method = $segments[2] ?? 'index';
        }

        if (! $moduleName) {
            $breadcrumbs[] = [
                'label' => 'Beranda',
                'url'   => '/',
            ];
        }

        $accumulated = '';

        if ($module !== '' && strtolower((string) $module) !== 'home') {
            $accumulated = $module;

            $breadcrumbs[] = [
                'label' => ucwords(str_replace(['-', '_'], ' ', (string) $module)),
                'url'   => $accumulated,
            ];
        }

        if (
            $controller !== ''
            && strtolower((string) $controller) !== strtolower((string) $module)
        ) {
            $accumulated .= '/' . $controller;

            $breadcrumbs[] = [
                'label' => ucwords(str_replace(['-', '_'], ' ', (string) $controller)),
                'url'   => $accumulated,
            ];
        }

        if ($method !== '' && $method !== 'index') {
            $accumulated .= '/' . $method;

            $breadcrumbs[] = [
                'label' => ucwords(str_replace(['-', '_'], ' ', (string) $method)),
                'url'   => $accumulated,
            ];
        }

        if (! empty($breadcrumbs)) {
            $breadcrumbs[array_key_last($breadcrumbs)]['url'] = null;
        }

        return $this->addCrumb($breadcrumbs);
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function clearBreadcrumbs(): self
    {
        $this->breadcrumbs = [];

        return $this;
    }

    public function breadcrumbHTML(array $options = []): string
    {
        if (($options['enabled'] ?? null) === false) {
            return '';
        }

        if (empty($this->breadcrumbs)) {
            return '';
        }

        if (! $this->isBreadcrumbEnabled()) {
            return '';
        }

        $config = array_merge($this->breadcrumbDefaults, $options);

        $listTag = $config['list_tag'];
        $html = "<{$listTag} class=\"" . esc($config['list_class']) . "\">";

        $lastIndex = count($this->breadcrumbs) - 1;

        foreach ($this->breadcrumbs as $i => $item) {
            $label = esc($item['label'] ?? '');
            $url   = $item['url'] ?? null;

            $itemClass = $config['item_class'];

            if ($i === $lastIndex) {
                $itemClass .= ' ' . $config['active_class'];
            }

            $html .= '<li class="' . esc(trim($itemClass)) . '">';

            if ($url && $i !== $lastIndex) {
                $html .= '<a href="' . esc($url) . '">' . $label . '</a>';
            } else {
                $html .= $label;
            }

            $html .= '</li>';

            if ($config['separator'] !== '' && $i !== $lastIndex) {
                $html .= '<li class="breadcrumb-separator">'
                    . $config['separator']
                    . '</li>';
            }
        }

        $html .= "</{$listTag}>";

        return sprintf($config['wrapper'], $html);
    }

    private function isAssoc(array $array): bool
    {
        return ! array_is_list($array);
    }

    private function normalizeUrl(mixed $url, string $label): string
    {
        if (empty($url) && $label !== '') {
            $slug = strtolower(
                trim(preg_replace('/\s+/', '-', $label) ?? $label)
            );

            return site_url($slug);
        }

        return ! empty($url)
            ? site_url(trim((string) $url, '/'))
            : site_url();
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
}
