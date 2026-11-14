<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
/* $routes->get(
    'api/v1/pharmacy/print-templates/(:num)/render',
    'Modules\Pharmacy\Controllers\API\V1\PrintTemplatesController::render/$1'
);
$routes->get(
    'api/v1/pharmacy/print-templates/(:num)/render.pdf',
    'Modules\Pharmacy\Controllers\API\V1\PrintTemplatesController::renderPdf/$1'
); */

$routes->get(
    'print-templates/(:num)/render.pdf',
    '\Modules\Pharmacy\Controllers\API\V1\PrintTemplatesController::renderPdf/$1'
);
$routes->get(
    'print-templates/(:num)/render',
    '\Modules\Pharmacy\Controllers\API\V1\PrintTemplatesController::render/$1'
);
/*
$routes->group('api/v1/pharmacy', ['namespace' => 'Modules\Pharmacy\Controllers\API\V1'], function ($routes) {
    $routes->get('sales-api', 'SalesApiController::index');
    $routes->get('sales-api/(:num)', 'SalesApiController::show/$1');
    $routes->post('sales-api', 'SalesApiController::create', ['filter' => 'throttle:20,1']);
    $routes->post('sales-api/checkout', 'SalesApiController::checkout', ['filter' => 'throttle:10,1']);
    $routes->post('sales-api/(:num)/post', 'SalesApiController::post/$1', ['filter' => 'throttle:10,1']);
    $routes->post('sales-api/(:num)/hold', 'SalesApiController::hold/$1', ['filter' => 'throttle:20,1']);
    $routes->post('sales-api/resume/(:num)', 'SalesApiController::resume/$1', ['filter' => 'throttle:20,1']);
    $routes->post('sales-api/(:num)/cancel', 'SalesApiController::cancel/$1', ['filter' => 'throttle:10,1']);
});
 */
$routes->group(
    '',
    ['namespace' => 'Modules\\pharmacy\\Routers'],
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
