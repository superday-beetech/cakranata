<?php

declare(strict_types=1);

namespace Modules\Roles;

use App\Libraries\DynamicRouterBase;

class DynamicRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Roles';

    protected string $moduleNamespace = 'Modules\\Roles\\Controllers';

    protected string $defaultController = 'RolesController';
}
