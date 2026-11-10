<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

use RuntimeException;

/**
 * Pemindaian modul dan sinkronisasi route_registry.
 * Dipakai Spark command / deploy hook. DynamicRouterBase::scanAllModules() dan
 * ::syncRouteRegistry() tetap tersedia sebagai wrapper kompatibilitas.
 */
final class RouteRegistrySynchronizer
{
    public static function scanAllModules(): array
    {
        $result = [];

        foreach (self::moduleFolders() as $module) {
            // Dua cakupan: controller web dan controller API (Controllers/API/V1).
            // Registry menyimpan keduanya; tanpa cakupan API, sync akan menandai endpoint API
            // sebagai 'missing' dan mencabut persetujuannya.
            $scan  = ['controllerClasses' => [], 'discoveredMethods' => []];
            $dupes = [];

            foreach (["Modules\\{$module}\\Controllers", "Modules\\{$module}\\Controllers\\API\\V1"] as $namespace) {
                $part = (new ControllerScanner($module, $namespace, false))->scanWithoutCache();

                foreach ($part['controllerClasses'] as $short => $class) {
                    if (isset($scan['controllerClasses'][$short])) {
                        $dupes[$short] = true;
                        log_message('critical', "Nama controller sama di cakupan web dan API: {$module}::{$short}. Dilewati.");

                        continue;
                    }

                    $scan['controllerClasses'][$short] = $class;
                }

                $scan['discoveredMethods'] = array_merge($scan['discoveredMethods'], $part['discoveredMethods']);
            }

            foreach (array_keys($dupes) as $short) {
                unset($scan['controllerClasses'][$short]);
                $scan['discoveredMethods'] = array_values(array_filter(
                    $scan['discoveredMethods'],
                    static fn(string $e): bool => ! str_starts_with($e, $short . '::')
                ));
            }

            $scan['discoveredMethods'] = array_values(array_unique($scan['discoveredMethods']));

            if ($scan['controllerClasses'] === []) {
                continue;
            }

            $controllers = [];

            foreach ($scan['controllerClasses'] as $short => $class) {
                $controllers[$short] = ['class' => $class, 'methods' => []];
            }

            foreach ($scan['discoveredMethods'] as $endpoint) {
                [$controller, $method] = explode('::', $endpoint, 2);
                $controllers[$controller]['methods'][] = $method;
            }

            $result[$module] = [
                'method_count'       => count($scan['discoveredMethods']),
                'controllers'        => $controllers,
                'discovered_methods' => $scan['discoveredMethods'],
            ];
        }

        return $result;
    }

    private static function moduleFolders(): array
    {
        $folders = [];

        foreach (glob(ROOTPATH . 'modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);

            if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) === 1) {
                $folders[] = $name;
            }
        }

        sort($folders);

        return $folders;
    }

    public static function syncRouteRegistry(): int
    {
        $db         = \Config\Database::connect();
        $scanned    = self::scanAllModules();
        $discovered = [];

        foreach ($scanned as $module => $data) {
            foreach ($data['controllers'] as $controller => $info) {
                foreach ($info['methods'] as $method) {
                    $endpoint = $controller . '::' . $method;

                    $discovered[$module . '|' . $endpoint] = compact('module', 'controller', 'method', 'endpoint');
                }
            }
        }

        $existing = [];

        foreach ($db->table('route_registry')->select('id, module, endpoint, scan_status')->get()->getResultArray() as $row) {
            $existing[$row['module'] . '|' . $row['endpoint']] = $row;
        }

        $insert = $reappeared = $missing = [];

        foreach ($discovered as $key => $d) {
            if (! isset($existing[$key])) {
                $insert[] = $d + [
                    'is_discovered' => 1,
                    'is_routable'   => 0,
                    'is_allowed'    => 0,
                    'scan_status'   => 'new',
                ];
            } elseif (($existing[$key]['scan_status'] ?? '') === 'missing') {
                $reappeared[] = (int) $existing[$key]['id'];
            }
        }

        // Tandai hilang hanya untuk modul yang berhasil dipindai (hindari kehilangan
        // persetujuan massal bila scan gagal sementara).
        foreach ($existing as $key => $row) {
            if (
                ! isset($discovered[$key])
                && isset($scanned[$row['module']])
                && ($row['scan_status'] ?? '') !== 'missing'
            ) {
                $missing[] = (int) $row['id'];
            }
        }

        $db->transStart();

        foreach (array_chunk($insert, 200) as $chunk) {
            $db->table('route_registry')->ignore(true)->insertBatch($chunk);
        }

        if ($reappeared !== []) {
            $db->table('route_registry')->whereIn('id', $reappeared)->update([
                'is_discovered' => 1,
                'is_routable' => 0,
                'is_allowed' => 0,
                'scan_status' => 'new',
            ]);
        }

        if ($missing !== []) {
            $db->table('route_registry')->whereIn('id', $missing)->update([
                'is_discovered' => 0,
                'is_routable' => 0,
                'is_allowed' => 0,
                'scan_status' => 'missing',
            ]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('syncRouteRegistry gagal menyimpan route_registry.');
        }

        return count($insert) + count($reappeared);
    }
}
