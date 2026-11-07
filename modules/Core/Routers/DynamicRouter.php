<?php

namespace Modules\Core\Routers;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Core';

    protected string $moduleNamespace = 'Modules\\Core\\Controllers';

    protected string $defaultController = 'CoreController';

    /**
     * Method yang dikecualikan dari pemeriksaan HTTP verb database.
     *
     * Format: Controller::method
     */

    protected array $httpVerbExclusions = [
        // 'SettingsController::index',
        // 'NavigationController::index',
    ];
}
