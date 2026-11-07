<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use InvalidArgumentException;
use RuntimeException;

final class FileHandler
{
    private const PUBLIC_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private ?StorageFacade $storage = null
    ) {
        $this->storage ??= service('storage');
    }

    /**
     * Upload file ke storage privat atau publik.
     *
     * @return array<string, mixed>
     */
    public function upload(
        string $sourcePath,
        string $originalName,
        ?int $uploadedBy = null,
        ?int $organizationId = null,
        string $visibility = 'private'
    ): array {
        $visibility = strtolower(trim($visibility));

        if (! in_array($visibility, ['private', 'public'], true)) {
            throw new InvalidArgumentException(
                'Visibilitas file harus private atau public.'
            );
        }

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException(
                'File sumber tidak ditemukan atau tidak dapat dibaca.'
            );
        }

        if (trim($originalName) === '') {
            throw new InvalidArgumentException(
                'Nama file asli wajib diisi.'
            );
        }

        $disk = match ($visibility) {
            'private' => 'local',
            'public'  => 'public',
        };

        // File publik hanya boleh berupa gambar yang diizinkan.
        if ($visibility === 'public') {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($sourcePath);

            if (
                ! is_string($mimeType)
                || ! in_array($mimeType, self::PUBLIC_MIME_TYPES, true)
            ) {
                throw new InvalidArgumentException(
                    'File publik hanya boleh berupa JPG, PNG, atau WebP.'
                );
            }
        }

        return $this->storage->upload(
            sourcePath: $sourcePath,
            originalName: $originalName,
            uploadedBy: $uploadedBy,
            organizationId: $organizationId,
            disk: $disk
        );
    }
}
