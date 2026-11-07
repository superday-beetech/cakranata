<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Config\Database;
use Modules\Core\Services\Storage\StorageFileRelation;
use RuntimeException;

final class StorageRelationRepository
{
    public function create(
        StorageFileRelation $relation,
        ?int $createdBy = null
    ): int|string {
        if ($createdBy !== null && $createdBy < 1) {
            throw new RuntimeException(
                'ID pengguna pembuat relasi tidak valid.'
            );
        }

        $data = $relation->toArray();

        if (
            ! isset($data['file_id'])
            || ! is_numeric($data['file_id'])
            || (int) $data['file_id'] < 1
        ) {
            throw new RuntimeException('ID file tidak valid.');
        }

        foreach (['module', 'entity_type', 'entity_id', 'purpose'] as $field) {
            if (
                ! isset($data[$field])
                || ! is_string($data[$field])
                || trim($data[$field]) === ''
            ) {
                throw new RuntimeException(
                    "Data relasi '{$field}' tidak valid."
                );
            }

            $data[$field] = trim($data[$field]);
        }

        $db = Database::connect();
        $builder = $db->table('storage_file_relations');

        $existing = $builder
            ->where('file_id', (int) $data['file_id'])
            ->where('module', $data['module'])
            ->where('entity_type', $data['entity_type'])
            ->where('entity_id', $data['entity_id'])
            ->where('purpose', $data['purpose'])
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return $existing['id'];
        }

        $data['file_id'] = (int) $data['file_id'];
        $data['created_by'] = $createdBy;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['deleted_at'] = null;

        if (! $db->table('storage_file_relations')->insert($data)) {
            throw new RuntimeException('Gagal menyimpan relasi file.');
        }

        $id = $db->insertID();

        if ($id === false || $id === 0 || $id === '0' || $id === '') {
            throw new RuntimeException(
                'Relasi tersimpan, tetapi ID tidak berhasil diperoleh.'
            );
        }

        return $id;
    }

    public function findByFileId(int $fileId): array
    {
        if ($fileId < 1) {
            return [];
        }

        return Database::connect()
            ->table('storage_file_relations')
            ->where('file_id', $fileId)
            ->where('deleted_at', null)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function softDelete(int $relationId): bool
    {
        if ($relationId < 1) {
            return false;
        }

        $db = Database::connect();

        $db->table('storage_file_relations')
            ->where('id', $relationId)
            ->where('deleted_at', null)
            ->update([
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);

        return $db->affectedRows() > 0;
    }
}
