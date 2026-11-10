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

$routes->group('', ['namespace' => 'Modules\RouteRegistry',], static function ($routes): void {
    $routes->match(
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '(:segment)/(:segment)/(:any)',
        'DynamicRouter::route/$1/$2/$3'
    );

    $routes->match(
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '(:segment)/(:segment)',
        'DynamicRouter::route/$1/$2'
    );

    $routes->match(
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '(:segment)',
        'DynamicRouter::route/$1'
    );

    $routes->match(
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/',
        'DynamicRouter::route'
    );
});
