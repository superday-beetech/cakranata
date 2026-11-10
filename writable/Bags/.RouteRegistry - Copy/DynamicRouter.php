<?php

namespace Modules\RouteRegistry;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder      = 'RouteRegistry';
    protected string $moduleNamespace   = 'Modules\\RouteRegistry\\Controllers';
    protected string $defaultController = 'RouteRegistryController';
}
