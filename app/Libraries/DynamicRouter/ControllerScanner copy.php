<?php

namespace App\Libraries\DynamicRouter;

use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class ControllerScanner
{
    public function __construct(
        private string $moduleFolder,
        private string $moduleNamespace,
        private bool $useCache = true,
        private int $cacheTtl = 86400,
    ) {}

    public function scan(): array
    {
        $cacheKey = $this->getCacheKey();

        if ($this->useCache  && $this->isCacheEnabled()) {
            $cached = cache()->get($cacheKey);

            if (
                is_array($cached)
                && isset(
                    $cached['allowedControllers'],
                    $cached['discoveredMethods'],
                    $cached['controllerClasses']
                )
            ) {
                return $cached;
            }
        }

        $result = $this->scanDirectory();

        if ($this->useCache && $this->isCacheEnabled()) {
            cache()->save($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    public function scanWithoutCache(): array
    {
        return $this->scanDirectory();
    }

    public function clearCache(): bool
    {
        return cache()->delete($this->getCacheKey());
    }

    private function scanDirectory(): array
    {
        $controllerPath = ROOTPATH
            . 'modules/'
            . trim($this->moduleFolder, '/\\')
            . '/Controllers/';

        $baseNamespace = trim($this->moduleNamespace, '\\');

        $result = [
            'allowedControllers' => [],
            'discoveredMethods' => [],
            'controllerClasses' => [],
        ];

        if (
            $this->moduleFolder === ''
            || $baseNamespace === ''
            || ! is_dir($controllerPath)
        ) {
            return $result;
        }

        $controllers = [];
        $methods = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $controllerPath,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            /* $relativePath = substr($file->getPathname(), strlen($controllerPath));
            $relativeClass = str_replace(['/', '\\'], '\\', substr($relativePath, 0, -4));
            $className = $baseNamespace . '\\' . $relativeClass; */

            $relativePath = substr($file->getPathname(), strlen($controllerPath));
            $relativeClass = str_replace(
                ['/', '\\'],
                '\\',
                substr($relativePath, 0, -4)
            );

            $relativeClass = preg_replace(
                '/^(API|Api)\\\\V1\\\\/i',
                '',
                $relativeClass
            );

            $className = $baseNamespace . '\\' . $relativeClass;

            /* log_message('info', 'SCANNER CLASS CHECK: ' . json_encode([
                'file'      => $file->getPathname(),
                'className' => $className,
                'exists'    => class_exists($className),
            ])); */
            if (! class_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
            } catch (ReflectionException) {
                continue;
            }

            if (
                ! $reflection->isInstantiable()
                || $reflection->isAbstract()
                || $reflection->getShortName() === 'DynamicRouter'
            ) {
                continue;
            }

            $shortName = $reflection->getShortName();
            $seen = [];
            $duplicates = [];
            // di dalam loop, setelah $shortName didapat:
            if (isset($seen[$shortName]) && $seen[$shortName] !== $className) {
                $duplicates[$shortName] = true;
                log_message('critical', "Nama controller ganda '{$shortName}': {$seen[$shortName]} dan {$className}");
                continue;
            }
            $seen[$shortName] = $className;

            // setelah loop:
            foreach (array_keys($duplicates) as $dup) {
                unset($result['controllerClasses'][$dup]);
                $controllers = array_values(array_diff($controllers, [$dup]));
                $methods     = array_values(array_filter($methods, static fn($e) => ! str_starts_with($e, $dup . '::')));
            }

            $controllers[] = $shortName;
            $result['controllerClasses'][$shortName] = $className;

            foreach (
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method
            ) {
                if (
                    $method->getDeclaringClass()->getName()
                    !== $reflection->getName()
                    || $method->isStatic()
                    || $method->isConstructor()
                    || str_starts_with($method->getName(), '_')
                ) {
                    continue;
                }

                $methods[] = $shortName . '::' . $method->getName();
            }
        }

        $result['allowedControllers'] = array_values(
            array_unique($controllers)
        );

        $result['discoveredMethods'] = array_values(
            array_unique($methods)
        );

        return $result;
    }

    private function getCacheKey(): string
    {
        $identity = $this->moduleFolder . '|' . $this->moduleNamespace;

        return 'dynamic_router_scan_' . md5($identity);
    }

    private function isCacheEnabled(): bool
    {
        try {
            $value = service('settings')->get('App.cacheRouter');

            if ($value !== null) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        } catch (\Throwable $e) {
            // Settings tidak tersedia, lanjut ke config.
        }

        try {
            $config = config('App');

            if (isset($config->cacheDynamic)) {
                return (bool) $config->cacheDynamic;
            }
        } catch (\Throwable $e) {
            // Config tidak tersedia.
        }

        return false;
    }
}
