<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

class ThemePartialManager
{
    protected array $data = [];

    protected array $partials = [];

    protected array $protectedPartials = [];

    protected array $removedPartials = [];

    /**
     * Mendaftarkan partial yang langsung dirender menjadi HTML.
     */
    public function addPartialView(
        string $name,
        array $data = [],
        string $theme = 'viho',
        string $defaultFolder = '',
        ?callable $locateView = null,
        ?callable $getUrlTheme = null
    ): self {
        $this->clearRemovedStatus($name);

        if (empty($data)) {
            $this->partials[$name] = true;

            return $this;
        }

        $view = "themes/{$theme}/partials/{$name}";

        if ($locateView !== null) {
            $view = $locateView($view);
        }

        $filePath = ROOTPATH
            . str_replace('\\', '/', $view)
            . '.php';

        if (! is_file($filePath)) {
            $this->data[$name] = "<!-- Partial {$name} not found -->";
            $this->partials[$name] = false;

            return $this;
        }

        $data['urlTheme'] = $getUrlTheme !== null
            ? $getUrlTheme()
            : base_url("themes/{$theme}{$defaultFolder}");

        $this->data[$name] = view($view, $data);
        $this->partials[$name] = true;

        return $this;
    }

    /**
     * Mendaftarkan partial yang dirender saat diperlukan.
     */
    public function addPartial(
        string $name,
        array $data = [],
        bool $enabled = true,
        string $location = 'partials',
        string $theme = 'viho'
    ): self {
        $this->clearRemovedStatus($name);

        $this->partials[$name] = [
            'view'    => "themes/{$theme}/{$location}/{$name}",
            'data'    => $data,
            'enabled' => $enabled,
        ];

        return $this;
    }

    public function removePartial(string $name): self
    {
        if (in_array($name, $this->protectedPartials, true)) {
            return $this;
        }

        unset($this->partials[$name], $this->data[$name]);

        if (! in_array($name, $this->removedPartials, true)) {
            $this->removedPartials[] = $name;
        }

        return $this;
    }

    public function wasPartialRemoved(string $name): bool
    {
        return in_array($name, $this->removedPartials, true);
    }

    public function hasPartial(string $name): bool
    {
        if (! array_key_exists($name, $this->partials)) {
            return false;
        }

        $partial = $this->partials[$name];

        if (is_bool($partial)) {
            return $partial;
        }

        return is_array($partial)
            && ($partial['enabled'] ?? false) === true;
    }

    public function getPartialData(string $name): array
    {
        $partial = $this->partials[$name] ?? null;

        return is_array($partial)
            ? ($partial['data'] ?? [])
            : [];
    }

    public function getPartialView(string $name): ?string
    {
        $partial = $this->partials[$name] ?? null;

        return is_array($partial) && isset($partial['view'])
            ? $partial['view']
            : null;
    }

    /**
     * Merender partial sesuai jenis registrasinya.
     */
    public function renderPartial(string $name): ?string
    {
        if (! $this->hasPartial($name)) {
            return null;
        }

        $partial = $this->partials[$name];

        // PartialView menyimpan HTML hasil render sebelumnya.
        if (is_bool($partial)) {
            return $this->getPartial($name);
        }

        $view = $this->getPartialView($name);

        if ($view === null || ! view_exists($view)) {
            return "<!-- Partial '{$name}' view not found -->";
        }

        return view($view, $this->getPartialData($name));
    }

    public function getPartial(string $name): ?string
    {
        $value = $this->data[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function protectPartials(array $names): self
    {
        $this->protectedPartials = array_values(
            array_unique(
                array_merge($this->protectedPartials, $names)
            )
        );

        return $this;
    }

    public function setPartial(string $section, bool $enabled = true): self
    {
        if (! array_key_exists($section, $this->partials)) {
            return $this;
        }

        if (is_array($this->partials[$section])) {
            $this->partials[$section]['enabled'] = $enabled;
        } else {
            $this->partials[$section] = $enabled;
        }

        return $this;
    }

    public function getPartials(): array
    {
        return $this->partials;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function setVar(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function getVar(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function clearPartials(): self
    {
        $this->partials = [];

        return $this;
    }

    /**
     * Menghapus status historis ketika partial didaftarkan kembali.
     */
    protected function clearRemovedStatus(string $name): void
    {
        $this->removedPartials = array_values(
            array_filter(
                $this->removedPartials,
                static fn(string $removed): bool => $removed !== $name
            )
        );
    }
}
