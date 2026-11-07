<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use InvalidArgumentException;

final class StorageFileRelation
{
    public function __construct(
        public readonly int $fileId,
        public readonly string $module,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $purpose = 'attachment'
    ) {
        if ($this->fileId < 1) {
            throw new InvalidArgumentException(
                'ID file harus lebih besar dari nol.'
            );
        }

        if (
            preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $this->module) !== 1
        ) {
            throw new InvalidArgumentException(
                'Nama modul tidak valid.'
            );
        }

        if (
            preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $this->entityType) !== 1
        ) {
            throw new InvalidArgumentException(
                'Jenis entitas tidak valid.'
            );
        }

        if (
            trim($this->entityId) === ''
            || strlen($this->entityId) > 191
            || str_contains($this->entityId, "\0")
            || preg_match('/[\x00-\x1F\x7F]/', $this->entityId) === 1
        ) {
            throw new InvalidArgumentException(
                'ID entitas tidak valid.'
            );
        }

        if (
            preg_match('/^[a-z][a-z0-9_-]*$/', $this->purpose) !== 1
        ) {
            throw new InvalidArgumentException(
                'Tujuan relasi file tidak valid.'
            );
        }
    }

    public function toArray(): array
    {
        return [
            'file_id'    => $this->fileId,
            'module'     => $this->module,
            'entity_type' => $this->entityType,
            'entity_id'  => $this->entityId,
            'purpose'    => $this->purpose,
        ];
    }
}
