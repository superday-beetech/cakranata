<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use Modules\Auth\Services\UserService;
use Modules\Auth\Config\Services as AuthServices;

class AuthController extends ApiController
{
    protected UserService $userService;

    public function __construct()
    {
        $this->userService = AuthServices::userService();
    }

    public function login()
    {

        if (auth()->loggedIn()) {
            return $this->respond([
                'status'  => 200,
                'error'   => false,
                'message' => 'Anda sudah login.',
                'data'    => [
                    'authenticated' => true,
                    'mode' => 'session',
                    'redirect'      => '/acn/dashboard',
                ],
            ], 200);
        }

        $payload = $this->payload();

        $identity = trim((string) (
            $payload['identity'] ?? ''
        ));

        // Jangan trim password.
        $password = (string) ($payload['password'] ?? '');
        $rules = [
            'identity' => 'required|string|max_length[254]',
            'password' => 'required|string',
        ];

        $remember = filter_var(
            $payload['remember'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $this->validateData(
            [
                'identity' => $identity,
                'password' => $password,
            ],
            $rules,
            [],
            config('Auth')->DBGroup
        )) {
            return $this->errorResponse(
                'Username/Email dan Password wajib diisi.',
                422
            );
        }

        $field = filter_var($identity, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $credentials = [
            $field => $field === 'email'
                ? mb_strtolower($identity)
                : $identity,
            'password' => $password,
        ];

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $result = $authenticator
            ->remember($remember)
            ->attempt($credentials);

        if (! $result->isOK()) {
            return $this->errorResponse(
                'Username/Email atau Password tidak valid.',
                401
            );
        }

        if ($authenticator->hasAction()) {
            return $this->successResponse([
                'user'          => $result['user'],
            ], 'Login berhasil.');
        }

        return $this->successResponse([
            'user' => $result,
        ], 'Login berhasil.');
    }

    public function action(): RedirectResponse
    {
        $identity = trim((string) $this->request->getPost('identity'));
        $password = (string) $this->request->getPost('password');

        $rules = [
            'identity' => 'required|string|max_length[254]',
            'password' => 'required|string',
        ];

        if (! $this->validateData(
            [
                'identity' => $identity,
                'password' => $password,
            ],
            $rules,
            [],
            config('Auth')->DBGroup
        )) {
            return $this->errorResponse([
                'message' => 'Terjadi Kesalahan pada input.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $field = filter_var($identity, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $credentials = [
            $field => $field === 'email'
                ? mb_strtolower($identity)
                : $identity,
            'password' => $password,
        ];

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $result = $authenticator
            ->remember((bool) $this->request->getPost('remember'))
            ->attempt($credentials);

        if (! $result->isOK()) {
            return $this->errorResponse('Username/Email atau Password tidak valid.', 401);
        }

        if ($authenticator->hasAction()) {
            return redirect()
                ->route('auth-action-show')
                ->withCookies();
        }

        return redirect()
            ->to(config('Auth')->loginRedirect())
            ->withCookies();
    }

    public function me()
    {

        $auth = auth();

        $user = $auth->user();

        if (! $user) {
            return $this->errorResponse(
                'Session tidak ditemukan pada request API.',
                401
            );
        }

        $profile = $this->userService->getProfile($user);

        $profile['groups'] = $user->getGroups();
        $profile['identities'] = $user->identities;

        return $this->successResponse(
            $profile,
            'Profil pengguna berhasil diambil.'
        );
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);

        if (is_array($json)) {
            return array_merge(
                $this->request->getPost() ?? [],
                $json
            );
        }

        return $this->request->getPost() ?? [];
    }

    public function logout(): ResponseInterface
    {
        $url = config('Auth')->logoutRedirect();

        auth()->logout();
        return $this->successResponse(lang('Auth.successLogout'));
    }
}
