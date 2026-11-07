<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group(
    'api/v1',
    ['namespace' => 'Modules\\Auth\\Routers'],
    static function (RouteCollection $routes): void {
        $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        /*
        $routes->match(
            $verbs,
            '(:segment)/(:any)/(:any)',
            'ApiRouter::route/$1/$2/$3'
        );
 */
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
