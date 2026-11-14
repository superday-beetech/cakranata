<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * ============================================================
 * ENTERPRISE ROUTES (default deny)
 * ============================================================
 * Prinsip:
 *  1. Auto-routing MATI. Tidak ada controller yang terjangkau tanpa route eksplisit.
 *  2. Semua route modul / app berada di dalam group ber-filter RBAC.
 *     Route di luar group hanya: Shield auth, health, webhook (filter signature).
 *  3. Pola URL dibatasi (placeholder ketat + kedalaman maksimum).
 *  4. Prefix web & API dihasilkan oleh SATU fungsi yang sama.
 *  5. Nama modul reserved/bentrok ditolak dan dicatat di log.
 *  6. Production memakai manifest cache (tanpa glob/class_exists tiap request).
 *  7. Request HTTP hanya memuat modul yang cocok dengan segmen URL.
 *
 * Wajib ada di Config\Filters:
 *  - alias 'rbac'              => filter RBAC (default deny, idempoten)
 *  - alias 'webhook-signature' => validasi signature/token webhook
 */

// ============================================================
// KONFIGURASI
// ============================================================

$routes->setAutoRoute(false);
$routes->setTranslateURIDashes(false);

$routes->get('/', 'Home::index');


$modulesPath  = ROOTPATH . 'modules' . DIRECTORY_SEPARATOR;
$manifestFile = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'routes.manifest.php';
$verbs        = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

// Filter untuk seluruh route modul. Untuk API bertoken, ganti sesuai setup
// Shield-mu (mis. gunakan filter chain yang sudah mencakup autentikasi + RBAC).
$webFilter = [];
$apiFilter = [];
$publicFilter = [];
// $webFilter = 'rbac'; diaktifkan setelah proses berjalan benar
// $apiFilter = 'rbac'; diaktifkan setelah proses berjalan benar

// Nama (hasil kebab-case) yang tidak boleh dipakai modul.
$reserved = [
    'api',
    'login',
    'logout',
    'register',
    // 'auth',
    'oauth',
    'webhook',
    'webhooks',
    'health',
    'assets',
    'public',
    'writable',
    'vendor',
    'index-php',
    'favicon-ico',
    'robots-txt',
    'well-known',
];

// Placeholder ketat: tolak karakter aneh (.., %, null byte, spasi) sejak di router.
$routes->addPlaceholder([
    'dslug'  => '[A-Za-z][A-Za-z0-9_-]{0,63}',
    'dparam' => '[A-Za-z0-9_-]{1,64}',
]);

// ============================================================
// HELPER
// ============================================================

$log = static function (string $level, string $message): void {
    log_message($level, '[Routes] ' . $message);
};

// Satu-satunya sumber prefix untuk web DAN API.
$toKebabCase = static function (string $name): string {
    $name = preg_replace('/Controller$/', '', $name) ?? $name;
    $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $name) ?? $name;
    $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name) ?? $name;

    return strtolower($name);
};

// Memuat file route dengan scope terisolasi (hanya $routes yang terlihat).
$loadRouteFile = static function (RouteCollection $routes, string $file): void {
    require $file;
};

// ============================================================
// MANIFEST MODUL
// ============================================================

