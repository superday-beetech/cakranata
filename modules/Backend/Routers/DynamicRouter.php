<?php

namespace Modules\Backend\Routers;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Backend';

    protected string $moduleNamespace = 'Modules\\Backend\\Controllers';

    protected string $defaultController = 'BackendController';

    /**
     * Method yang dikecualikan dari pemeriksaan HTTP verb database.
     *
     * Format: Controller::method
     */

    protected array $httpVerbExclusions = [
        'SettingsController::index',
        // 'NavigationController::index',
    ];
}
