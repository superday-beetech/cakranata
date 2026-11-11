<?php

declare(strict_types=1);

return [
    // ============================================================
    // Allowed Methods (WAJIB — tanpa ini, semua 404)
    // ============================================================
    'allowedMethods' => [
        // AuthController (API V1)
        'AuthController::index',
        'AuthController::masuk',
        'AuthController::login',
        'AuthController::me',
        'AuthController::logout',
        // 'AuthController::action' → JANGAN — ini untuk form web (RedirectResponse),
        //                              bukan API. Form login web pakai LoginController.

        // AuthTokenController (API V1) — asumsi method
        'AuthTokenController::index',
        'AuthTokenController::login',
    ],

    // ============================================================
    // Public Methods (tanpa auth)
    // ============================================================
    'publicMethods' => [
        'AuthController::index',
        'AuthController::masuk',
        'AuthController::login',
        // 'AuthController::me' → butuh auth (JANGAN public)
        // 'AuthController::logout' → butuh auth (JANGAN public)

        'AuthTokenController::index',
        'AuthTokenController::login',
    ],

    // ============================================================
    // Rate Limits (SESUAIKAN dengan method API V1)
    // ============================================================
    'rateLimits' => [
        // Login (sangat sensitif)
        'AuthController::login' => [
            'limit'       => 5,
            'minutes'     => 1,
            'fail_closed' => true,
        ],

        // Token login
        'AuthTokenController::login' => [
            'limit'       => 10,
            'minutes'     => 1,
            'fail_closed' => true,
        ],
    ],
];
