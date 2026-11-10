<?php

namespace App\Traits;

use Config\Database;

trait ModulesMethodsCacheTrait
{
    protected function loadModulesMethodsFromCache(string $module): array
    {
        $modules = $module ? $module : 'front';
        // log_message('info', $modules);
        $cacheKey = 'modules_methods_' . $modules;

        if ($cached = cache($cacheKey)) {
            if (app_setting('log_info')) {
                log_message('info', '[Traits] Load Modules From Cache::' . $modules);
            }

            return $cached;
        }

        /*      $db   = Database::connect();
             $rows = $db->table('modules_methods')
                 ->where('module', $module)
                 ->get()->getResultArray();
 */
        $allowedMethods      = [];
        $requiredPermissions = [];
        $requiredRoles       = [];
        /*
                foreach ($rows as $row) {
                    if (!empty($row['method'])) {
                        $allowedMethods[] = $row['method'];
                    }
                    if (!empty($row['required_permission'])) {
                        $requiredPermissions[$row['method']] = $row['required_permission'];
                    }
                    if (!empty($row['required_role'])) {
                        $requiredRoles[] = $row['required_role'];
                    }
                }
                if (app_setting('log_info')) {
                    log_message('info', '[Traits] Load Modules From Database::' . $modules);
                }
         */
        $result = [
            'allowedMethods'      => array_unique($allowedMethods),
            'requiredPermissions' => $requiredPermissions,
            'requiredRoles'       => array_unique($requiredRoles),
        ];

        cache()->save($cacheKey, $result, 300); // 5 menit TTL

        return $result;
    }

    /**
     * Hapus cache untuk module tertentu
     */
    public function clearModulesMethodCache(string $module): void
    {
        cache()->delete('modules_methods_' . $module);
    }

    /**
     * Ambil daftar method, permission, dan role dari tabel modules_methods.
     * Termasuk cache agar efisien.
     */
    protected function loadModuleMethodsFromDatabase(string $module): array
    {
        log('debug', $module);
        $modules  = $module ? $module : 'front';
        $cacheKey = 'modules_methods_' . $modules;

        // Cek cache
        if ($cached = cache($cacheKey)) {
            return $cached;
        }

        // Query DB
        // $db   = \Config\Database::connect();
        // $rows = $db->table('modules_methods')
        //     ->where('module', $module)
        //     ->get()->getResultArray();

        // Olah hasil
        $allowedMethods      = [];
        $requiredPermissions = [];
        $requiredRoles       = [];

        /* foreach ($rows as $row) {
            if (!empty($row['method'])) {
                $allowedMethods[] = $row['method'];
            }
            if (!empty($row['required_permission'])) {
                $requiredPermissions[$row['method']] = $row['required_permission'];
            }
            if (!empty($row['required_role'])) {
                $requiredRoles[] = $row['required_role'];
            }
        } */

        $result = [
            'allowedMethods'      => array_unique($allowedMethods),
            'requiredPermissions' => $requiredPermissions,
            'requiredRoles'       => array_unique($requiredRoles),
        ];

        cache()->save($cacheKey, $result, 300); // TTL 5 menit

        return $result;
    }
}
