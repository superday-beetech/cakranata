<?php

declare(strict_types=1);

namespace Modules\Rbac\Routers;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Rbac';

    protected string $moduleNamespace = 'Modules\\Rbac\\Controllers';

    protected string $defaultController = 'RbacController';
}
