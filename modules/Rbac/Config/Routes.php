<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('', ['namespace' => 'Modules\\Rbac\\Routers'], static function (RouteCollection $routes): void {
    $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    $routes->match(
        $verbs,
        '(:segment)/(:segment)/(:any)',
        'DynamicRouter::route/$1/$2/$3'
    );

    $routes->match(
        $verbs,
        '(:segment)/(:segment)',
        'DynamicRouter::route/$1/$2'
    );

    $routes->match(
        $verbs,
        '(:segment)',
        'DynamicRouter::route/$1'
    );

    $routes->match(
        $verbs,
        '/',
        'DynamicRouter::route'
    );
});
