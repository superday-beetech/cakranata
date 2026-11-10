<?php

namespace App\Libraries\DynamicRouter;

use CodeIgniter\HTTP\RequestInterface;

class AccessChecker
{
    public function __construct(
        private RequestInterface $request,
        private AuthenticationResolver $authenticationResolver,
        private array $publicMethods = [],
    ) {}

    public function checkAccess(
        string $endpoint
    ): bool {

        if (in_array($endpoint, $this->publicMethods, true)) {
            return true;
        }

        $verb = strtoupper($this->request->getMethod());
        $registry = service('routeRegistryAccess');
        $route = $registry->findAllowedRoute($endpoint, $verb);

        if ($route === null) {
            return false;
        }

        $user = $this->authenticationResolver->getAuthenticatedUser();

        if ($user === null) {
            return false;
        }

        $userId = (int) $user->id;
        $accessControl = service('accessControl');

        if ($accessControl->isSuperadmin($userId)) {
            return true;
        }

        // dd(($route['id']));
        // dd(($userId));
        // dd($registry->allows($userId, (int) $route['id']));
        return $registry->allows($userId, (int) $route['id']);
    }

    public function isSuperadmin(): bool
    {
        $user = $this->authenticationResolver->getAuthenticatedUser();

        if ($user === null) {
            return false;
        }

        return service('accessControl')
            ->isSuperadmin((int) $user->id);
    }
}
