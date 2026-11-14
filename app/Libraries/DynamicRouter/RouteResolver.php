<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;

class RouteResolver
{
    /** @var array<string, string> slug => shortName, cache untuk findController */
    private array $slugMap = [];

    public function __construct(
        private IncomingRequest $request,
        private MethodPolicy $methodPolicy,
        private array $controllerClasses,
        private string $defaultController,
        private array $customActions = [],
    ) {
        foreach (array_keys($this->controllerClasses) as $short) {
            $slug = $this->shortToSlug($short);
            if (isset($this->slugMap[$slug])) {
                log_message('warning', "RouteResolver: slug collision '{$slug}' antara '{$this->slugMap[$slug]}' dan '{$short}'");
                continue;
            }
            $this->slugMap[$slug] = $short;
        }
    }

    public function resolveControllerAndMethod(string $seg1 = '', ?string $seg2 = null, array $params = []): array
    {
        $slug = strtolower(trim($seg1, '/'));
        $verb = strtoupper($this->request->getMethod());

        $controller = $this->findController($slug);
        if ($controller === null) {
            return $this->resolveShortcut($slug, $seg2, $params);
        }


        $rest = $this->collectRemainingSegments($seg2, $params);

        if (($r = $this->resolveRestful($controller, $verb, $rest)) !== null) {
            [$method, $consumed] = $r;
            // resolveRestful mengonsumsi $rest[0..$consumed-1] sebagai parameter method.
            // Untuk show/update/delete, ini adalah id. Untuk index/create, kosong.
            $params = array_slice($rest, 0, $consumed);
            return [$controller, $method, $params];
        }
        if (($r = $this->resolveCustomSuffix($controller, $verb, $rest)) !== null) {
            [$method, $id, $consumed] = $r;
            $after = array_slice($rest, $consumed);
            array_unshift($after, $id);
            return [$controller, $method, $after];
        }
        if (($r = $this->resolveCustomPrefix($controller, $verb, $rest)) !== null) {
            [$method, $id, $consumed] = $r;
            $after = array_slice($rest, $consumed);
            array_unshift($after, $id);
            return [$controller, $method, $after];
        }

        $requested = isset($rest[0]) ? trim((string) $rest[0]) : '';
        if ($requested === '') {
            $method = in_array($verb, ['GET', 'HEAD'], true)
                ? $this->methodPolicy->resolveDiscoveredMethod($controller, 'index')
                : $this->resolveMethodByVerb($controller, 'index');
            if ($method === null) $this->fail('METHOD_NOT_DISCOVERED', $controller, '', $verb);
            return [$controller, $method, []];
        }

        $method = $this->resolveMethodExplicit($controller, $requested, $verb);
        if ($method === null) $this->fail('METHOD_NOT_DISCOVERED', $controller, $requested, $verb);
        return [$controller, $method, array_slice($rest, 1)];
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
            $m = $this->methodPolicy->resolveDiscoveredMethod($controller, $base . $suffix);
            if ($m !== null && $this->methodPolicy->isMethodAllowed($controller, $m)) return $m;
        }
        if (
            in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && in_array($base, ['index', 'action'], true)
        ) {
            $m = $this->methodPolicy->resolveDiscoveredMethod($controller, 'action');
            if ($m !== null && $this->methodPolicy->isMethodAllowed($controller, $m)) return $m;
        }
        return null;
    }

    private function findController(string $slug): ?string
    {
        return $this->slugMap[$slug] ?? null;
    }

    private function shortToSlug(string $short): string
    {
        $name = preg_replace('/Controller$/i', '', $short);
        // Handle akronim + CamelCase: APIKey -> api-key, OAuth2 -> oauth2
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $name);
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name);
        return strtolower($name);
    }

    private function isIdLike(string $seg, string $controller): bool
    {
        if ($seg === '') return false;
        if (ctype_digit($seg)) return true;
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $seg)) return true;
        // Konservatif: bukan ID jika bukan numeric/UUID.
        // Custom action harus eksplisit via customActions.
        return false;
    }

    private function resolveRestful(string $controller, string $verb, array $rest): ?array
    {
        $count = count($rest);
        // PUT/PATCH/DELETE tanpa ID TIDAK didukung di REST default.
        // Gunakan endpoint eksplisit (mis. /users/bulk) jika butuh operasi koleksi.
        $map = [
            'GET'    => [0 => 'index', 1 => 'show'],
            'HEAD'   => [0 => 'index', 1 => 'show'],
            'POST'   => [0 => 'create'],
            'PUT'    => [1 => 'update'],
            'PATCH'  => [1 => 'update'],
            'DELETE' => [1 => 'delete'],
        ];
        $base = $map[$verb][$count] ?? null;
        if ($base === null) return null;
        if ($count === 1 && ! $this->isIdLike($rest[0], $controller)) return null;
        $m = $this->resolveMethodExplicit($controller, $base, $verb);
        return $m !== null ? [$m, $count] : null;
    }

    private function resolveCustomSuffix(string $controller, string $verb, array $rest): ?array
    {
        if (! in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true) || count($rest) < 2) return null;
        [$a, $b] = $rest;
        if (! $this->isIdLike($a, $controller) || $this->isIdLike($b, $controller)) return null;
        if (! $this->isCustomAllowed($controller, $b)) return null;
        $m = $this->resolveMethodExplicit($controller, $b, $verb);
        return $m !== null ? [$m, $a, 2] : null;
    }

    private function resolveCustomPrefix(string $controller, string $verb, array $rest): ?array
    {
        if (! in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true) || count($rest) < 2) return null;
        [$a, $b] = $rest;
        if ($this->isIdLike($a, $controller) || ! $this->isIdLike($b, $controller)) return null;
        if (! $this->isCustomAllowed($controller, $a)) return null;
        $m = $this->resolveMethodExplicit($controller, $a, $verb);
        return $m !== null ? [$m, $b, 2] : null;
    }

    private function isCustomAllowed(string $controller, string $action): bool
    {
        if ($action === '') return false;
        if (isset($this->customActions[$controller])) {
            return in_array($action, $this->customActions[$controller], true);
        }
        if (isset($this->customActions['*'])) {
            return in_array($action, $this->customActions['*'], true);
        }
        // Fail-closed: custom action WAJIB terdaftar.
        log_message('debug', "RouteResolver: custom action '{$action}' tidak terdaftar untuk '{$controller}'.");
        return false;
    }

    private function resolveMethodExplicit(string $controller, string $name, string $verb): ?string
    {
        $candidates = in_array($verb, ['GET', 'HEAD'], true)
            ? [$name . 'View', $name]
            : (str_ends_with($name, 'Action') ? [$name] : [$name . 'Action', $name]);
        foreach ($candidates as $c) {
            $m = $this->methodPolicy->resolveDiscoveredMethod($controller, $c);
            if ($m !== null && $this->methodPolicy->isMethodAllowed($controller, $m)) return $m;
        }
        return null;
    }

    private function collectRemainingSegments(?string $seg2, array $params): array
    {
        $rest = [];
        if ($seg2 !== null && $seg2 !== '') $rest[] = $seg2;
        foreach ($params as $p) {
            $s = (string) $p;
            if ($s !== '') $rest[] = $s;
        }
        return array_values($rest);
    }

    private function resolveShortcut(string $slug, ?string $seg2, array $params): array
    {
        $requested = $slug !== '' ? $slug : 'index';
        // Prioritas 1: defaultController
        $m = $this->resolveMethodByVerb($this->defaultController, $requested);

        if ($m !== null) {
            return [
                $this->defaultController,
                $m,
                $this->collectRemainingSegments($seg2, $params),
            ];
        }

        // Prioritas 2: scan semua controller (kecuali default), log jika ambigu
        $matches = [];
        foreach (array_keys($this->controllerClasses) as $short) {
            if ($short === $this->defaultController) continue;
            $m = $this->resolveMethodByVerb($short, $requested);
            if ($m !== null) $matches[$short] = $m;
        }

        if (count($matches) > 1) {
            log_message('warning', 'RouteResolver ambiguous shortcut: ' . json_encode([
                'slug' => $slug,
                'matches' => array_keys($matches),
            ]));
            throw PageNotFoundException::forPageNotFound();
        }

        if ($matches === []) throw PageNotFoundException::forPageNotFound();

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
