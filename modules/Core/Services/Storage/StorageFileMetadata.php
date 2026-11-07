<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use InvalidArgumentException;

final class StorageFileMetadata
{
    public function __construct(
        public readonly string $disk,
        public readonly string $key,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int $size,
        public readonly string $checksum
    ) {
        if ($this->disk === '' || $this->key === '') {
            throw new InvalidArgumentException(
                'Disk dan storage key wajib diisi.'
            );
        }

        if (
            $this->originalName === ''
            || str_contains($this->originalName, "\0")
        ) {
            throw new InvalidArgumentException(
                'Nama file tidak valid.'
            );
        }

        if (
            $this->size < 1
            || preg_match('/^[a-f0-9]{64}$/i', $this->checksum) !== 1
        ) {
            throw new InvalidArgumentException(
                'Ukuran atau checksum file tidak valid.'
            );
        }

        if (
            preg_match('/^[a-z0-9]+$/i', $this->extension) !== 1
        ) {
            throw new InvalidArgumentException(
                'Ekstensi file tidak valid.'
            );
        }
    }

    public function toArray(): array
    {
        return [
            'disk'          => $this->disk,
            'storage_key'   => $this->key,
            'original_name' => $this->originalName,
            'mime_type'     => $this->mimeType,
            'extension'     => strtolower($this->extension),
            'size'          => $this->size,
            'checksum'      => strtolower($this->checksum),
        ];
    }
}
