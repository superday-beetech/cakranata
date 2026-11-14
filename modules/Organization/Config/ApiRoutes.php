<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group(
    '',
    ['namespace' => 'Modules\\Organization\\Routers'],
    static function (RouteCollection $routes): void {
        $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        $routes->match(
            $verbs,
            '(:segment)',
            'ApiRouter::route/$1'
        );

        $routes->match(
            $verbs,
            '(:segment)/(:segment)',
            'ApiRouter::route/$1/$2'
        );

        // Tambahan: controller/method/id
        $routes->match(
            $verbs,
            '(:segment)/(:segment)/(:segment)',
            'ApiRouter::route/$1/$2/$3'
        );
    }
);
