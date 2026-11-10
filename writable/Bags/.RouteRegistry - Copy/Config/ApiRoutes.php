<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// $routes->get('', 'RouteRegistryController::index');
/* $routes->group('api', ['namespace' => 'Modules\RouteRegistry\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('verbs/(:segment)', 'RouteRegistryController::getVerbs/$1');
    $routes->post('verbs/save', 'RouteRegistryController::saveVerbs');
    $routes->get('scan', 'RouteRegistryController::scan');
    $routes->post('apply', 'RouteRegistryController::apply');
    $routes->get('registered', 'RouteRegistryController::registered');

    $routes->post('status', 'RouteRegistryController::updateStatus');
});
 */

$routes->group(
    'api/v1',
    ['namespace' => 'Modules\\RouteRegistry'],
    static function (RouteCollection $routes): void {
        $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        $routes->match($verbs, '(:segment)/(:any)', 'ApiRouter::route/$1/$2');

        $routes->match($verbs, '(:segment)', 'ApiRouter::route/$1');
    }
);
