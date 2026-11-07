<?php

namespace Modules\Test\Controllers;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder      = 'Test';
    protected string $moduleNamespace   = 'Modules\\Test\\Controllers';
    protected string $defaultController = 'TestController';
}
