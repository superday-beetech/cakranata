<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage\Contracts;

interface StorageDriverInterface
{
    /**
     * Simpan file dari path sementara ke storage.
     *
     * @return array{
     *     key: string,
     *     size: int,
     *     checksum: string
     * }
     */
    public function writeFromPath(
        string $key,
        string $sourcePath
    ): array;

    /**
     * Buka file sebagai stream.
     *
     * @return resource
     */
    public function readStream(string $key);

    public function exists(string $key): bool;

    public function delete(string $key): bool;

    public function copy(
        string $sourceKey,
        string $destinationKey
    ): bool;

    public function size(string $key): int;

    public function checksum(string $key): string;
}
