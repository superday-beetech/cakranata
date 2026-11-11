<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Resolves controller URLs while preserving DynamicRouterBase's public contract.
 *
 * Supported conventions:
 * - Legacy explicit method: /controller/method/param...
 * - REST: GET /controller, GET /controller/{numeric-or-uuid-id},
 *         POST /controller, PUT|PATCH|DELETE /controller/{id}
 * - Custom action: /controller/{id}/{action} and /controller/{action}/{id}
 *
 * Slug identifiers are intentionally not guessed: they are ambiguous with the
 * legacy explicit-method convention. Add an explicit route/config convention
 * before enabling slug IDs.
 */
class RouteResolver
{
    public function __construct(
        private IncomingRequest $request,
        private MethodPolicy $methodPolicy,
        private array $controllerClasses,
        private string $defaultController,
        private array $customActions = [],
    ) {}

    public function normalizeControllerName(string $controller): string
    {
        return NameNormalizer::kebab($controller);
    }

    public function resolveEffectiveMethod(string $controller, string $method): string
    {
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

    public function resolveControllerAndMethod($seg1, $seg2, array &$params): array
    {
        $slug = strtolower(trim((string) $seg1, '/'));
        $verb = strtoupper($this->request->getMethod());

        $controller = $this->findController($slug);

        if ($controller === null) {
            return $this->resolveShortcut($slug, $seg2, $params);
        }

        $rest = $this->collectRemainingSegments($seg2, $params);

        // Custom action patterns take precedence over generic REST matching.
        if (($custom = $this->resolveCustomAction($controller, $verb, $rest)) !== null) {
            return $custom;
        }

        if (($restful = $this->resolveRestful($controller, $verb, $rest)) !== null) {
            [$method, $consumed] = $restful;

            return [$controller, $method, array_slice($rest, $consumed)];
        }

        // Preserve the previous /controller/method/param... convention.
        $requested = isset($rest[0]) ? trim((string) $rest[0]) : '';

        if ($requested !== '') {
            $method = $this->resolveMethodExplicit($controller, $requested, $verb);

            if ($method !== null) {
                return [$controller, $method, array_slice($rest, 1)];
            }
        }

        $this->fail(
            'METHOD_NOT_DISCOVERED',
            $controller,
            $requested,
            $verb
        );
    }

    public function resolveMethodByVerb(string $controller, string $requestedMethod): ?string
    {
        $verb = strtoupper($this->request->getMethod());
        $base = $requestedMethod !== '' ? $requestedMethod : 'index';

        $suffixes = match ($verb) {
            'GET', 'HEAD' => ['View', ''],
            'POST', 'PUT', 'PATCH', 'DELETE' => ['Action', ''],
            default => [],
        };

        foreach ($suffixes as $suffix) {
            $method = $this->methodPolicy->resolveDiscoveredMethod($controller, $base . $suffix);

            if ($method !== null && $this->methodPolicy->isMethodAllowed($controller, $method)) {
                return $method;
            }
        }

        if (in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && in_array($base, ['index', 'action'], true)
        ) {
            $method = $this->methodPolicy->resolveDiscoveredMethod($controller, 'action');

            if ($method !== null && $this->methodPolicy->isMethodAllowed($controller, $method)) {
                return $method;
            }
        }

        return null;
    }

    private function findController(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        foreach ($this->controllerClasses as $shortName => $_class) {
            $name = preg_replace('/Controller$/i', '', $shortName) ?? $shortName;

            if ($this->normalizeControllerName($name) === $slug) {
                return $shortName;
            }
        }

        return null;
    }

    /**
     * Only unambiguous identifier formats are inferred as REST IDs.
     * Unknown words remain eligible for the legacy explicit-method resolver.
     */
    private function isIdLike(string $segment): bool
    {
        if ($segment === '') {
            return false;
        }

        if (ctype_digit($segment)) {
            return true;
        }

        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $segment
        ) === 1;
    }

