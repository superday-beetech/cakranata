<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

class ThemeMetadataManager
{
    protected array $meta = [];

    /**
     * Menambahkan satu metadata atau beberapa metadata sekaligus.
     *
     * Mendukung:
     * - addMeta('description', 'Deskripsi')
     * - addMeta('viewport', 'width=device-width', 'name')
     * - addMeta('utf-8', '', 'charset')
     * - addMeta('stylesheet', 'https://example.com/style.css', 'link')
     * - addMeta(['name' => 'description', 'content' => '...'])
     * - addMeta([['name' => 'description', 'content' => '...']])
     */
    public function addMeta(
        string|array $name,
        string $content = '',
        string $type = 'name',
        array $extraAttributes = []
    ): self {
        // Daftar metadata numerik.
        if (
            is_array($name)
            && array_is_list($name)
        ) {
            foreach ($name as $meta) {
                $this->addMeta($meta);
            }

            return $this;
        }

        // Metadata dalam bentuk array asosiatif.
        if (is_array($name)) {
            $meta = $name;
            $tag = $meta['tag'] ?? 'meta';

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
                    $key = sha1(
                        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            ?: serialize($meta)
                    );
                }
            } elseif ($tag === 'link') {
                $rel = $meta['rel'] ?? 'link';
                $href = $meta['href'] ?? '';

                $key = 'rel:' . $rel . '|href:' . $href;
            } else {
                $key = sha1(
                    json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        ?: serialize($meta)
                );
            }

            $this->meta[$key] = $meta;

            return $this;
        }

        // Mode klasik.
        $type = strtolower($type);

        if ($type === 'charset') {
            $key = 'charset:' . $name;

            $this->meta[$key] = [
                'tag' => 'meta',
                'charset' => $name,
            ];
        } elseif ($type === 'link') {
            $key = 'rel:' . $name . '|href:' . $content;

            $this->meta[$key] = array_merge(
                [
                    'tag' => 'link',
                    'rel' => $name,
                    'href' => $content,
                ],
                $extraAttributes
            );
        } else {
            $key = $type . ':' . $name;

            $this->meta[$key] = array_merge(
                [
                    'tag' => 'meta',
                    $type => $name,
                    'content' => $content,
                ],
                $extraAttributes
            );
        }

        return $this;
    }

    /**
     * Menetapkan metadata menggunakan key khusus.
     *
     * Perilaku ini mengikuti Theme lama:
     * nilai disimpan sebagai string dan bukan struktur tag HTML.
     */
    public function setMeta(string $name, string $content): self
    {
        $this->meta[$name] = $content;

        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function removeMeta(string $key): self
    {
        unset($this->meta[$key]);

        return $this;
    }

    public function clearMeta(): self
    {
        $this->meta = [];

        return $this;
    }

    /**
     * Menghasilkan HTML metadata.
     */
    public function metaHTML(array $options = []): string
    {
        if (empty($this->meta)) {
            return '';
        }

        $wrapper = $options['wrapper'] ?? '%s';
        $html = '';

        foreach ($this->meta as $meta) {
            // Kompatibilitas dengan setMeta() versi lama,
            // yang menyimpan nilai berupa string.
            if (! is_array($meta)) {
                continue;
            }

            $tag = $meta['tag'] ?? 'meta';

            if (isset($meta['charset'])) {
                $html .= '<meta charset="' . esc($meta['charset']) . '">' . PHP_EOL;

                continue;
            }

            if (! in_array($tag, ['meta', 'link'], true)) {
                continue;
            }

            $html .= '<' . $tag;

            foreach ($meta as $key => $value) {
                if ($key === 'tag') {
                    continue;
                }

                if ($value === null || is_array($value) || is_object($value)) {
                    continue;
                }

                if (is_bool($value)) {
                    if ($value) {
                        $html .= ' ' . esc($key);
                    }

                    continue;
                }

                $html .= ' ' . esc($key) . '="' . esc((string) $value) . '"';
            }

            $html .= '>' . PHP_EOL;
        }

        return sprintf($wrapper, $html);
    }
}