$buildManifest = static function () use ($modulesPath, $reserved, $toKebabCase, $log): array {
    $manifest = ['modules' => [], 'app' => []];

    // --- Modul ---
    $modulesReal = realpath($modulesPath);

    if ($modulesReal !== false) {
        $dirs = glob($modulesPath . '*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        $seen = [];

        foreach ($dirs as $dir) {
            $module = basename($dir);

            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $module)) {
                $log('error', "Nama modul tidak valid dilewati: {$module}");
                continue;
            }

            // Cegah symlink keluar dari folder modules.
            $real = realpath($dir);
            if ($real === false || ! str_starts_with($real, $modulesReal . DIRECTORY_SEPARATOR)) {
                $log('critical', "Modul di luar folder modules ditolak: {$module}");
                continue;
            }

            $prefix = $toKebabCase($module);

            if (in_array($prefix, $reserved, true)) {
                $log('critical', "Nama modul reserved ditolak: {$module} ({$prefix})");
                continue;
            }

            if (isset($seen[$prefix])) {
                $log('critical', "Prefix bentrok: {$module} dengan {$seen[$prefix]} ({$prefix})");
                continue;
            }

            $namespace   = "Modules\\{$module}\\Routers";
            $dynamicFile = $dir . '/Routers/DynamicRouter.php';
            $apiFile     = $dir . '/Routers/ApiRouter.php';
            $customFile  = $dir . '/Config/Routes.php';
            $customApiFile = $dir . '/Config/ApiRoutes.php';

            $hasDynamic = false;
            if (is_file($dynamicFile)) {
                $hasDynamic = class_exists("{$namespace}\\DynamicRouter");
                if (! $hasDynamic) {
                    $log('error', "DynamicRouter ada tapi class tidak ter-autoload (cek Autoload::\$psr4): {$module}");
                }
            }

            $hasApi = false;
            if (is_file($apiFile)) {
                $hasApi = class_exists("{$namespace}\\ApiRouter");
                if (! $hasApi) {
                    $log('error', "ApiRouter ada tapi class tidak ter-autoload (cek Autoload::\$psr4): {$module}");
                }
            }

            $hasCustom = is_file($customFile);

            if (! $hasDynamic && ! $hasApi && ! $hasCustom) {
                continue;
            }

            $seen[$prefix] = $module;

            $manifest['modules'][$module] = [
                'prefix'     => $prefix,
                'namespace'  => $namespace,
                'dynamic'    => $hasDynamic,
                'api'        => $hasApi,
                'customFile' => $hasCustom ? $customFile : null,
                'customApiFile' => is_file($customApiFile) ? $customApiFile : null,
            ];
        }
    }

    // --- Route khusus app/Controllers/*/Config/Routes.php ---
    $appReal = realpath(APPPATH . 'Controllers');

    if ($appReal !== false) {
        foreach (glob(APPPATH . 'Controllers/*/Config/Routes.php') ?: [] as $file) {
            $real = realpath($file);
            if ($real !== false && str_starts_with($real, $appReal . DIRECTORY_SEPARATOR)) {
                $manifest['app'][] = $real;
            }
        }
        sort($manifest['app']);
    }

    return $manifest;
};

$isProduction = ENVIRONMENT === 'production';
$manifest     = null;
$cacheDir = dirname($manifestFile);

if (! is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
if ($isProduction && is_file($manifestFile)) {
    $cached = include $manifestFile;

    if (is_array($cached) && isset($cached['modules'], $cached['app'])) {
        $manifest = $cached;
    }
}

if ($manifest === null) {
    $manifest = $buildManifest();

    if ($isProduction) {
        // Tulis atomik agar request paralel tidak membaca file setengah jadi.
        $tmp = $manifestFile . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmp, '<?php return ' . var_export($manifest, true) . ';', LOCK_EX) !== false) {
            @rename($tmp, $manifestFile);
        } else {
            $log('error', 'Gagal menulis manifest route: ' . $manifestFile);
        }
    }
}

// ============================================================
// OPTIMASI: muat hanya modul yang relevan dengan URL (CLI muat semua)
// ============================================================

$segments = null;

if (! is_cli()) {
    $path     = trim((string) service('request')->getPath(), '/');
    $segments = $path === '' ? [''] : explode('/', $path, 4);
}

$wantsWeb = static fn(string $prefix): bool => $segments === null || ($segments[0] ?? '') === $prefix;
$wantsApi = static fn(string $prefix): bool => $segments === null
    || (($segments[0] ?? '') === 'api' && ($segments[1] ?? '') === 'v1' && ($segments[2] ?? '') === $prefix);

// ============================================================
// ROUTE PUBLIK (eksplisit, sedikit, tanpa RBAC)
// ============================================================

// Login/logout dari Shield. Aktifkan 'except' bila registrasi publik tidak diinginkan.
// service('auth')->routes($routes /*, ['except' => ['register']] */);

