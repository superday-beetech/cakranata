<?php

return [
    'requiredPermissions' => [],
    'allowedHttpVerbs' => [
        'LoginController::index' => ['GET', 'POST'],
        'LoginController::action' => ['GET', 'POST'],
    ],
];
