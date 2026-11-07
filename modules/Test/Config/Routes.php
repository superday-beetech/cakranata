<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('', ['namespace' => 'Modules\Test\Controllers',], static function ($routes): void {
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




/* $routes->group('auth', ['namespace' => 'Modules\Auth\Controllers'], function ($routes) {
    $routes->get('home', 'Auth::index');
});
// service('auth')->routes($routes, ['only' => ['magic-link', 'verify-magic-link'],]);
// service('auth')->routes($routes, ['only' => ['register', 'register'],]);

$routes->group('auth', ['namespace' => 'Modules\Auth\Controllers\Web',], static function (RouteCollection $routes): void {
    $routes->get('login', 'LoginController::loginView', ['as' => 'login',]);
    $routes->post('login', 'LoginController::loginAction');
    $routes->get('logout', 'LoginController::logoutAction', ['as' => 'logout',]);
    $routes->get('register', 'RegisterController::registerView', [
        'as' => 'register',
    ]);

    $routes->post('register', 'RegisterController::registerAction');
    $routes->get('magic-link', 'MagicController::loginView', [
        'as' => 'magic-link',
    ]);

    $routes->post('magic-link', 'MagicController::loginAction');

    $routes->get('magic-link/verify', 'MagicController::verify', [
        'as' => 'magic-link-verify',
    ]);
});
 */

/*
<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;


// File ini dimuat dari dalam group modul "auth".
// Prefix dan namespace sudah diwariskan dari app/Config/Routes.php.

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

 */
