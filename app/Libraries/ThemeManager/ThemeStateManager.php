<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

class ThemeStateManager
{
    protected ?string $title = null;

    protected array $data = [];

    protected array $classes = [];

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function getData(?array $key = null): array
    {
        if ($key === null) {
            return $this->data;
        }

        $result = [];

        foreach ($key as $item) {
            if (array_key_exists($item, $this->data)) {
                $result[$item] = $this->data[$item];
            }
        }

        return $result;
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

    public function addClass(string $section, string|array $classes): self
    {
        $classes = is_array($classes)
            ? $classes
            : preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY);

        $this->classes[$section] = array_values(array_unique(array_merge(
            $this->classes[$section] ?? [],
            $classes
        )));

        return $this;
    }

    public function removeClass(string $section, string|array $classes): self
    {
        $classes = is_array($classes)
            ? $classes
            : preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY);

        $this->classes[$section] = array_values(array_diff(
            $this->classes[$section] ?? [],
            $classes
        ));

        if ($this->classes[$section] === []) {
            unset($this->classes[$section]);
        }

        return $this;
    }

    public function replaceClass(
        string $section,
        string|array $classes
    ): self {
        $classes = is_array($classes)
            ? $classes
            : preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY);

        $this->classes[$section] = array_values(array_unique($classes));

        return $this;
    }

    public function clearClasses(?string $section = null): self
    {
        if ($section === null) {
            $this->classes = [];
        } else {
            unset($this->classes[$section]);
        }

        return $this;
    }

    public function getClass(string $section): string
    {
        return implode(' ', $this->classes[$section] ?? []);
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getRawData(): array
    {
        return $this->data;
    }

    public function replaceData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
