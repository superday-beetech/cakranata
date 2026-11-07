<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('', ['namespace' => 'Modules\Backend\Routers',], static function ($routes): void {
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
