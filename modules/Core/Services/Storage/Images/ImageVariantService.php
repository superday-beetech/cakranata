
<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage\Images;

use RuntimeException;
use Throwable;

final class ImageVariantService
{
    private const ALLOWED_SOURCE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const ALLOWED_OUTPUT_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    /**
     * Membuat varian gambar menggunakan Image Library bawaan CI4.
     *
     * Destination harus berupa path internal yang dikendalikan aplikasi.
     *
     * @return array{
     *     width:int,
     *     height:int,
     *     mime_type:string,
     *     extension:string,
     *     size:int
     * }
     */
    public function create(
        string $sourcePath,
        string $destinationPath,
        int $maxSize = 150,
        string $handler = 'gd',
        int $quality = 80
    ): array {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException(
                'File sumber gambar tidak dapat dibaca.'
            );
        }

        if ($maxSize < 1 || $maxSize > 4096) {
            throw new RuntimeException(
                'Ukuran varian gambar tidak valid.'
            );
        }

        if ($quality < 0 || $quality > 100) {
            throw new RuntimeException(
                'Kualitas gambar harus antara 0 dan 100.'
            );
        }

        $handler = strtolower($handler);

        if (! in_array($handler, ['gd', 'imagick'], true)) {
            throw new RuntimeException(
                'Image handler harus gd atau imagick.'
            );
        }

        $info = @getimagesize($sourcePath);

        if ($info === false) {
            throw new RuntimeException(
                'File sumber bukan gambar yang valid.'
            );
        }

        $mime = strtolower((string) ($info['mime'] ?? ''));

        if (! in_array($mime, self::ALLOWED_SOURCE_MIMES, true)) {
            throw new RuntimeException(
                'Format gambar sumber tidak diizinkan.'
            );
        }

        $extension = strtolower(
            pathinfo($destinationPath, PATHINFO_EXTENSION)
        );

        if (! in_array(
            $extension,
            self::ALLOWED_OUTPUT_EXTENSIONS,
            true
        )) {
            throw new RuntimeException(
                'Format output gambar tidak diizinkan.'
            );
        }

        if (
            file_exists($destinationPath) ||
            is_link($destinationPath)
        ) {
            throw new RuntimeException(
                'File tujuan sudah ada.'
            );
        }

        $directory = dirname($destinationPath);

        if (
            ! is_dir($directory) &&
            ! mkdir($directory, 0750, true) &&
            ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Folder tujuan gambar gagal dibuat.'
            );
        }

        $temporaryPath = tempnam($directory, '.variant-');

        if ($temporaryPath === false) {
            throw new RuntimeException(
                'File sementara gagal dibuat.'
            );
        }

        // ImageMagick menggunakan ekstensi target untuk menentukan format.
        // Gunakan ekstensi yang benar pada file sementara.
        $temporaryImage = $temporaryPath . '.' . $extension;

        try {
            $image = service('image', $handler);

            $image->withFile($sourcePath)
                ->resize($maxSize, $maxSize, true)
                ->save($temporaryImage, $quality);

            $outputInfo = @getimagesize($temporaryImage);

            if ($outputInfo === false) {
                throw new RuntimeException(
                    'Hasil pemrosesan bukan gambar yang valid.'
                );
            }

            $outputMime = strtolower(
                (string) ($outputInfo['mime'] ?? '')
            );

            $expectedMime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'webp'        => 'image/webp',
            };

            if ($outputMime !== $expectedMime) {
                throw new RuntimeException(
                    'Format hasil gambar tidak sesuai ekstensi.'
                );
            }

            if (! rename($temporaryImage, $destinationPath)) {
                throw new RuntimeException(
                    'Hasil gambar gagal dipindahkan.'
                );
            }

            clearstatcache(true, $destinationPath);

            $size = filesize($destinationPath);

            if ($size === false) {
                @unlink($destinationPath);

                throw new RuntimeException(
                    'Ukuran hasil gambar gagal dibaca.'
                );
            }

            return [
                'width'     => (int) $outputInfo[0],
                'height'    => (int) $outputInfo[1],
                'mime_type' => $outputMime,
                'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
                'size'      => $size,
            ];
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            if (is_file($temporaryImage)) {
                @unlink($temporaryImage);
            }

            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
