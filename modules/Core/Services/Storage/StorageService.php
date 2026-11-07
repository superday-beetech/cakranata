<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use Throwable;

final class StorageService
{
    public function __construct(
        private ?StorageManager $manager = null,
        private ?StorageValidationService $validator = null
    ) {
        $this->manager ??= new StorageManager();
        $this->validator ??= new StorageValidationService();
    }

    /**
     * Simpan file dan kembalikan metadata standar.
     */
    public function storeFromPath(
        string $sourcePath,
        ?string $originalName = null,
        ?string $disk = null
    ): array {
        $originalName ??= basename($sourcePath);

        if (
            $originalName === '' ||
            str_contains($originalName, "\0") ||
            preg_match('/[\x00-\x1F\x7F]/', $originalName) === 1
        ) {
            throw new \InvalidArgumentException(
                'Nama file tidak valid.'
            );
        }

        // 1. Validasi sebelum menyimpan.
        $validated = $this->validator->validatePath(
            $sourcePath,
            $originalName
        );

        // 2. Pilih driver.
        $disk ??= $this->manager->getDefaultDriver();

        // 3. Buat key internal acak.
        $key = sprintf(
            'objects/%s/%s',
            date('Y/m'),
            bin2hex(random_bytes(32))
        );

        $driver = $this->manager->getDriver($disk);

        // 4. Simpan file fisik.
        $stored = $driver->writeFromPath($key, $sourcePath);

        try {
            // Pastikan driver mengembalikan key yang diminta.
            if (($stored['key'] ?? null) !== $key) {
                throw new \RuntimeException(
                    'Driver storage mengembalikan key yang tidak sesuai.'
                );
            }

            if (
                ! isset($stored['size'], $stored['checksum']) ||
                ! is_int($stored['size']) ||
                $stored['size'] < 1 ||
                ! is_string($stored['checksum']) ||
                preg_match('/^[a-f0-9]{64}$/i', $stored['checksum']) !== 1
            ) {
                throw new \RuntimeException(
                    'Metadata hasil penyimpanan tidak valid.'
                );
            }

            // 5. Kembalikan metadata standar.
            return [
                'disk'          => $disk,
                'key'           => $key,
                'original_name' => $originalName,
                'size'          => $stored['size'],
                'mime_type'     => $validated['mime_type'],
                'extension'     => $validated['extension'],
                'checksum'      => strtolower($stored['checksum']),
            ];
        } catch (Throwable $exception) {
            // Bersihkan file fisik jika hasil penyimpanan tidak valid.
            try {
                if ($driver->exists($key)) {
                    $driver->delete($key);
                }
            } catch (Throwable $cleanupException) {
                log_message(
                    'critical',
                    'Gagal membersihkan file storage setelah proses gagal. Key: {key}; Error: {error}',
                    [
                        'key'   => $key,
                        'error' => $cleanupException->getMessage(),
                    ]
                );
            }

            throw $exception;
        }
    }

    /**
     * Pemanggil wajib memeriksa otorisasi file terlebih dahulu.
     *
     * @return resource
     */
    public function readStream(
        string $key,
        ?string $disk = null
    ) {
        return $this->manager
            ->getDriver($disk)
            ->readStream($key);
    }

    public function exists(
        string $key,
        ?string $disk = null
    ): bool {
        return $this->manager
            ->getDriver($disk)
            ->exists($key);
    }

    public function delete(
        string $key,
        ?string $disk = null
    ): bool {
        return $this->manager
            ->getDriver($disk)
            ->delete($key);
    }

    public function getManager(): StorageManager
    {
        return $this->manager;
    }
}
