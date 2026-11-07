<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use finfo;
use RuntimeException;

final class StorageValidationService
{
    /**
     * Allowlist keamanan ACN.
     * Config\Mimes dapat digunakan sebagai referensi ekstensi,
     * tetapi tidak menggantikan allowlist kebijakan aplikasi.
     */
    private array $allowedMimeTypes = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'text/plain' => ['txt'],
        'text/csv' => ['csv'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        => ['docx'],
    ];

    public function __construct(
        private int $maxBytes = 10485760
    ) {
        if ($this->maxBytes < 1) {
            throw new RuntimeException(
                'Batas ukuran file harus lebih besar dari nol.'
            );
        }
    }

    /**
     * @return array{
     *     size:int,
     *     mime_type:string,
     *     extension:string,
     *     checksum:string
     * }
     */
    public function validatePath(
        string $path,
        ?string $originalName = null
    ): array {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                'File tidak ditemukan atau tidak dapat dibaca.'
            );
        }

        $size = filesize($path);

        if ($size === false || $size < 1) {
            throw new RuntimeException(
                'File kosong atau ukuran file tidak dapat dibaca.'
            );
        }

        if ($size > $this->maxBytes) {
            throw new RuntimeException(
                'Ukuran file melebihi batas yang diizinkan.'
            );
        }

        $originalName ??= basename($path);

        if (
            $originalName === ''
            || str_contains($originalName, "\0")
            || preg_match('/[\x00-\x1F\x7F]/', $originalName) === 1
        ) {
            throw new RuntimeException('Nama file tidak valid.');
        }

        $extension = strtolower(
            pathinfo(basename($originalName), PATHINFO_EXTENSION)
        );

        if (
            $extension === ''
            || preg_match('/^[a-z0-9]+$/', $extension) !== 1
        ) {
            throw new RuntimeException(
                'Ekstensi file tidak valid.'
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if (! is_string($mime) || $mime === '') {
            throw new RuntimeException(
                'Jenis file tidak dapat dikenali.'
            );
        }

        if (! isset($this->allowedMimeTypes[$mime])) {
            throw new RuntimeException(
                'Jenis file tidak diizinkan.'
            );
        }

        if (! in_array(
            $extension,
            $this->allowedMimeTypes[$mime],
            true
        )) {
            throw new RuntimeException(
                'Ekstensi file tidak sesuai dengan jenis file.'
            );
        }

        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new RuntimeException(
                'Gagal menghitung checksum file.'
            );
        }

        return [
            'size' => $size,
            'mime_type' => $mime,
            'extension' => $extension,
            'checksum' => $checksum,
        ];
    }

    public function setAllowedMimeTypes(array $types): void
    {
        foreach ($types as $mime => $extensions) {
            if (
                ! is_string($mime)
                || preg_match(
                    '~^[a-z0-9][a-z0-9!#$&^_.+-]*/[a-z0-9][a-z0-9!#$&^_.+-]*$~i',
                    $mime
                ) !== 1
                || ! is_array($extensions)
                || $extensions === []
            ) {
                throw new RuntimeException(
                    'Konfigurasi MIME file tidak valid.'
                );
            }

            foreach ($extensions as $extension) {
                if (
                    ! is_string($extension)
                    || preg_match('/^[a-z0-9]+$/i', $extension) !== 1
                ) {
                    throw new RuntimeException(
                        'Konfigurasi ekstensi file tidak valid.'
                    );
                }
            }
        }

        $this->allowedMimeTypes = $types;
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }
}
