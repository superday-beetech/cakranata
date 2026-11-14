<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Shield\Entities\User;
use Config\AuthPolicy;

class AuthenticationResolver
{
    private const ACCESS_COOKIE = 'acn_access_token';

    private ?User $resolvedUser = null;
    private bool $userResolved = false;

    private AuthPolicy $policy;

    public function __construct(
        private RequestInterface $request,
        ?AuthPolicy $policy = null,
    ) {
        $this->policy = $policy ?? config(AuthPolicy::class);
    }

    public function isApiRequest(): bool
    {
        $path = trim($this->request->getUri()->getPath(), '/');
        $prefix = trim($this->policy->apiPrefix, '/');

        return $prefix !== ''
            && (
                $path === $prefix
                || str_starts_with($path, $prefix . '/')
            );
    }

    public function isAuthenticated(): bool
    {
        return $this->getAuthenticatedUser() !== null;
    }

    public function getAuthenticatedUser(): ?User
    {
        if ($this->userResolved) {
            return $this->resolvedUser;
        }
        $this->userResolved = true;

        try {
            if ($this->isApiRequest()) {
                return $this->resolvedUser = $this->resolveApiUser();
            }
            return $this->resolvedUser = $this->resolveWebUser();
        } catch (\Throwable $e) {
            log_message('error', 'AuthenticationResolver: gagal resolve user — ' . $e->getMessage());
            return $this->resolvedUser = null;
        }
    }

    private function resolveApiUser(): ?User
    {
        foreach ($this->policy->apiAuthenticators as $authenticator) {
            $user = match (strtolower((string) $authenticator)) {
                'session' => $this->getSessionUser(),
                'tokens', 'bearer' => $this->authenticateToken($this->getBearerToken()),
                default => null,
            };

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    private function resolveWebUser(): ?User
    {
        foreach ($this->policy->webAuthenticators as $authenticator) {
            $user = match (strtolower($authenticator)) {
                'session' => $this->getSessionUser(),
                'cookie'  => $this->authenticateToken(
                    $this->getCookieValue(self::ACCESS_COOKIE)
                ),
                default => null,
            };

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    public function getSessionUser(): ?User
    {
        try {
            $authenticator = auth('session');
            if (! $authenticator->loggedIn()) {
                return null;
            }
            $user = $authenticator->user();
            return $user instanceof User ? $user : null;
        } catch (\Throwable $e) {
            log_message('error', 'AuthenticationResolver: gagal resolve session user — ' . $e->getMessage());
            return null;
        }
    }

    private function authenticateToken(string $rawToken): ?User
    {
        $rawToken = trim($rawToken);

        if ($rawToken === '') {
            return null;
        }

        $authenticator = auth('tokens')->getAuthenticator();

        $result = $authenticator->check([
            'token' => $rawToken,
        ]);

        if (! $result->isOK()) {
            return null;
        }

        $user = $result->extraInfo();

        return $user instanceof User ? $user : null;
    }

    private function getBearerToken(): string
    {
        $header = trim(
            $this->request->getHeaderLine('Authorization')
        );

        if (
            $header === ''
            || ! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)
        ) {
            return '';
        }

        return trim($matches[1]);
    }

    private function getCookieValue(string $name): string
    {
        if (! $this->request instanceof IncomingRequest) {
            return '';
        }

        $value = $this->request->getCookie($name);

        return is_string($value) ? trim($value) : '';
    }
}

/*<!--
namespace App\Libraries\DynamicRouter;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Shield\Entities\User;
use Config\AuthPolicy;

class AuthenticationResolver
{
    private const ACCESS_COOKIE = 'acn_access_token';
    private const AUTH_MODE_HEADER = 'X-ACN-Auth-Mode';

    private ?User $resolvedUser = null;
    private bool $userResolved = false;

    private AuthPolicy $policy;

    public function __construct(
        private RequestInterface $request,
        ?AuthPolicy $policy = null,
    ) {
        $this->policy = $policy ?? config(AuthPolicy::class);
    }

    public function isApiRequest(): bool
    {
        $path = trim($this->request->getUri()->getPath(), '/');
        $prefix = trim($this->policy->apiPrefix, '/');

        return $prefix !== ''
            && (
                $path === $prefix
                || str_starts_with($path, $prefix . '/')
            );
    }

    public function isAuthenticated(): bool
    {
        return $this->getAuthenticatedUser() !== null;
    }

    public function getAuthenticatedUser(): ?User
    {
        if ($this->userResolved) {
            return $this->resolvedUser;
        }


        $this->userResolved = true;

        $mode = strtolower(trim(
            $this->request->getHeaderLine(self::AUTH_MODE_HEADER)
        ));
        // print_r($mode);
        if ($this->isApiRequest()) {
            if ($mode === '' || ! in_array(
                $mode,
                $this->policy->apiAuthenticators,
                true
            )) {
                return null;
            }

            return $this->resolvedUser = match ($mode) {
                'tokens', 'bearer' => $this->authenticateToken(
                    $this->getBearerToken()
                ),
                default => null,
            };
        }



        if ($mode !== '' && ! in_array(
            $mode,
            $this->policy->webAuthenticators,
            true
        )) {
            return null;
        }

        // Web: session
        if ($mode === 'session' || ($mode === '' && in_array(
            'session',
            $this->policy->webAuthenticators,
            true
        ))) {
            $user = $this->getSessionUser();

            if ($user !== null) {
                return $this->resolvedUser = $user;
            }
        }

        // Web: cookie token
        if ($mode === 'cookie' || ($mode === '' && in_array(
            'cookie',
            $this->policy->webAuthenticators,
            true
        ))) {
            return $this->resolvedUser = $this->authenticateToken(
                $this->getCookieValue(self::ACCESS_COOKIE)
            );
        }

        return null;
    }

    public function getSessionUser(): ?User
    {
        $authenticator = auth('session');

        if (! $authenticator->loggedIn()) {
            return null;
        }

        $user = $authenticator->user();

        return $user instanceof User ? $user : null;
    }

    private function authenticateToken(string $rawToken): ?User
    {
        $rawToken = trim($rawToken);

        if ($rawToken === '') {
            return null;
        }

        $authenticator = auth('tokens')->getAuthenticator();

        $result = $authenticator->check([
            'token' => $rawToken,
        ]);

        if (! $result->isOK()) {
            return null;
        }

        $user = $result->extraInfo();

        return $user instanceof User ? $user : null;
    }

    private function getBearerToken(): string
    {
        $header = trim(
            $this->request->getHeaderLine('Authorization')
        );

        if (
            $header === ''
            || ! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)
        ) {
            return '';
        }

        return trim($matches[1]);
    }

    private function getCookieValue(string $name): string
    {
        // getCookie() tersedia pada IncomingRequest CI4.
        if (! $this->request instanceof IncomingRequest) {
            return '';
        }

        return trim((string) $this->request->getCookie($name));
    }
}
 -->
*/