    private function resolveRestful(string $controller, string $verb, array $rest): ?array
    {
        $count = count($rest);

        $base = match (true) {
            in_array($verb, ['GET', 'HEAD'], true) && $count === 0 => 'index',
            in_array($verb, ['GET', 'HEAD'], true) && $count === 1 && $this->isIdLike((string) $rest[0]) => 'show',
            $verb === 'POST' && $count === 0 => 'create',
            in_array($verb, ['PUT', 'PATCH'], true) && $count === 1 && $this->isIdLike((string) $rest[0]) => 'update',
            $verb === 'DELETE' && $count === 1 && $this->isIdLike((string) $rest[0]) => 'delete',
            default => null,
        };

        if ($base === null) {
            return null;
        }

        $method = $this->resolveMethodExplicit($controller, $base, $verb);

        // Compatibility fallback for controllers that still expose indexAction/action.
        if ($method === null && $base === 'create') {
            $method = $this->resolveMethodByVerb($controller, 'index')
                ?? $this->resolveMethodByVerb($controller, 'action');
        }

        if ($method === null) {
            return null;
        }

        return [$method, $count];
    }

    private function resolveCustomAction(string $controller, string $verb, array $rest): ?array
    {
        if (! in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true) || count($rest) < 2) {
            return null;
        }

        [$first, $second] = [(string) $rest[0], (string) $rest[1]];

        // /controller/{id}/{action}
        if ($this->isIdLike($first) && ! $this->isIdLike($second)
            && $this->isCustomAllowed($controller, $second)
        ) {
            $method = $this->resolveMethodExplicit($controller, $second, $verb);

            if ($method !== null) {
                return [$controller, $method, array_merge([$first], array_slice($rest, 2))];
            }
        }

        // /controller/{action}/{id}
        if (! $this->isIdLike($first) && $this->isIdLike($second)
            && $this->isCustomAllowed($controller, $first)
        ) {
            $method = $this->resolveMethodExplicit($controller, $first, $verb);

            if ($method !== null) {
                return [$controller, $method, array_merge([$second], array_slice($rest, 2))];
            }
        }

        return null;
    }

    private function isCustomAllowed(string $controller, string $action): bool
    {
        if ($action === '') {
            return false;
        }

        if (array_key_exists($controller, $this->customActions)) {
            return in_array($action, $this->customActions[$controller], true);
        }

        if (array_key_exists('*', $this->customActions)) {
            return in_array($action, $this->customActions['*'], true);
        }

        // Backward compatible default: discovered + MethodPolicy-allowed methods only.
        return $this->methodPolicy->resolveDiscoveredMethod($controller, $action) !== null
            && $this->methodPolicy->isMethodAllowed(
                $controller,
                $this->methodPolicy->resolveDiscoveredMethod($controller, $action)
            );
    }

    private function resolveMethodExplicit(string $controller, string $name, string $verb): ?string
    {
        $candidates = in_array($verb, ['GET', 'HEAD'], true)
            ? [$name . 'View', $name]
            : (str_ends_with($name, 'Action') ? [$name] : [$name . 'Action', $name]);

        foreach ($candidates as $candidate) {
            $method = $this->methodPolicy->resolveDiscoveredMethod($controller, $candidate);

            if ($method !== null && $this->methodPolicy->isMethodAllowed($controller, $method)) {
                return $method;
            }
        }

        return null;
    }

    private function collectRemainingSegments($seg2, array $params): array
    {
        $rest = [];

        if (is_string($seg2) && $seg2 !== '') {
            $rest[] = $seg2;
        }

        foreach ($params as $parameter) {
            if ($parameter !== '' && $parameter !== null) {
                $rest[] = (string) $parameter;
            }
        }

        return array_values($rest);
    }

    private function resolveShortcut(string $slug, $seg2, array &$params): array
    {
        $requested = $slug !== '' ? $slug : 'index';
        $matches = [];

        foreach (array_keys($this->controllerClasses) as $shortName) {
            $method = $this->resolveMethodByVerb($shortName, $requested);

            if ($method !== null) {
                $matches[$shortName] = $method;
            }
        }

        if (count($matches) > 1) {
            $matches = isset($matches[$this->defaultController])
                ? [$this->defaultController => $matches[$this->defaultController]]
                : [];
        }

        if ($matches === []) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [
            array_key_first($matches),
            reset($matches),
            $this->collectRemainingSegments($seg2, $params),
        ];
    }

    private function fail(string $reason, string $controller, string $requested, string $verb): never
    {
        log_message('warning', 'RouteResolver failed: ' . json_encode([
            'reason' => $reason,
            'controller' => $controller,
            'requested' => $requested,
            'verb' => $verb,
        ]));

        throw PageNotFoundException::forPageNotFound();
    }
}
