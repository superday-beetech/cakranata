<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use Config\Services;
use RuntimeException;

final class ImageHandler
{
    private const MAX_BYTES = 10485760; // 10 MiB
    private const MAX_PIXELS = 40000000;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private ?StorageFacade $storage = null
    ) {
        $this->storage ??= service('storage');
    }

    /**
     * Upload gambar dengan opsi resize.
     *
     * @return array<string, mixed>
     */
    public function upload(
        string $sourcePath,
        string $originalName,
        ?int $uploadedBy = null,
        ?int $organizationId = null,
        int $maxWidth = 1920,
        int $maxHeight = 1920,
        int $quality = 85,
        string $visibility = 'private'
    ): array {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('File gambar tidak ditemukan atau tidak dapat dibaca.');
        }

        $size = filesize($sourcePath);

        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Ukuran gambar tidak valid atau melebihi batas 10 MiB.');
        }

        if (
            $maxWidth < 1 ||
            $maxHeight < 1 ||
            $quality < 1 ||
            $quality > 100
        ) {
            throw new RuntimeException('Pengaturan resize atau kualitas gambar tidak valid.');
        }

        $imageInfo = @getimagesize($sourcePath);

        if ($imageInfo === false) {
            throw new RuntimeException('File bukan gambar yang valid.');
        }

        [$width, $height] = $imageInfo;
        $mimeType = $imageInfo['mime'] ?? '';

        if (! isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new RuntimeException('Format gambar tidak didukung.');
        }

        if (
            $width < 1 ||
            $height < 1 ||
            ($width * $height) > self::MAX_PIXELS
        ) {
            throw new RuntimeException('Dimensi gambar melebihi batas yang diizinkan.');
        }

        $needsResize = $width > $maxWidth || $height > $maxHeight;
        $disk = match ($visibility) {
            'private' => 'local',
            'public'  => 'public',
            default   => throw new \InvalidArgumentException(
                'Visibilitas gambar harus private atau public.'
            ),
        };
        if (! $needsResize) {
            return $this->storage->upload(
                sourcePath: $sourcePath,
                originalName: $originalName,
                uploadedBy: $uploadedBy,
                organizationId: $organizationId,
                disk: $disk
            );
        }

        $tempDirectory = WRITEPATH . 'cache';

        if (
            ! is_dir($tempDirectory) &&
            ! mkdir($tempDirectory, 0750, true) &&
            ! is_dir($tempDirectory)
        ) {
            throw new RuntimeException('Direktori sementara gambar gagal dibuat.');
        }

        $tempBase = tempnam($tempDirectory, 'acn-image-');

        if ($tempBase === false) {
            throw new RuntimeException('File sementara gambar gagal dibuat.');
        }

        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $tempPath = $tempBase . '.' . $extension;

        if (! unlink($tempBase)) {
            throw new RuntimeException('File sementara awal gagal dibersihkan.');
        }

        try {
            $image = Services::image()
                ->withFile($sourcePath)
                ->resize($maxWidth, $maxHeight, true, 'auto');

            $image->save($tempPath, $quality);

            if (! is_file($tempPath) || filesize($tempPath) < 1) {
                throw new RuntimeException(
                    'Hasil resize gambar gagal dibuat.'
                );
            }

            $outputInfo = @getimagesize($tempPath);

            if (
                $outputInfo === false ||
                ($outputInfo['mime'] ?? '') !== $mimeType
            ) {
                throw new RuntimeException(
                    'Format hasil resize gambar tidak valid.'
                );
            }

            return $this->storage->upload(
                sourcePath: $tempPath,
                originalName: $originalName,
                uploadedBy: $uploadedBy,
                organizationId: $organizationId,
                disk: $disk
            );
        } finally {
            if (is_file($tempPath) && ! unlink($tempPath)) {
                log_message('warning', 'Gagal membersihkan file sementara gambar: {path}', [
                    'path' => $tempPath,
                ]);
            }
        }
    }
}
