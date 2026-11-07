<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('login', '\Modules\Auth\Controllers\LoginController::index', ['as' => 'login']);
$routes->post('login', '\Modules\Auth\Controllers\LoginController::action', ['as' => 'login']);
$routes->get('logout', '\Modules\Auth\Controllers\LoginController::logout', ['as' => 'logout']);
$routes->get('developer-recovery', '\Modules\Auth\Controllers\DeveloperRecoveryController::index', ['as' => 'developer-recovery']);

$routes->get('magic-link', '\Modules\Auth\Controllers\MagicController::index', ['as' => 'magic-link']);

$routes->get('register', '\Modules\Auth\Controllers\MagicController::index', ['as' => 'register']);

$routes->match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '(:segment)/(:segment)/(:any)',
    '\Modules\Auth\Routers\DynamicRouter::route/$1/$2/$3'
);

$routes->match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '(:segment)/(:segment)',
    '\Modules\Auth\Routers\DynamicRouter::route/$1/$2'
);

$routes->match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '(:segment)',
    '\Modules\Auth\Routers\DynamicRouter::route/$1'
);

$routes->match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '/',
    '\Modules\Auth\Routers\DynamicRouter::route'
);
