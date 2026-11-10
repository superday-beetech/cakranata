<?php

namespace App\Libraries\DynamicRouter;

use CodeIgniter\HTTP\RequestInterface;

class AuthenticationResolver
{
    public function __construct(
        private RequestInterface $request,
        private string $apiPrefix = 'api',
    ) {}

    public function isApiRequest(): bool
    {
        $path = trim(
            $this->request->getUri()->getPath(),
            '/'
        );

        $prefix = trim($this->apiPrefix, '/');
        $isJsonAccept = str_contains((string) $this->request->getHeaderLine('Accept'), 'application/json');

        return $path === $prefix
            || str_starts_with($path, $prefix . '/');
        // || $isJsonAccept;
    }

    public function isAuthenticated(): bool
    {
        if (auth('session')->loggedIn()) {
            return true;
        }

        return $this->isApiRequest()
            && auth('tokens')->loggedIn();
    }

    public function getAuthenticatedUser()
    {
        $session = auth('session');

        if ($session->loggedIn()) {
            return $session->user();
        }

        if ($this->isApiRequest()) {
            $tokens = auth('tokens');

            if ($tokens->loggedIn()) {
                return $tokens->user();
            }
        }

        return null;
    }

    /*  public function isAuthenticated(string $authType): bool
    {
        return match ($authType) {
            'session' => auth('session')->loggedIn(),
            'token'   => auth('tokens')->loggedIn(),
            default   => false,
        };
    }

    public function getAuthenticatedUser(string $authType)
    {
        $handler = match ($authType) {
            'session' => auth('session'),
            'token'   => auth('tokens'),
            default   => null,
        };

        return $handler !== null && $handler->loggedIn()
            ? $handler->user()
            : null;
    } */
}
