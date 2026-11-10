<?php

declare(strict_types=1);

/**
 * Simpan di: app/Commands/DebugScan.php
 *
 * Pemakaian (dari folder proyek):
 *   php spark debug:scan Auth          -> diagnosis scanner untuk modul Auth
 *   php spark debug:scan Auth --sync   -> lalu jalankan syncRouteRegistry()
 *
 * Alat bantu pengembangan: hapus atau jangan di-deploy ke production.
 */

namespace App\Commands;

use App\Libraries\DynamicRouter\ControllerScanner;
use App\Libraries\DynamicRouterBase;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Controller;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;

class DebugScan extends BaseCommand
{
    protected $group       = 'Debug';
    protected $name        = 'debug:scan';
    protected $description = 'Diagnosis scanner controller modul dan (opsional) sinkron route_registry.';
    protected $usage       = 'debug:scan <Module> [--sync]';
    protected $arguments   = ['Module' => 'Nama folder modul, contoh: Auth'];
    protected $options     = ['--sync' => 'Jalankan syncRouteRegistry() setelah scan'];

    public function run(array $params)
    {
        if (ENVIRONMENT === 'production') {
            CLI::error('Perintah ini dinonaktifkan di production.');

            return;
        }

        $module = $params[0] ?? 'Auth';

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $module) !== 1) {
            CLI::error('Nama modul tidak valid.');

            return;
        }

        $namespace = "Modules\\{$module}\\Controllers";
        $dir       = rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Controllers';

        CLI::write("Modul      : {$module}", 'yellow');
        CLI::write("Namespace  : {$namespace}");
        CLI::write("Folder     : {$dir}");
        CLI::newLine();

        if (! is_dir($dir)) {
            CLI::error('Folder Controllers tidak ditemukan.');

            return;
        }

        // 1. Diagnosis per file: mengapa sebuah controller lolos atau tidak.
        CLI::write('FILE -> CLASS KANDIDAT', 'yellow');

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $files    = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        foreach ($files as $path) {
            $relative = substr($path, strlen($dir) + 1);
            $class    = $namespace . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', substr($relative, 0, -4));

            try {
                if (! class_exists($class)) {
                    CLI::write("  {$relative}", 'red');
                    CLI::write("    kandidat : {$class}");
                    CLI::write('    status   : class TIDAK ditemukan (namespace tidak cocok dengan lokasi file / autoload belum terdaftar)', 'red');

                    continue;
                }

                $ref    = new ReflectionClass($class);
                $notes  = [];
                $notes[] = $ref->isInstantiable() ? 'instantiable' : 'TIDAK instantiable';
                $notes[] = $ref->isSubclassOf(Controller::class) ? 'turunan Controller' : 'BUKAN turunan Controller';

                if ($ref->isSubclassOf(DynamicRouterBase::class)) {
                    $notes[] = 'router (dilewati)';
                }

                $ok = $ref->isInstantiable() && $ref->isSubclassOf(Controller::class) && ! $ref->isSubclassOf(DynamicRouterBase::class);

                CLI::write("  {$relative}", $ok ? 'green' : 'red');
                CLI::write("    kandidat : {$class}");
                CLI::write('    status   : ' . implode(', ', $notes));
            } catch (Throwable $e) {
                CLI::write("  {$relative}", 'red');
                CLI::write('    error    : ' . $e->getMessage(), 'red');
            }
        }

        // 2. Hasil scanner sebenarnya.
        CLI::newLine();
        CLI::write('HASIL SCANNER', 'yellow');

        $scan = (new ControllerScanner($module, $namespace, false))->scanWithoutCache();

        CLI::write('Controller : ' . ($scan['allowedControllers'] === [] ? '(kosong)' : implode(', ', $scan['allowedControllers'])));
        CLI::write('Endpoint   : ' . count($scan['discoveredMethods']));

        foreach ($scan['discoveredMethods'] as $endpoint) {
            CLI::write("  {$endpoint}");
        }

        CLI::newLine();
        CLI::write('Cek juga writable/logs untuk "Scanner gagal memuat" atau "Nama controller ganda".', 'light_gray');

        // 3. Sinkron registry (opsional).
        if (CLI::getOption('sync')) {
            CLI::newLine();

            try {
                $count = DynamicRouterBase::syncRouteRegistry();
                CLI::write("syncRouteRegistry(): {$count} baris baru / muncul kembali.", 'green');
            } catch (Throwable $e) {
                CLI::error('syncRouteRegistry gagal: ' . $e->getMessage());
            }
        }
    }
}
