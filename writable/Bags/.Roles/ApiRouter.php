<?php

declare(strict_types=1);

namespace Modules\Roles;

use App\Libraries\DynamicRouterBase;

class ApiRouter extends DynamicRouterBase
{
    protected string $moduleFolder = 'Roles';

    protected string $moduleNamespace = 'Modules\\Roles\\Controllers\\API\\V1';

    protected string $defaultController = 'RolesController';

    public function route($seg1 = '', $seg2 = null, ...$params)
    {
        // Jika $seg2 langsung berupa angka (ID, contoh: "15") tanpa ada nama method di depannya,
        // arahkan otomatis ke method "getVerbs" dan jadikan $seg2 sebagai parameter.
        if ($seg2 !== null && ctype_digit((string) $seg2)) {
            $id = $seg2;

            // Masukkan ID ke awal array $params
            array_unshift($params, $id);
        }
        // Jika $seg2 berisi gabungan method dan ID (contoh: "getVerbs/15")
        elseif ($seg2 !== null && str_contains((string) $seg2, '/')) {
            $parts = explode('/', trim((string) $seg2, '/'));
            $seg2  = array_shift($parts);
            $params = array_merge($parts, $params);
        }

        // Teruskan ke router induk
        return parent::route($seg1, $seg2, ...$params);
    }
}
