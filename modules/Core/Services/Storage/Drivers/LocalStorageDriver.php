<?php

declare(strict_types=1);

namespace Modules\Core\Services\Storage\Drivers;

use Modules\Core\Services\Storage\Contracts\StorageDriverInterface;
use RuntimeException;

final class LocalStorageDriver implements StorageDriverInterface
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        if (! is_dir($rootPath)) {
            if (! mkdir($rootPath, 0750, true) && ! is_dir($rootPath)) {
                throw new RuntimeException(
                    'Gagal membuat direktori storage.'
                );
            }
        }

        $realPath = realpath($rootPath);

        if ($realPath === false || ! is_writable($realPath)) {
            throw new RuntimeException(
                'Direktori storage tidak valid atau tidak dapat ditulis.'
            );
        }

        $this->rootPath = rtrim($realPath, DIRECTORY_SEPARATOR);
    }

    public function writeFromPath(
        string $key,
        string $sourcePath
    ): array {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException(
                'File sumber tidak ditemukan atau tidak dapat dibaca.'
            );
        }

        $destination = $this->resolvePath($key);
        if (file_exists($destination) || is_link($destination)) {
            throw new RuntimeException(
                'Storage key sudah digunakan.'
            );
        }
        $directory   = dirname($destination);

        $this->ensureDirectory($directory);

        $temporary = $directory
            . DIRECTORY_SEPARATOR
            . '.uploading-'
            . bin2hex(random_bytes(16));

        $input  = fopen($sourcePath, 'rb');
        $output = false;

        try {
            if ($input === false) {
                throw new RuntimeException(
                    'Gagal membuka file sumber.'
                );
            }

            $output = fopen($temporary, 'xb');

            if ($output === false) {
                throw new RuntimeException(
                    'Gagal membuat file sementara.'
                );
            }

            $copied = stream_copy_to_stream($input, $output);
            $sourceSize = filesize($sourcePath);

            if (
                $copied === false ||
                $sourceSize === false ||
                $copied !== $sourceSize ||
                ! fflush($output)
            ) {
                throw new RuntimeException(
                    'File gagal disalin secara lengkap ke storage.'
                );
            }

            if (! fclose($output)) {
                $output = false;

                throw new RuntimeException(
                    'Gagal menutup file sementara.'
                );
            }

            $output = false;

            if (! fclose($input)) {
                $input = false;

                throw new RuntimeException(
                    'Gagal menutup file sumber.'
                );
            }

            $input = false;

            if (! rename($temporary, $destination)) {
                throw new RuntimeException(
                    'Gagal menyelesaikan penyimpanan file.'
                );
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }

            if (is_resource($output)) {
                fclose($output);
            }

            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return [
            'key'      => $key,
            'size'     => $this->size($key),
            'checksum' => $this->checksum($key),
        ];
    }

    public function readStream(string $key)
    {
        $path = $this->resolvePath($key);

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                'File tidak ditemukan atau tidak dapat dibaca.'
            );
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException(
                'Gagal membuka file.'
            );
        }

        return $stream;
    }

    public function exists(string $key): bool
    {
        return is_file($this->resolvePath($key));
    }

    public function delete(string $key): bool
    {
        $path = $this->resolvePath($key);

        if (! file_exists($path) && ! is_link($path)) {
            return true;
        }

        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException(
                'Target penghapusan bukan file reguler yang diizinkan.'
            );
        }

        if (! unlink($path)) {
            throw new RuntimeException(
                'Gagal menghapus file.'
            );
        }

        return true;
    }

    public function copy(
        string $sourceKey,
        string $destinationKey
    ): bool {
        $source = $this->resolvePath($sourceKey);

        if (! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException(
                'File sumber tidak ditemukan.'
            );
        }

        $this->writeFromPath($destinationKey, $source);

        return true;
    }

    public function size(string $key): int
    {
        $path = $this->resolvePath($key);

        if (! is_file($path)) {
            throw new RuntimeException(
                'File tidak ditemukan.'
            );
        }

        $size = filesize($path);

        if ($size === false) {
            throw new RuntimeException(
                'Gagal membaca ukuran file.'
            );
        }

        return $size;
    }

    public function checksum(string $key): string
    {
        $path = $this->resolvePath($key);

        if (! is_file($path)) {
            throw new RuntimeException(
                'File tidak ditemukan.'
            );
        }

        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new RuntimeException(
                'Gagal menghitung checksum file.'
            );
        }

        return $checksum;
    }

    private function resolvePath(string $key): string
    {

        if (
            $key === ''
            || str_contains($key, "\0")
            || str_contains($key, '\\')
            || str_starts_with($key, '/')
            || preg_match('~(^|/)\.{1,2}(/|$)~', $key) === 1
        ) {
            throw new RuntimeException('Storage key tidak valid.');
        }


        $segments = explode('/', $key);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new RuntimeException(
                    'Storage key tidak valid.'
                );
            }
        }

        $path = $this->rootPath
            . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, $segments);

        // Cegah key mengakses file melalui symlink di dalam storage.
        $current = $path;

        while (
            $current !== $this->rootPath
            && str_starts_with(
                $current,
                $this->rootPath . DIRECTORY_SEPARATOR
            )
        ) {
            if (is_link($current)) {
                throw new RuntimeException(
                    'Symlink tidak diizinkan dalam storage.'
                );
            }

            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        return $path;
    }

    private function ensureDirectory(string $directory): void
    {
        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0750, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Gagal membuat direktori tujuan.'
            );
        }

        $realDirectory = realpath($directory);

        if (
            $realDirectory === false
            || (
                $realDirectory !== $this->rootPath
                && ! str_starts_with(
                    $realDirectory,
                    $this->rootPath . DIRECTORY_SEPARATOR
                )
            )
        ) {
            throw new RuntimeException(
                'Direktori tujuan berada di luar storage root.'
            );
        }
    }
}
