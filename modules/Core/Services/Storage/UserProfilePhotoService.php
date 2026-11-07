<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use Config\Database;
use Modules\Core\Repositories\StorageFileRepository;
use RuntimeException;

final class UserProfilePhotoService
{
    public function __construct(
        private ?StorageFileRepository $files = null
    ) {
        $this->files ??= new StorageFileRepository();
    }

    /**
     * Jadikan foto yang sudah diunggah sebagai foto profil aktif.
     * File lama tidak dihapus.
     */
    public function setActive(int $userId, int $fileId): void
    {
        if ($userId < 1 || $fileId < 1) {
            throw new RuntimeException('ID pengguna atau file tidak valid.');
        }

        $file = $this->files->findActiveById($fileId);

        if ($file === null) {
            throw new RuntimeException('File tidak ditemukan atau tidak aktif.');
        }

        if (! str_starts_with(
            strtolower((string) ($file['mime_type'] ?? '')),
            'image/'
        )) {
            throw new RuntimeException('File bukan gambar.');
        }

        $db = Database::connect();
        $db->transStart();

        $relation = $db->table('storage_file_relations')
            ->where('file_id', $fileId)
            ->where('module', 'Core')
            ->where('entity_type', 'user')
            ->where('entity_id', (string) $userId)
            ->where('purpose', 'profile')
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($relation === null) {
            $db->table('storage_file_relations')->insert([
                'file_id'    => $fileId,
                'module'     => 'Core',
                'entity_type' => 'user',
                'entity_id'  => (string) $userId,
                'purpose'    => 'profile',
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'deleted_at' => null,
            ]);
        }

        $existing = $db->table('user_profile_photos')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($existing === null) {
            $db->table('user_profile_photos')->insert([
                'user_id'    => $userId,
                'file_id'    => $fileId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('user_profile_photos')
                ->where('user_id', $userId)
                ->update([
                    'file_id'    => $fileId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('Gagal memperbarui foto profil.');
        }
    }

    public function getActive(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $row = Database::connect()
            ->table('user_profile_photos p')
            ->select('f.*')
            ->join('storage_files f', 'f.id = p.file_id')
            ->where('p.user_id', $userId)
            ->where('f.status', 'active')
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Daftar foto terdahulu milik pengguna.
     */
    public function getHistory(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        return Database::connect()
            ->table('storage_file_relations r')
            ->select('f.id, f.original_name, f.mime_type, f.created_at')
            ->join('storage_files f', 'f.id = r.file_id')
            ->where('r.module', 'Core')
            ->where('r.entity_type', 'user')
            ->where('r.entity_id', (string) $userId)
            ->where('r.purpose', 'profile')
            ->where('r.deleted_at', null)
            ->where('f.status', 'active')
            ->orderBy('r.id', 'DESC')
            ->get()
            ->getResultArray();
    }
}
