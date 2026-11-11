<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Libraries;

class TemplateBindingResolver
{
    public function resolve(string $text, array $data): string
    {
        // {{#each path}}...{{/each}}
        $text = preg_replace_callback(
            '/\{\{#each\s+([\w\.\-]+)\s*\}\}(.*?)\{\{\/each\}\}/s',
            function (array $m) use ($data) {
                $items = $this->getByPath($data, $m[1]);
                if (! is_array($items)) return '';
                $out = '';
                foreach ($items as $item) {
                    $out .= $this->resolve($m[2], is_array($item) ? $item : []);
                }
                return $out;
            },
            $text
        );

        // {{ path }}
        return preg_replace_callback(
            '/\{\{\s*([\w\.\-]+)\s*\}\}/',
            fn(array $m) => (string) ($this->getByPath($data, $m[1]) ?? ''),
            $text
        );
    }

    public function resolveElements(array $elements, array $data): array
    {
        foreach ($elements as &$el) {
            $type = $el['type'] ?? 'text';

            // Tabel dinamis: isi rows dari binding
            if ($type === 'table') {
                $binding = $el['props']['binding'] ?? null;
                if (is_string($binding) && $binding !== '') {
                    $rows = $this->getByPath($data, $binding);
                    if (is_array($rows)) {
                        $el['props']['rows'] = $rows;
                    }
                }
            }

            $binding = $el['props']['binding'] ?? null;
            if (is_string($binding) && $binding !== '') {
                $value = $this->getByPath($data, $binding);
                if ($value !== null) {
                    $el['props']['label'] = (string) $value;
                } else {
                    $label = (string) ($el['props']['label'] ?? '');
                    $el['props']['label'] = $this->resolve($label, $data);
                }
            } elseif (isset($el['props']['label'])) {
                $el['props']['label'] = $this->resolve((string) $el['props']['label'], $data);
            }
        }
        return $elements;
    }

    private function getByPath(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $cursor = $data;
        foreach ($segments as $seg) {
            if (is_array($cursor) && array_key_exists($seg, $cursor)) {
                $cursor = $cursor[$seg];
                continue;
            }
            if (is_object($cursor) && isset($cursor->{$seg})) {
                $cursor = $cursor->{$seg};
                continue;
            }
            return null;
        }
        return $cursor;
    }
}
