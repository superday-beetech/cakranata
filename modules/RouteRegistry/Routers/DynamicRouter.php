<?php

declare(strict_types=1);

namespace Modules\RouteRegistry\Routers;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'RouteRegistry';

    protected string $moduleNamespace = 'Modules\\RouteRegistry\\Controllers';

    protected string $defaultController = 'RouteRegistryController';
}