// Health check: jangan kembalikan informasi sensitif di controller ini.
// Sesuaikan nama controller dengan milikmu.
$routes->get('health', 'HealthController::index');
// $routes->get('erp', 'ErpSpaController::index');
// $routes->get('erp/(:any)', 'ErpSpaController::index/$1');
$routes->get('vite', 'Vite::index');
$routes->get('acn', 'ErpSpaController::index');
$routes->get('acn/login', 'ErpSpaController::index');
$routes->get('acn/(:any)', 'ErpSpaController::index');
$routes->get('api/v1/navigation', 'ErpApiController::index');
$routes->get('api/v1/settings', 'ErpApiController::settings');
$routes->post('api/v1/settings', 'ErpApiController::settings');

// Webhook (payment gateway / marketplace): wajib validasi signature di filter.
$routes->group(
    'webhook',
    ['namespace' => 'App\Controllers', 'filter' => 'webhook-signature'],
    static function (RouteCollection $routes): void {
        $routes->post('(:dslug)', 'WebhookController::handle/$1');
    }
);

// ============================================================
// ROUTE WEB DINAMIS PER MODUL (selalu lewat filter RBAC)
// ============================================================

foreach ($manifest['modules'] as $module => $entry) {
    $prefix = $entry['prefix'];

    if (! $wantsWeb($prefix) || (! $entry['dynamic'] && $entry['customFile'] === null)) {
        continue;
    }

    $routes->group(
        $prefix,
        ['namespace' => $entry['namespace'], 'filter' => $webFilter],
        static function (RouteCollection $routes) use ($entry, $verbs, $loadRouteFile): void {
            // Route eksplisit modul dimuat DULU agar menang atas catch-all,
            // tetapi tetap di bawah filter RBAC milik group.
            if ($entry['customFile'] !== null) {
                $loadRouteFile($routes, $entry['customFile']);
            }

            if (! $entry['dynamic']) {
                return;
            }

            // Kedalaman maksimum 4 segmen: controller/method/param1/param2
            $routes->match($verbs, '/', 'DynamicRouter::route');
            $routes->match($verbs, '(:dslug)', 'DynamicRouter::route/$1');
            $routes->match($verbs, '(:dslug)/(:dslug)', 'DynamicRouter::route/$1/$2');
            $routes->match($verbs, '(:dslug)/(:dslug)/(:dparam)', 'DynamicRouter::route/$1/$2/$3');
            $routes->match($verbs, '(:dslug)/(:dslug)/(:dparam)/(:dparam)', 'DynamicRouter::route/$1/$2/$3/$4');
        }
    );
}

// ============================================================
// ROUTE API PER MODUL (prefix sama dengan web)
// ============================================================

foreach ($manifest['modules'] as $module => $entry) {
    $prefix = $entry['prefix'];

    if (! $entry['api'] || ! $wantsApi($prefix)) {
        continue;
    }

    $routes->group(
        "api/v1/{$prefix}",
        ['namespace' => $entry['namespace'], 'filter' => $apiFilter],
        static function (RouteCollection $routes) use ($entry, $verbs, $loadRouteFile): void {
            //                                                       ↑ TAMBAH $entry & $loadRouteFile

            // Muat route API statis modul DULU (kalau ada)
            if (! empty($entry['customApiFile'])) {
                $loadRouteFile($routes, $entry['customApiFile']);
            }

            // Wildcard setelahnya
            $routes->match($verbs, '(:dslug)', 'ApiRouter::route/$1');
            $routes->match($verbs, '(:dslug)/(:dparam)', 'ApiRouter::route/$1/$2');
            $routes->match($verbs, '(:dslug)/(:dparam)/(:dparam)', 'ApiRouter::route/$1/$2/$3');
        }
    );
}

// ============================================================
// ROUTE KHUSUS APP/CONTROLLERS (juga dipaksa lewat RBAC)
// ============================================================

if ($manifest['app'] !== []) {
    $routes->group(
        '',
        ['filter' => $publicFilter],
        static function (RouteCollection $routes) use ($manifest, $loadRouteFile): void {
            foreach ($manifest['app'] as $file) {
                $loadRouteFile($routes, $file);
            }
        }
    );
}
