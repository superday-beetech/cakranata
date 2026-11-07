<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use Modules\Core\Repositories\StorageFileRepository;
use RuntimeException;
use Throwable;

final class StorageMetadataService
{
    public function __construct(
        private ?StorageService $storage = null,
        private ?StorageFileRepository $repository = null
    ) {
        $this->storage ??= new StorageService();
        $this->repository ??= new StorageFileRepository();
    }

    /**
     * Simpan file fisik dan metadata.
     *
     * organizationId bersifat opsional.
     *
     * @return array<string, mixed>
     */
    public function store(
        string $sourcePath,
        string $originalName,
        ?int $organizationId = null,
        ?int $uploadedBy = null,
        ?string $disk = null
    ): array {
        if ($organizationId !== null && $organizationId < 1) {
            throw new RuntimeException('ID organisasi tidak valid.');
        }

        if ($uploadedBy !== null && $uploadedBy < 1) {
            throw new RuntimeException('ID pengguna tidak valid.');
        }

        $stored = $this->storage->storeFromPath(
            $sourcePath,
            $originalName,
            $disk
        );

        $storageKey = $stored['key'];
        $storedDisk = $stored['disk'];

        $metadata = [
            'disk'          => $storedDisk,
            'storage_key'   => $storageKey,
            'original_name' => $stored['original_name'],
            'mime_type'     => $stored['mime_type'],
            'extension'     => $stored['extension'],
            'size'          => $stored['size'],
            'checksum'      => $stored['checksum'],
            'uploaded_by'   => $uploadedBy,
            'status'        => 'active',
        ];

        if ($organizationId !== null) {
            $metadata['organization_id'] = $organizationId;
        }

        try {
            $id = $this->repository->create($metadata);
        } catch (Throwable $exception) {
            try {
                $this->storage->delete($storageKey, $storedDisk);
            } catch (Throwable $cleanupException) {
                log_message(
                    'critical',
                    'Storage cleanup gagal setelah metadata gagal. '
                        . 'Key: {key}; Error: {error}',
                    [
                        'key'   => $storageKey,
                        'error' => $cleanupException->getMessage(),
                    ]
                );
            }

            throw $exception;
        }

        return [
            'id'              => $id,
            'organization_id' => $organizationId,
            'uploaded_by'     => $uploadedBy,
            ...$stored,
            'storage_key'     => $storageKey,
        ];
    }
}
