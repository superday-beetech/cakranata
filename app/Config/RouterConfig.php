<?php

declare(strict_types=1);

return [
    'allowedMethods' => [
        'UsersController::index',
        'UsersController::show',
        'UsersController::create',
        'UsersController::update',
        'UsersController::delete',
    ],

    'requiredPermissions' => [
        'UsersController::index'  => 'users.view',
        'UsersController::show'   => 'users.view',
        'UsersController::create' => 'users.create',
        'UsersController::update' => 'users.update',
        'UsersController::delete' => 'users.delete',
    ],

    'requiredRoles' => [],

    'rateLimits' => [
        'UsersController::delete' => [
            'limit'   => 5,
            'minutes' => 1,
        ],
    ],

    'allowedHttpVerbs' => [
        'UsersController::index'  => ['GET'],
        'UsersController::show'   => ['GET'],
        'UsersController::create' => ['POST'],
        'UsersController::update' => ['PUT', 'PATCH'],
        'UsersController::delete' => ['DELETE'],
    ],
];
