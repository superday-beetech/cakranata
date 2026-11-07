<?php

return [
    'allowedMethods' => [
        'TestController::index',
    ],
    'requiredPermissions' => [],
    'allowedHttpVerbs' => [
        'TestController::index' => ['GET', 'POST'],
    ],
];
