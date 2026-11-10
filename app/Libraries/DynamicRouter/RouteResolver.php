<?php

namespace App\Libraries\DynamicRouter;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;

class RouteResolver
{
    public function __construct(
        private IncomingRequest $request,
        private MethodPolicy $methodPolicy,
        private array $controllerClasses,
        private string $defaultController,
    ) {}

    public function normalizeControllerName(string $controller): string
    {
        return NameNormalizer::kebab($controller);
    }

    public function resolveEffectiveMethod(
        string $controller,
        string $method
    ): string {
        if (! $this->request->isAJAX()) {
            return $method;
        }

        $ajaxMethod = $method . 'Ajax';

        return $this->methodPolicy->isDiscovered($controller, $ajaxMethod)
            ? $ajaxMethod
            : $method;
    }

    public function isAjax(): bool
    {
        return $this->request->isAJAX();
    }

    public function resolveMethodByVerb(
        string $controller,
        string $requestedMethod
    ): ?string {
        $verb = strtoupper($this->request->getMethod());

        $suffixes = match ($verb) {
            'GET', 'HEAD' => ['View', ''],
            'POST', 'PUT', 'PATCH', 'DELETE' => ['Action', ''],
            default => [],
        };

        foreach ($suffixes as $suffix) {
            $method = $this->methodPolicy->resolveDiscoveredMethod(
                $controller,
                $requestedMethod . $suffix
            );

            if (
                $method !== null
                && $this->methodPolicy->isMethodAllowed($controller, $method)
            ) {
                return $method;
            }
        }

        if (in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $method = $this->methodPolicy->resolveDiscoveredMethod(
                $controller,
                'action'
            );

            if (
                $method !== null
                && $this->methodPolicy->isMethodAllowed($controller, $method)
            ) {
                return $method;
            }
        }

        return null;
    }

    public function resolveControllerAndMethod(
        $seg1,
        $seg2,
        array &$params
    ): array {

        $slug = strtolower(trim((string) $seg1, '/'));
        $verb = strtoupper($this->request->getMethod());
        $controller = null;

        foreach ($this->controllerClasses as $shortName => $className) {
            $name = preg_replace('/Controller$/i', '', $shortName);

            if (
                $slug !== ''
                && $this->normalizeControllerName($name) === $slug
            ) {
                $controller = $shortName;
                break;
            }
        }

        if ($controller !== null) {
            $requestedMethod = is_string($seg2) ? trim($seg2) : '';

            if ($requestedMethod === '') {
                $method = in_array($verb, ['GET', 'HEAD'], true)
                    ? $this->methodPolicy->resolveDiscoveredMethod(
                        $controller,
                        'index'
                    )
                    : $this->resolveMethodByVerb($controller, $slug);
            } else {
                $method = $this->methodPolicy->resolveDiscoveredMethod(
                    $controller,
                    $requestedMethod
                );
            }

            if ($method === null) {
                log_message('warning', 'RouteResolver failed: ' . json_encode([
                    'stage'     => 'method_resolution',
                    'reason'    => 'METHOD_NOT_DISCOVERED',
                    'controller' => $controller,
                    'requestedMethod' => $requestedMethod,
                    'httpMethod' => $verb,
                ]));

                throw PageNotFoundException::forPageNotFound();
            }

            return [$controller, $method, $params];
        }
        $requestedMethod = $slug !== '' ? $slug : 'index';
        $matches = [];

        foreach (array_keys($this->controllerClasses) as $shortName) {
            $m = $this->resolveMethodByVerb($shortName, $requestedMethod);
            if ($m !== null) {
                $matches[$shortName] = $m;
            }
        }

        if (count($matches) > 1) {
            log_message('critical', "Shortcut ambigu untuk '{$requestedMethod}': " . implode(', ', array_keys($matches)));
            $matches = isset($matches[$this->defaultController])
                ? [$this->defaultController => $matches[$this->defaultController]]
                : [];
        }

        if ($matches === []) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (is_string($seg2) && $seg2 !== '') {
            array_unshift($params, $seg2);
        }

        return [array_key_first($matches), reset($matches), $params];


        /* $requestedMethod = $slug !== '' ? $slug : 'index';

        foreach ($this->controllerClasses as $shortName => $className) {
            $method = $this->resolveMethodByVerb(
                $shortName,
                $requestedMethod
            );

            if (
                $method !== null
                && $this->methodPolicy->isMethodAllowed($shortName, $method)
            ) {
                return [$shortName, $method, $params];
            }
        }


        $default = $this->defaultController;

        if (! isset($this->controllerClasses[$default])) {
            throw PageNotFoundException::forPageNotFound(
                'Controller default tidak ditemukan.'
            );
        }

        $method = $this->resolveMethodByVerb($default, $requestedMethod);

        if (
            $method === null
            || ! $this->methodPolicy->isMethodAllowed($default, $method)
        ) {
            throw PageNotFoundException::forPageNotFound();
        }



        return [$default, $method, $params]; */
    }
}
