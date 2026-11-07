<?php

declare(strict_types=1);

namespace Modules\RouteRegistry\Services;

use ReflectionClass;
use ReflectionMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class ModuleScannerService
{
    /**
     * Scan seluruh module tanpa mengubah database.
     *
     * @return array<int, array{
     *     module: string,
     *     controller: string,
     *     method: string,
     *     endpoint: string,
     *     class: string
     * }>
     */
    private array $ignoredControllers = [
        'MagicController',
        'ApiRouter',
        'DynamicRouter',
    ];

    public function scan(): array
    {
        $modulesPath = ROOTPATH . 'Modules';

        if (! is_dir($modulesPath)) {
            return [];
        }

        $results = [];

        foreach (new \DirectoryIterator($modulesPath) as $moduleDir) {
            if ($moduleDir->isDot() || ! $moduleDir->isDir()) {
                continue;
            }

            $module = $moduleDir->getFilename();

            // Hindari memindai module registry sendiri.
            /* if (strcasecmp($module, 'RouteRegistry') === 0) {
                continue;
            } */

            /*  if (strcasecmp($module, 'Auth') === 0) {
                continue;
            }
 */


            $controllerPath = $moduleDir->getPathname()
                . DIRECTORY_SEPARATOR . 'Controllers';

            if (! is_dir($controllerPath)) {
                continue;
            }

            $namespace = 'Modules\\' . $module . '\\Controllers';

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $controllerPath,
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (
                    ! $file->isFile()
                    || strtolower($file->getExtension()) !== 'php'
                ) {
                    continue;
                }

                // pp($file);
                // exit;
                $controllerName = $file->getBasename('.php');

                if (in_array($controllerName, $this->ignoredControllers, true)) {
                    continue;
                }


                $relativePath = substr(
                    $file->getPathname(),
                    strlen($controllerPath) + 1
                );

                $relativeClass = str_replace(
                    DIRECTORY_SEPARATOR,
                    '\\',
                    substr($relativePath, 0, -4)
                );

                // Abaikan router jika suatu saat diletakkan di Controllers.
                if (
                    preg_match(
                        '/(?:^|\\\\)(?:DynamicRouter|ApiRouter|BaseController)$/i',
                        $relativeClass
                    )
                ) {
                    continue;
                }

                $className = $namespace . '\\' . $relativeClass;

                if (! class_exists($className)) {
                    continue;
                }

                try {
                    $reflection = new \ReflectionClass($className);
                } catch (\ReflectionException) {
                    continue;
                }

                if (
                    ! $reflection->isInstantiable()
                    || $reflection->isAbstract()
                ) {
                    continue;
                }

                foreach (
                    $reflection->getMethods(
                        \ReflectionMethod::IS_PUBLIC
                    ) as $method
                ) {
                    if (
                        $method->getDeclaringClass()->getName()
                        !== $reflection->getName()
                        || $method->isStatic()
                        || $method->isConstructor()
                        || $method->isDestructor()
                        || str_starts_with($method->getName(), '_')
                    ) {
                        continue;
                    }


                    $controller = $reflection->getShortName();
                    $methodName = $method->getName();
                    $renderInfo = $this->detectRender($method);

                    // Normalisasi module dan controller.
                    // Bentuk kandidat permission berdasarkan aksi.
                    $moduleKey = strtolower($module);

                    $controllerKey = strtolower(
                        preg_replace('/Controller$/i', '', $controller)
                    );

                    $moduleControllerKey = strtolower(
                        preg_replace('/Controller$/i', '', $module)
                    );

                    $permissionParts = [$moduleKey];

                    if ($controllerKey !== $moduleControllerKey) {
                        $permissionParts[] = $controllerKey;
                    }

                    $permissionParts[] = strtolower($methodName);

                    // $permission = implode('.', $permissionParts);
                    $normalizedParts = $permissionParts;
                    $lastIndex = array_key_last($normalizedParts);

                    $normalizedParts[$lastIndex] = $this->normalizePermissionAction(
                        $methodName
                    );
                    $permission_normalize = implode('.', $normalizedParts);
                    $is_menu_url = $this->buildMenuUrl([
                        'module'     => $module,
                        'controller' => $controller,
                        'method'     => $methodName,
                    ]);
                    $menu_url = $renderInfo['has_interface'] ? $is_menu_url : null;
                    $results[] = [
                        'module'     => $module,
                        'controller' => $controller,
                        'method'     => $methodName,
                        'endpoint'   => $controller . '::' . $methodName,
                        'namespace'      => $className,
                        'permission' => $permission_normalize,
                        'url_route' => $menu_url,
                        'has_interface' => $renderInfo['has_interface'],
                    ];
                }
            }
        }

        usort(
            $results,
            static fn(array $a, array $b): int => [
                $a['module'],
                $a['controller'],
                $a['method'],
            ] <=> [
                $b['module'],
                $b['controller'],
                $b['method'],
            ]
        );

        return $results;
    }

    private function detectRender(\ReflectionMethod $method): array
    {
        $filePath = $method->getFileName();

        if ($filePath === false || ! is_file($filePath)) {
            return ['has_interface' => false];
        }

        $lines = file($filePath);

        if ($lines === false) {
            return ['has_interface' => false];
        }

        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $tokens = token_get_all('<?php ' . $source);
        $total  = count($tokens);

        for ($i = 0; $i < $total; $i++) {
            if (
                ! is_array($tokens[$i])
                || $tokens[$i][0] !== T_VARIABLE
                || $tokens[$i][1] !== '$this'
            ) {
                continue;
            }

            $j = $i + 1;

            while (
                $j < $total
                && is_array($tokens[$j])
                && in_array($tokens[$j][0], [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ], true)
            ) {
                $j++;
            }

            if (
                $j >= $total
                || ! is_array($tokens[$j])
                || $tokens[$j][0] !== T_OBJECT_OPERATOR
            ) {
                continue;
            }

            $j++;

            while (
                $j < $total
                && is_array($tokens[$j])
                && in_array($tokens[$j][0], [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ], true)
            ) {
                $j++;
            }

            if (
                $j < $total
                && is_array($tokens[$j])
                && $tokens[$j][0] === T_STRING
                && in_array(strtolower($tokens[$j][1]), [
                    'render',
                    'view',
                ], true)
            ) {
                return ['has_interface' => true];
            }
        }

        return ['has_interface' => false];
    }

    private function normalizePermissionActions(string $method): string
    {
        $method = strtolower($method);

        return match ($method) {
            // Membaca data atau membuka form edit.
            'index', 'show', 'detail', 'view', 'edit' => 'view',

            // Membuat data.
            'create', 'store' => 'create',

            // Memperbarui data.
            'update', 'updateorder' => 'update',

            // Menghapus data.
            'delete', 'destroy' => 'delete',

            // Aksi khusus tetap menggunakan nama method.
            default => $method,
        };
    }

    private function normalizePermissionAction(string $method): string
    {
        // Pisahkan camelCase: updateStatus -> update Status
        $method = strtolower(
            preg_replace('/(?<!^)[A-Z]/', ' $0', $method)
        );

        $words = preg_split('/\s+/', trim($method));

        if ($this->containsAny($words, ['view', 'index', 'show', 'detail', 'dashboard', 'edit'])) {
            return 'view';
        }

        if ($this->containsAny($words, ['delete', 'destroy', 'remove'])) {
            return 'delete';
        }

        if ($this->containsAny($words, ['create', 'store'])) {
            return 'create';
        }

        if ($this->containsAny($words, ['update', 'action', 'save'])) {
            return 'update';
        }

        return implode('', $words);
    }

    private function containsAny(array $words, array $keywords): bool
    {
        return count(array_intersect($words, $keywords)) > 0;
    }

    /**
     * Bentuk URL menu dari metadata endpoint hasil scan.
     */
    private function buildMenuUrl(array $row): ?string
    {
        $toSlug = static function (string $value): string {
            $value = trim($value);
            $value = preg_replace('/Controller$/i', '', $value);
            $value = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value);

            return strtolower($value);
        };

        $module     = $toSlug((string) ($row['module'] ?? ''));
        $controller = $toSlug((string) ($row['controller'] ?? ''));
        $method     = strtolower(trim((string) ($row['method'] ?? '')));

        if ($module === '' || $controller === '') {
            return null;
        }

        // Jika controller sama dengan module, method menjadi subpath.
        if ($module === $controller) {
            return $method === 'index'
                ? $module
                : $module . '/' . $method;
        }

        return $module . '/' . $controller;
    }

    private function detectRenderssss(\ReflectionMethod $method): array
    {
        $filePath = $method->getFileName();

        if ($filePath === false || ! is_file($filePath)) {
            return ['has_interface' => false];
        }

        $lines = file($filePath);

        if ($lines === false) {
            return ['has_interface' => false];
        }

        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $tokens = token_get_all('<?php ' . $source);
        $total  = count($tokens);

        for ($i = 0; $i < $total; $i++) {
            if (
                ! is_array($tokens[$i])
                || $tokens[$i][0] !== T_VARIABLE
                || $tokens[$i][1] !== '$this'
            ) {
                continue;
            }

            $j = $i + 1;

            while (
                $j < $total
                && is_array($tokens[$j])
                && in_array($tokens[$j][0], [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ], true)
            ) {
                $j++;
            }

            if (
                $j >= $total
                || ! is_string($tokens[$j])
                || $tokens[$j] !== '->'
            ) {
                continue;
            }

            $j++;

            while (
                $j < $total
                && is_array($tokens[$j])
                && in_array($tokens[$j][0], [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ], true)
            ) {
                $j++;
            }

            if (
                $j < $total
                && is_array($tokens[$j])
                && $tokens[$j][0] === T_STRING
                && in_array(strtolower($tokens[$j][1]), [
                    'render',
                    'view',
                ], true)
            ) {
                return ['has_interface' => true];
            }
        }

        return ['has_interface' => false];
    }

    private function detectRenderLama(string $filePath): array
    {
        $source = file_get_contents($filePath);

        if ($source === false) {
            return [
                'uses_render'  => false,
                'render_count' => 0,
            ];
        }

        $tokens = token_get_all($source);
        $count  = 0;
        $total  = count($tokens);

        for ($i = 0; $i < $total; $i++) {
            if (
                ! is_array($tokens[$i])
                || $tokens[$i][0] !== T_VARIABLE
                || $tokens[$i][1] !== '$this'
            ) {
                continue;
            }

            $j = $i + 1;

            while (
                $j < $total
                && is_array($tokens[$j])
                && in_array($tokens[$j][0], [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ], true)
            ) {
                $j++;
            }

            if (
                $j >= $total
                || ! is_string($tokens[$j])
                || $tokens[$j] !== '->'
            ) {
                continue;
            }

            $j++;

            while (
                $j < $total
                && is_array($tokens[$j])
                && in_array($tokens[$j][0], [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ], true)
            ) {
                $j++;
            }

            if (
                $j < $total
                && is_array($tokens[$j])
                && $tokens[$j][0] === T_STRING
                && strtolower($tokens[$j][1]) === 'render'
            ) {
                $count++;
            }
        }

        return [
            'uses_render'  => $count > 0,
            'render_count' => $count,
        ];
    }

    public function scanOld(): array
    {
        $modulesPath = ROOTPATH . 'modules';

        if (! is_dir($modulesPath)) {
            return [];
        }

        $results = [];

        foreach (new \DirectoryIterator($modulesPath) as $moduleDir) {
            if ($moduleDir->isDot() || ! $moduleDir->isDir()) {
                continue;
            }

            $module = $moduleDir->getFilename();

            // Hindari memindai module pengelola registry sendiri.
            if (strcasecmp($module, 'RouteRegistry') === 0) {
                continue;
            }

            $controllerPath = $moduleDir->getPathname()
                . DIRECTORY_SEPARATOR . 'Controllers';

            if (! is_dir($controllerPath)) {
                continue;
            }

            $namespace = 'Modules\\' . $module . '\\Controllers';

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $controllerPath,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (
                    ! $file->isFile()
                    || strtolower($file->getExtension()) !== 'php'
                ) {
                    continue;
                }

                $relativePath = substr(
                    $file->getPathname(),
                    strlen($controllerPath) + 1
                );

                $relativeClass = str_replace(
                    DIRECTORY_SEPARATOR,
                    '\\',
                    substr($relativePath, 0, -4)
                );

                // Jangan memindai DynamicRouter sebagai endpoint.
                if (
                    $relativeClass === 'DynamicRouter'
                    || str_ends_with(
                        $relativeClass,
                        '\\DynamicRouter'
                    )
                ) {
                    continue;
                }

                $className = $namespace . '\\' . $relativeClass;

                if (! class_exists($className)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($className);
                } catch (\ReflectionException) {
                    continue;
                }

                if (
                    ! $reflection->isInstantiable()
                    || $reflection->isAbstract()
                ) {
                    continue;
                }

                foreach (
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
                    as $method
                ) {
                    if (
                        $method->getDeclaringClass()->getName()
                        !== $reflection->getName()
                        || $method->isStatic()
                        || $method->isConstructor()
                        || $method->isDestructor()
                        || str_starts_with($method->getName(), '_')
                    ) {
                        continue;
                    }

                    $controller = $reflection->getShortName();
                    $methodName = $method->getName();

                    $results[] = [
                        'module'     => $module,
                        'controller' => $controller,
                        'method'     => $methodName,
                        'endpoint'   => $controller . '::' . $methodName,
                        'class'      => $className,

                    ];
                }
            }
        }

        usort(
            $results,
            static fn(array $a, array $b): int =>
            [
                $a['module'],
                $a['controller'],
                $a['method'],
            ] <=> [
                $b['module'],
                $b['controller'],
                $b['method'],
            ]
        );

        return $results;
    }
}
