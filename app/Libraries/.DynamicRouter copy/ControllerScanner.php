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

        if ($this->useCache && ENVIRONMENT === 'production') {
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

        if ($this->useCache && ENVIRONMENT === 'production') {
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
            . 'Modules/'
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

            $relativePath = substr(
                $file->getPathname(),
                strlen($controllerPath)
            );

            $relativeClass = str_replace(
                ['/', '\\'],
                '\\',
                substr($relativePath, 0, -4)
            );

            $className = $baseNamespace . '\\' . $relativeClass;

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
}
