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
 *  - Isi cache divalidasi (bentuk + namespace + jumlah method per controller) sebelum dipakai.
 *  - methodCount disimpan di cache untuk mendeteksi cache basi.
 *  - Cache key menyertakan ENVIRONMENT agar dev/prod tidak tercampur.
 *  - Exception pada settings/config di-log (tidak silent).
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

    /**
     * Method dari CodeIgniter\Controller yang tidak boleh jadi endpoint,
     * meskipun di-override di subclass. Ditambah daftar eksplisit
     * (bukan method_exists) agar controller yang meng-override method
     * tertentu tetap bisa di-route jika memang diinginkan.
     *
     * @var list<string>
     */
    private const EXCLUDED_METHODS = [
        'initController',
        'forceHTTPS',
        'cachePage',
        'validate',
        'render',
        'renderSection',
        'renderSectionRecursive',
        'getResponse',
        'getRequest',
        'getLogger',
        'helpers',
    ];

    /**
     * Class yang tidak boleh di-scan menjadi controller.
     * Ditambahkan agar tidak perlu hard-code getShortName() di isEligible().
     *
     * @var list<class-string>
     */
    private const EXCLUDED_CLASSES = [
        DynamicRouterBase::class,
        // Tambahkan class lain di sini jika ada (mis. DynamicRouter).
    ];

    private bool $useCache;
    private int $cacheTtl;

    public function __construct(
        private string $moduleFolder,
        private string $moduleNamespace,
        ?bool $useCache = null,
        int $cacheTtl = 86400,
    ) {
        // Default: cache aktif hanya di production. Di dev/test, selalu scan ulang.
        $this->useCache = $useCache ?? (ENVIRONMENT === 'production');
        $this->cacheTtl = $cacheTtl;
    }

    // ============================================================
    // Public API
    // ============================================================

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

    /**
     * Normalisasi nama controller untuk keperluan lookup dari URL.
     * Contoh: "user-profile" / "user_profile" / "User-Profile" -> "UserProfile".
     *
     * Catatan: fungsi ini TIDAK mengembalikan nama class asli untuk akronim.
     * Mis. "api-key" -> "ApiKey" (bukan "APIKey"). Jika butuh nama class
     * asli, gunakan $this->controllerClasses (via scanner) — bukan fungsi ini.
     */
    public function normalizeControllerName(string $controller): string
    {
        $controller = trim($controller);
        if ($controller === '') {
            return '';
        }

        // Jika sudah mengandung huruf besar tanpa separator, anggap sudah PascalCase.
        if (
            preg_match('/[A-Z]/', $controller)
            && ! str_contains($controller, '-')
            && ! str_contains($controller, '_')
        ) {
            return $controller;
        }

        $controller = str_replace(['-', '_'], ' ', $controller);

        return str_replace(' ', '', ucwords($controller));
    }

    // ============================================================
    // Scan
    // ============================================================

    private function scanDirectory(): array
    {
        $empty = [
            'allowedControllers' => [],
            'discoveredMethods'  => [],
            'controllerClasses'  => [],
            'methodCount'        => [],
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
            log_message('debug', "ControllerScanner: folder Controllers tidak ditemukan untuk modul '{$folder}'.");

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

        $methods     = [];
        $methodCount = [];

        foreach (array_keys($classes) as $short) {
            $methodCount[$short] = count($methodsMap[$short]);

            foreach ($methodsMap[$short] as $method) {
                $methods[] = $short . '::' . $method;
            }
        }

        return [
            'allowedControllers' => array_keys($classes),
            'discoveredMethods'  => $methods,
            'controllerClasses'  => $classes,
            'methodCount'        => $methodCount,
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
        if (! $reflection->isInstantiable()) {
            return false;
        }

        if (! $reflection->isSubclassOf(Controller::class)) {
            return false;
        }

        // Buang class yang secara eksplisit dikecualikan (termasuk DynamicRouterBase).
        foreach (self::EXCLUDED_CLASSES as $excluded) {
            if ($reflection->getName() === $excluded || $reflection->isSubclassOf($excluded)) {
                return false;
            }
        }

        return true;
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
                || in_array($name, self::EXCLUDED_METHODS, true)
            ) {
                continue;
            }

            $names[] = $name;
        }

        sort($names, SORT_STRING);

        return array_values(array_unique($names));
    }

    // ============================================================
    // Cache
    // ============================================================

    private function isValidShape(mixed $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        // Wajib ada 4 key, semuanya array.
        foreach (['allowedControllers', 'discoveredMethods', 'controllerClasses', 'methodCount'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                return false;
            }
        }

        $prefix = trim($this->moduleNamespace, '\\') . '\\';

        // 1. Validasi controllerClasses: short name & namespace prefix.
        foreach ($data['controllerClasses'] as $short => $class) {
            if (
                ! is_string($short) || ! is_string($class)
                || preg_match(self::NAME_PATTERN, $short) !== 1
                || ! str_starts_with($class, $prefix)
            ) {
                return false;
            }
        }

        // 2. Validasi discoveredMethods: format 'Controller::method' & controller ada.
        $actualCount = [];

        foreach ($data['discoveredMethods'] as $endpoint) {
            if (! is_string($endpoint)) {
                return false;
            }

            $parts = explode('::', $endpoint, 2);

            if (count($parts) !== 2 || ! isset($data['controllerClasses'][$parts[0]])) {
                return false;
            }

            $actualCount[$parts[0]] = ($actualCount[$parts[0]] ?? 0) + 1;
        }

        // 3. Validasi allowedControllers: setiap entry ada di controllerClasses.
        foreach ($data['allowedControllers'] as $controller) {
            if (! is_string($controller) || ! isset($data['controllerClasses'][$controller])) {
                return false;
            }
        }

        // 4. Validasi silang: methodCount harus cocok dengan jumlah method aktual.
        //    Ini mendeteksi cache basi (mis. file controller diedit tanpa clear cache).
        foreach ($data['allowedControllers'] as $controller) {
            if (($data['methodCount'][$controller] ?? -1) !== ($actualCount[$controller] ?? 0)) {
                log_message('warning', "ControllerScanner: cache invalid — methodCount mismatch untuk '{$controller}'.");

                return false;
            }
        }

        // 5. Validasi silang: setiap controller di allowedControllers harus punya minimal 1 method.
        //    Controller tanpa method publik = tidak berguna, kemungkinan cache basi.
        foreach ($data['allowedControllers'] as $controller) {
            if (($actualCount[$controller] ?? 0) < 1) {
                log_message('warning', "ControllerScanner: cache invalid — controller '{$controller}' tanpa method.");

                return false;
            }
        }

        return true;
    }

    private function getCacheKey(): string
    {
        return 'dynamic_router_scan_' . md5(
            $this->moduleFolder
                . '|' . $this->moduleNamespace
                . '|' . ENVIRONMENT
        );
    }

    private function isCacheEnabled(): bool
    {
        try {
            $value = service('settings')->get('App.cacheRouter');

            if ($value !== null) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        } catch (Throwable $e) {
            log_message('debug', 'ControllerScanner: settings tidak tersedia — ' . $e->getMessage());
        }

        try {
            $config = config('App');

            if (isset($config->cacheDynamic)) {
                return (bool) $config->cacheDynamic;
            }
        } catch (Throwable $e) {
            log_message('debug', 'ControllerScanner: config App tidak tersedia — ' . $e->getMessage());
        }

        return false;
    }
}
