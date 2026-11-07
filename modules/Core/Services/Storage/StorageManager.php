<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage;

use Modules\Core\Services\Storage\Contracts\StorageDriverInterface;
use Modules\Core\Services\Storage\Drivers\LocalStorageDriver;
use Modules\Core\Services\Storage\Drivers\PublicStorageDriver;
use RuntimeException;

final class StorageManager
{
    private string $defaultDriver;

    private string $localRoot;

    private string $publicRoot;

    /** @var array<string, StorageDriverInterface> */
    private array $drivers = [];

    public function __construct(?string $defaultDriver = null, ?string $localRoot = null)
    {
        $this->defaultDriver = strtolower(
            trim($defaultDriver ?? 'local')
        );

        $this->localRoot = $localRoot
            ?? WRITEPATH . 'storage/private';

        $this->publicRoot = rtrim(
            FCPATH,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . 'uploads';

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $this->defaultDriver) !== 1) {
            throw new RuntimeException(
                'Nama driver storage default tidak valid.'
            );
        }

        if (trim($this->localRoot) === '') {
            throw new RuntimeException(
                'Lokasi storage lokal tidak boleh kosong.'
            );
        }
    }

    public function registerDriver(
        string $name,
        StorageDriverInterface $driver
    ): void {
        $name = strtolower(trim($name));

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
            throw new RuntimeException(
                'Nama driver storage tidak valid.'
            );
        }

        $this->drivers[$name] = $driver;
    }

    public function getDriver(
        ?string $name = null
    ): StorageDriverInterface {
        $name = strtolower(trim($name ?? $this->defaultDriver));

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
            throw new RuntimeException(
                'Nama driver storage tidak valid.'
            );
        }

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        if ($name === 'local') {
            return $this->drivers['local'] =
                new LocalStorageDriver($this->localRoot);
        }

        if ($name === 'public') {
            return $this->drivers['public'] =
                new PublicStorageDriver($this->publicRoot);
        }

        throw new RuntimeException(
            "Driver storage '{$name}' belum terdaftar."
        );
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }
}
