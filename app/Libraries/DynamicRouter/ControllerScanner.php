<?php

declare(strict_types=1);

/**
 * Simpan di: app/Libraries/DynamicRouter/ControllerScanner.php
 *
 * Perubahan utama:
 *  - Class diverifikasi benar-benar berasal dari file yang dipindai
 *    (menghapus ambiguitas prefix Api\V1 tanpa menebak).
 *  - Nama controller ganda -> KEDUANYA dibuang + log critical (tidak ada "yang terakhir menang").
 *  - Urutan file deterministik (sort).
 *  - Hanya turunan CodeIgniter\Controller; bukan DynamicRouterBase.
 *  - Method bawaan Controller (initController, forceHTTPS, cachePage, ...) tidak jadi endpoint.
 *  - Isi cache divalidasi (bentuk + namespace) sebelum dipakai.
 */

namespace App\Libraries\DynamicRouter;

use App\Libraries\DynamicRouterBase;
use CodeIgniter\Controller;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class ControllerScanner
{
    private const NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_]*$/';

    public function __construct(
        private string $moduleFolder,
        private string $moduleNamespace,
        private bool $useCache = true,
        private int $cacheTtl = 86400,
    ) {}

    public function scan(): array
    {
        if (! $this->useCache || ! $this->isCacheEnabled()) {
            return $this->scanDirectory();
        }

        $key    = $this->getCacheKey();
        $cached = cache()->get($key);

        if ($this->isValidShape($cached)) {
            return $cached;
        }

        if ($cached !== null) {
            log_message('warning', 'Cache scanner tidak valid dibuang: ' . $this->moduleFolder);
        }

        $result = $this->scanDirectory();
        cache()->save($key, $result, $this->cacheTtl);

        return $result;
    }

    public function scanWithoutCache(): array
    {
        return $this->scanDirectory();
    }

    public function clearCache(): bool
    {
        return cache()->delete($this->getCacheKey());
    }

    // ============================================================

    private function scanDirectory(): array
    {
        $empty = [
            'allowedControllers' => [],
            'discoveredMethods'  => [],
            'controllerClasses'  => [],
        ];

        $folder        = trim($this->moduleFolder, '/\\');
        $baseNamespace = trim($this->moduleNamespace, '\\');

        if (preg_match(self::NAME_PATTERN, $folder) !== 1 || $baseNamespace === '') {
            return $empty;
        }

        $controllerPath = realpath(
            rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'Controllers'
        );

        if ($controllerPath === false) {
            return $empty;
        }

        // --- Kumpulkan file secara deterministik ---
        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllerPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $real = $file->getRealPath();

            // Tolak symlink yang keluar dari folder Controllers.
            if ($real === false || ! str_starts_with($real, $controllerPath . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[] = $real;
        }

        sort($files, SORT_STRING);

        // --- Resolusi class ---
        $classes    = [];
        $methodsMap = [];
        $duplicates = [];

        foreach ($files as $real) {
            $reflection = $this->reflectFile($real, $controllerPath, $baseNamespace);

            if ($reflection === null || ! $this->isEligible($reflection)) {
                continue;
            }

            $short     = $reflection->getShortName();
            $className = $reflection->getName();

            if (preg_match(self::NAME_PATTERN, $short) !== 1) {
                continue;
            }

            if (isset($classes[$short]) && $classes[$short] !== $className) {
                $duplicates[$short] = true;
                log_message(
                    'critical',
                    "Nama controller ganda '{$short}' di modul '{$folder}': {$classes[$short]} dan {$className}. Keduanya dinonaktifkan."
                );

                continue;
            }

            $classes[$short]    = $className;
            $methodsMap[$short] = $this->collectMethods($reflection);
        }

        foreach (array_keys($duplicates) as $short) {
            unset($classes[$short], $methodsMap[$short]);
        }

        ksort($classes, SORT_STRING);

        $methods = [];

        foreach (array_keys($classes) as $short) {
            foreach ($methodsMap[$short] as $method) {
                $methods[] = $short . '::' . $method;
            }
        }

        return [
            'allowedControllers' => array_keys($classes),
            'discoveredMethods'  => $methods,
            'controllerClasses'  => $classes,
        ];
    }

    /**
     * Cari class yang BENAR-BENAR dideklarasikan di file ini.
     */
    private function reflectFile(string $real, string $controllerPath, string $baseNamespace): ?ReflectionClass
    {
        $relative      = substr($real, strlen($controllerPath) + 1);
        $relativeClass = str_replace(DIRECTORY_SEPARATOR, '\\', substr($relative, 0, -4));

        // File di Controllers/API/V1 dipetakan ke namespace dasar + nama class (tanpa API\V1).
        //  - cakupan web  (base = ...\Controllers)         : controller API TIDAK ditemukan -> dilewati
        //  - cakupan API  (base = ...\Controllers\API\V1)  : hanya controller API yang ditemukan
        // Satu kandidat saja; kecocokan file diverifikasi di bawah, jadi tidak ada tebakan.
        $stripped   = preg_replace('/^api\\\\v1\\\\/i', '', $relativeClass);
        $candidates = [$baseNamespace . '\\' . (is_string($stripped) ? $stripped : $relativeClass)];

        foreach ($candidates as $candidate) {
            try {
                if (! class_exists($candidate)) {
                    continue;
                }

                $reflection = new ReflectionClass($candidate);
                $fileName   = $reflection->getFileName();

                if ($fileName !== false && realpath($fileName) === $real) {
                    return $reflection;
                }
            } catch (Throwable $e) {
                log_message('error', "Scanner gagal memuat {$candidate}: " . $e->getMessage());
            }
        }

        return null;
    }

    private function isEligible(ReflectionClass $reflection): bool
    {
        return $reflection->isInstantiable()
            && $reflection->isSubclassOf(Controller::class)
            && ! $reflection->isSubclassOf(DynamicRouterBase::class)
            && $reflection->getShortName() !== 'DynamicRouter';
    }

    /**
     * @return list<string>
     */
    private function collectMethods(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (
                $method->getDeclaringClass()->getName() !== $reflection->getName()
                || $method->isStatic()
                || $method->isConstructor()
                || $method->isAbstract()
                || str_starts_with($name, '_')
                || preg_match(self::NAME_PATTERN, $name) !== 1
                || method_exists(Controller::class, $name) // initController, forceHTTPS, cachePage, ...
            ) {
                continue;
            }

            $names[] = $name;
        }

        sort($names, SORT_STRING);

        return $names;
    }

    private function isValidShape(mixed $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        foreach (['allowedControllers', 'discoveredMethods', 'controllerClasses'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                return false;
            }
        }

        $prefix = trim($this->moduleNamespace, '\\') . '\\';

        foreach ($data['controllerClasses'] as $short => $class) {
            if (
                ! is_string($short) || ! is_string($class)
                || preg_match(self::NAME_PATTERN, $short) !== 1
                || ! str_starts_with($class, $prefix)
            ) {
                return false;
            }
        }

        foreach ($data['discoveredMethods'] as $endpoint) {
            if (! is_string($endpoint)) {
                return false;
            }

            $parts = explode('::', $endpoint, 2);

            if (count($parts) !== 2 || ! isset($data['controllerClasses'][$parts[0]])) {
                return false;
            }
        }

        foreach ($data['allowedControllers'] as $controller) {
            if (! is_string($controller) || ! isset($data['controllerClasses'][$controller])) {
                return false;
            }
        }

        return true;
    }

    private function getCacheKey(): string
    {
        return 'dynamic_router_scan_' . md5($this->moduleFolder . '|' . $this->moduleNamespace);
    }

    private function isCacheEnabled(): bool
    {
        try {
            $value = service('settings')->get('App.cacheRouter');

            if ($value !== null) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        } catch (Throwable) {
            // Settings tidak tersedia, lanjut ke config.
        }

        try {
            $config = config('App');

            if (isset($config->cacheDynamic)) {
                return (bool) $config->cacheDynamic;
            }
        } catch (Throwable) {
            // Config tidak tersedia.
        }

        return false;
    }
}
