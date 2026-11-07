<?php

declare(strict_types=1);

namespace Modules\Organization\Routers;

use App\Libraries\DynamicRouterBase;

class ApiRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Organization';

    protected string $moduleNamespace = 'Modules\\Organization\\Controllers\\API\\V1';

    protected string $defaultController = 'OrganizationsController';


    public function route($seg1 = '', $seg2 = null, ...$params)
    {
        if ($seg2 !== null && ctype_digit((string) $seg2)) {
            array_unshift($params, $seg2);
            $seg2 = null;
        } elseif ($seg2 !== null && str_contains((string) $seg2, '/')) {
            $parts = explode('/', trim((string) $seg2, '/'));

            $seg2 = array_shift($parts);
            $params = array_merge($parts, $params);
        }

        return parent::route($seg1, $seg2, ...$params);
    }
}
