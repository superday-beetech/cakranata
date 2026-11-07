<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use Modules\Core\Repositories\StorageFileRepository;
use Modules\Core\Repositories\StorageRelationRepository;
use RuntimeException;

final class StorageFacade
{
    public function __construct(
        private ?StorageMetadataService $metadata = null,
        private ?StorageService $storage = null,
        private ?StorageRelationRepository $relations = null,
        private ?StorageFileRepository $files = null
    ) {
        $this->metadata ??= new StorageMetadataService();
        $this->storage ??= new StorageService();
        $this->relations ??= new StorageRelationRepository();
        $this->files ??= new StorageFileRepository();
    }

    /**
     * Simpan file fisik sekaligus metadata.
     *
     * @return array<string, mixed>
     */
    public function upload(
        string $sourcePath,
        string $originalName,
        ?int $uploadedBy = null,
        ?int $organizationId = null,
        ?string $disk = null
    ): array {
        return $this->metadata->store(
            sourcePath: $sourcePath,
            originalName: $originalName,
            organizationId: $organizationId,
            uploadedBy: $uploadedBy,
            disk: $disk
        );
    }

    /**
     * Hubungkan file aktif dengan entitas modul.
     */
    public function attach(
        int $fileId,
        string $module,
        string $entityType,
        string $entityId,
        string $purpose = 'attachment',
        ?int $createdBy = null
    ): int|string {
        if ($this->files->findActiveById($fileId) === null) {
            throw new RuntimeException(
                'File tidak ditemukan atau tidak aktif.'
            );
        }

        $relation = new StorageFileRelation(
            fileId: $fileId,
            module: $module,
            entityType: $entityType,
            entityId: $entityId,
            purpose: $purpose
        );

        return $this->relations->create($relation, $createdBy);
    }

    /**
     * Ambil relasi aktif sebuah file.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRelations(int $fileId): array
    {
        if ($fileId < 1) {
            throw new RuntimeException('ID file tidak valid.');
        }

        return $this->relations->findByFileId($fileId);
    }

    /**
     * Baca file untuk pemakaian internal.
     *
     * Pemanggil wajib memastikan otorisasi dan kepemilikan
     * metadata sebelum membaca file.
     *
     * @return resource
     */
    public function readStream(string $key, ?string $disk = null)
    {
        return $this->storage->readStream($key, $disk);
    }
}
