<?php

declare(strict_types=1);

namespace Modules\Organization\Routers;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Organization';

    protected string $moduleNamespace = 'Modules\\Organization\\Controllers';

    protected string $defaultController = 'OrganizationController';

    protected array $requiredRoles = [];

    protected array $requiredPermissions = [];

    /**
     * Endpoint yang dapat diakses tanpa login.
     */
    // protected array $publicMethods = [
    //     'LoginController::index',
    //     'LoginController::login',
    //     'LoginController::action',
    //     'RegisterController::index',
    //     'RegisterController::action',
    //     'MagicController::login',
    // ];

    /**
     * Endpoint yang dikecualikan dari pemeriksaan HTTP verb.
     */
    protected array $httpVerbExclusions = [
        'LoginController::index',
        'LoginController::login',
        'LoginController::action',
        'LoginController::logout',
        'MagicController::login',
        'RegisterController::index',
        'RegisterController::action',
    ];
}
