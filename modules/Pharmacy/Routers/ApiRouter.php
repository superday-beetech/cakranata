<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Routers;

use App\Libraries\DynamicRouterBase;

class ApiRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Pharmacy';

    protected string $moduleNamespace = 'Modules\\Pharmacy\\Controllers\\API\\V1';

    protected string $defaultController = 'PharmacyApiController';
    protected array $customActions = [
        'PrintTemplatesController' => ['render', 'renderPdf'],
        // atau kalau pakai short name:
        'PrintTemplates' => ['render', 'renderPdf'],
    ];
}
