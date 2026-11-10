<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Images\Exceptions\ImageException;
use Config\Services;
use Modules\Core\Services\Storage\FileHandler;
use RuntimeException;
use Throwable;

class Uploader
{
    /**
     * Kompatibilitas dengan pemanggilan library lama.
     *
     * Folder tujuan fisik kini dikelola Storage.
     *
     * @return array{
     *     status: bool,
     *     filename: string|null,
     *     file_id?: int|string|null,
     *     thumbnail_id?: int|string|null,
     *     message: string
     * }
     */
    public function upload(
        UploadedFile $file,
        string $folder,
        array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'],
        int $thumbWidth = 150
    ): array {
        return $this->uploadWithThumbnail(
            $file,
            $folder,
            $allowedExtensions,
            $thumbWidth
        );
    }

    public function uploadWithThumbnail(
        UploadedFile $file,
        string $folder,
        array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'],
        int $thumbWidth = 150
    ): array {
        if (! $file->isValid() || $file->hasMoved()) {
            return $this->failure('File tidak valid atau sudah dipindahkan.');
        }

        $extension = strtolower($file->getExtension());
        $allowed = array_map('strtolower', $allowedExtensions);

        if (! in_array($extension, $allowed, true)) {
            return $this->failure('Ekstensi file tidak diizinkan.');
        }

        if ($thumbWidth < 1 || $thumbWidth > 2000) {
            return $this->failure('Ukuran thumbnail tidak valid.');
        }

        $sourcePath = $file->getTempName();

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return $this->failure('File sementara tidak dapat dibaca.');
        }

        $tempThumbnail = null;

        try {
            $handler = new FileHandler();

            // Upload file asli melalui Storage.
            $stored = $handler->upload(
                sourcePath: $sourcePath,
                originalName: $file->getName(),
                visibility: 'private'
            );

            $fileId = $stored['id'] ?? null;

            if ($fileId === null) {
                throw new RuntimeException(
                    'Storage tidak mengembalikan ID file.'
                );
            }

            // Buat thumbnail sementara di writable.
            $tempDirectory = WRITEPATH . 'uploads/tmp/';

            if (
                ! is_dir($tempDirectory)
                && ! mkdir($tempDirectory, 0750, true)
                && ! is_dir($tempDirectory)
            ) {
                throw new RuntimeException(
                    'Gagal membuat direktori sementara.'
                );
            }

            $tempThumbnail = $tempDirectory
                . bin2hex(random_bytes(16))
                . '.jpg';

            Services::image()
                ->withFile($sourcePath)
                ->resize($thumbWidth, $thumbWidth, true, 'height')
                ->save($tempThumbnail, 85);

            // Thumbnail menjadi file Storage tersendiri.
            $thumbnail = $handler->upload(
                sourcePath: $tempThumbnail,
                originalName: 'thumbnail-' . $fileId . '.jpg',
                visibility: 'private'
            );

            return [
                'status'       => true,
                'filename'     => (string) $fileId,
                'file_id'      => $fileId,
                'thumbnail_id' => $thumbnail['id'] ?? null,
                'message'      => 'Upload dan thumbnail berhasil.',
            ];
        } catch (Throwable $e) {
            log_message(
                'error',
                'Uploader Storage gagal: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->failure(
                'Upload atau pembuatan thumbnail gagal.'
            );
        } finally {
            if (
                $tempThumbnail !== null
                && is_file($tempThumbnail)
            ) {
                @unlink($tempThumbnail);
            }
        }
    }

    private function failure(string $message): array
    {
        return [
            'status'   => false,
            'filename' => null,
            'message'  => $message,
        ];
    }

    /**
     * Penghapusan fisik tidak lagi dilakukan oleh Uploader.
     * File dapat dipakai bersama oleh beberapa relasi Storage.
     *
     * Gunakan lifecycle Storage untuk mengelola penghapusan.
     */
    public function deleteImage(
        string $folder,
        string $filename
    ): bool {
        log_message(
            'warning',
            'Uploader::deleteImage() tidak menghapus file fisik. '
                . 'Gunakan pengelolaan relasi dan lifecycle Storage.'
        );

        return false;
    }
}
