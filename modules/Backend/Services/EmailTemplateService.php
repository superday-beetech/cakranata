<?php

declare(strict_types=1);

namespace Modules\Backend\Services;

use Modules\Backend\Repositories\EmailTemplateRepository;
use RuntimeException;

class EmailTemplateService
{
    public function __construct(
        private readonly EmailTemplateRepository $repository =
        new EmailTemplateRepository()
    ) {}

    /**
     * Render template aktif berdasarkan kode.
     *
     * @return array{
     *     code: string,
     *     subject: string,
     *     body_html: string,
     *     body_text: string
     * }
     */
    public function render(string $code, array $data = []): array
    {
        $template = $this->repository->findByCode(trim($code));

        if ($template === null) {
            throw new RuntimeException(
                'Template email tidak ditemukan atau tidak aktif.'
            );
        }

        $allowedVariables = $this->decodeVariables(
            $template['variables'] ?? null
        );

        // Variabel masukan harus terdaftar.
        foreach (array_keys($data) as $key) {
            if (
                ! is_string($key)
                || ! in_array($key, $allowedVariables, true)
            ) {
                throw new RuntimeException(
                    'Variabel template tidak diizinkan: ' . (string) $key
                );
            }
        }

        $subject = (string) ($template['subject'] ?? '');
        $bodyHtml = (string) ($template['body_html'] ?? '');
        $bodyText = (string) ($template['body_text'] ?? '');

        $placeholders = $this->extractPlaceholders(
            $subject . "\n" . $bodyHtml . "\n" . $bodyText
        );

        foreach ($placeholders as $key) {
            if (! in_array($key, $allowedVariables, true)) {
                throw new RuntimeException(
                    'Placeholder tidak terdaftar pada template: ' . $key
                );
            }

            if (! array_key_exists($key, $data)) {
                throw new RuntimeException(
                    'Nilai variabel template belum diberikan: ' . $key
                );
            }

            if (! is_scalar($data[$key]) && $data[$key] !== null) {
                throw new RuntimeException(
                    'Nilai variabel harus berupa teks atau angka: ' . $key
                );
            }

            $value = (string) ($data[$key] ?? '');

            // Variabel URL hanya boleh menggunakan HTTP atau HTTPS.
            if (
                str_ends_with($key, '_url')
                || str_ends_with($key, '_link')
            ) {
                $this->validateUrl($key, $value);
            }
        }

        // Subject tidak boleh menyisipkan baris header email.
        $subject = preg_replace('/[\r\n]+/', ' ', $subject) ?? '';

        $renderedSubject = $this->replaceVariables(
            $subject,
            $data,
            false
        );

        $renderedHtml = $this->replaceVariables(
            $bodyHtml,
            $data,
            true
        );

        $renderedText = $bodyText !== ''
            ? $this->replaceVariables($bodyText, $data, false)
            : trim(strip_tags($renderedHtml));

        return [
            'code'      => (string) $template['code'],
            'subject'   => $renderedSubject,
            'body_html' => $renderedHtml,
            'body_text' => $renderedText,
        ];
    }

    /**
     * Decode JSON array variabel yang diizinkan.
     */
    private function decodeVariables(mixed $variables): array
    {
        if ($variables === null || $variables === '') {
            return [];
        }

        if (is_string($variables)) {
            try {
                $variables = json_decode(
                    $variables,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $e) {
                throw new RuntimeException(
                    'Konfigurasi variabel template bukan JSON yang valid.',
                    0,
                    $e
                );
            }
        }

        if (! is_array($variables) || ! array_is_list($variables)) {
            throw new RuntimeException(
                'Variabel template harus berupa JSON array.'
            );
        }

        foreach ($variables as $variable) {
            if (
                ! is_string($variable)
                || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $variable) !== 1
            ) {
                throw new RuntimeException(
                    'Format nama variabel template tidak valid.'
                );
            }
        }

        if (count($variables) !== count(array_unique($variables))) {
            throw new RuntimeException(
                'Daftar variabel template mengandung duplikasi.'
            );
        }

        return $variables;
    }

    /**
     * Ambil seluruh placeholder {{variable_name}}.
     */
    private function extractPlaceholders(string $content): array
    {
        preg_match_all(
            '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            $content,
            $matches
        );

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Ganti placeholder dengan nilai yang sudah divalidasi.
     */
    private function replaceVariables(
        string $content,
        array $data,
        bool $escapeHtml
    ): string {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            static function (array $matches) use ($data, $escapeHtml): string {
                $value = (string) ($data[$matches[1]] ?? '');

                if ($escapeHtml) {
                    return htmlspecialchars(
                        $value,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );
                }

                return $value;
            },
            $content
        ) ?? $content;
    }

    /**
     * Batasi URL dinamis ke skema HTTP/HTTPS.
     */
    private function validateUrl(string $key, string $value): void
    {
        $scheme = strtolower(
            (string) parse_url($value, PHP_URL_SCHEME)
        );

        if (
            filter_var($value, FILTER_VALIDATE_URL) === false
            || ! in_array($scheme, ['http', 'https'], true)
        ) {
            throw new RuntimeException(
                'Nilai variabel URL tidak valid: ' . $key
            );
        }
    }
}
