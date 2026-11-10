<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ============================================================
// ROUTE UTAMA
// ============================================================

$routes->get('/', 'Home::index');
$routes->get('vite', 'Vite::index');
$routes->get('erp', 'ErpSpaController::index');
$routes->get('erp/login', 'ErpSpaController::index');
$routes->get('erp/(:any)', 'ErpSpaController::index');
$routes->get('api/v1/navigation', 'ErpApiController::index');

// ============================================================
// KONFIGURASI DYNAMIC ROUTER
// ============================================================

$modulesPath = ROOTPATH . 'modules/';
$dynamicVerbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

$toSnakeCase = static function (string $name): string {
    $name = preg_replace('/Controller$/', '', $name) ?? $name;
    $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $name) ?? $name;
    $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name) ?? $name;

    return strtolower($name);
};

// ============================================================
// ROUTE DINAMIS MODUL
// ============================================================

foreach (glob($modulesPath . '*', GLOB_ONLYDIR) ?: [] as $dir) {
    $module = basename($dir);

    if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $module)) {
        continue;
    }
    $reserved = ['api', 'login', 'logout', 'auth', 'webhook', 'webhooks', 'assets', 'health'];
    // di dalam loop, setelah regex nama modul:
    if (in_array(strtolower($module), $reserved, true)) {
        continue; // atau log error saat boot
    }
    $namespace = "Modules\\{$module}\\Routers";

    // DynamicRouter berada di root namespace modul,
    // bukan di dalam Controllers.
    $dynamicRouterClass = "{$namespace}\\DynamicRouter";
    // $dynamicRouterFile  = $dir . '/DynamicRouter.php';
    $dynamicRouterFile = $dir . '/Routers/DynamicRouter.php';
    if (! is_file($dynamicRouterFile)) {
        continue;
    }

    // Prefix berdasarkan controller utama modul.
    $controllerClass = "{$namespace}\\Controllers\\{$module}Controller";
    $controllerFile  = $dir . '/Controllers/' . $module . 'Controller.php';

    if (is_file($controllerFile)) {
        $prefix = $toSnakeCase($module . 'Controller');
    } else {
        $prefix = strtolower($module);
    }

    $routes->group(
        $prefix,
        ['namespace' => $namespace],
        static function (RouteCollection $routes) use (
            $dir,
            $namespace,
            $dynamicRouterClass,
            $dynamicRouterFile,
            $dynamicVerbs
        ): void {
            $routeFile = $dir . '/Config/Routes.php';

            if (is_file($routeFile)) {
                require $routeFile;
            }

            if (! class_exists($dynamicRouterClass)) {
                return;
            }

            $routes->match(
                $dynamicVerbs,
                '/',
                'DynamicRouter::route'
            );

            $routes->match(
                $dynamicVerbs,
                '(:segment)',
                'DynamicRouter::route/$1'
            );

            $routes->match(
                $dynamicVerbs,
                '(:segment)/(:segment)',
                'DynamicRouter::route/$1/$2'
            );

            $routes->match(
                $dynamicVerbs,
                '(:segment)/(:segment)/(:any)',
                'DynamicRouter::route/$1/$2/$3'
            );
        }
    );
}

// ============================================================
// API ROUTES PER MODUL
// ============================================================

foreach (glob($modulesPath . '*', GLOB_ONLYDIR) ?: [] as $dir) {
    $module = basename($dir);

    if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $module)) {
        continue;
    }

    $namespace = "Modules\\{$module}\\Routers";
    $apiRouterClass = "{$namespace}\\ApiRouter";

    $apiRouterFile = $dir . '/Routers/ApiRouter.php';

    if (! is_file($apiRouterFile)) {
        continue;
    }

    if (! class_exists($apiRouterClass)) {
        continue;
    }

    $prefix = $toSnakeCase($module);

    $routes->group(
        "api/v1/{$prefix}",
        ['namespace' => $namespace],
        static function (RouteCollection $routes): void {
            $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

            $routes->match(
                $verbs,
                '(:segment)/(:any)',
                'ApiRouter::route/$1/$2'
            );

            $routes->match(
                $verbs,
                '(:segment)',
                'ApiRouter::route/$1'
            );
        }
    );
}
// ============================================================
// ROUTE KHUSUS APP/CONTROLLERS
// ============================================================

foreach (
    glob(APPPATH . 'Controllers/*/Config/Routes.php') ?: []
    as $file
) {
    require $file;
}
