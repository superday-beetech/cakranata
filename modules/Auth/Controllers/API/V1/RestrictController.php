<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use Modules\Auth\Config\Services as AuthServices;
use Modules\Auth\Services\UserService;

class RestrictController extends ApiController
{

    protected UserService $userService;

    public function __construct()
    {
        $this->userService = AuthServices::userService();
    }

    public function index()
    {
        return $this->successResponse((['message' => 's']));
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
                401
                    ? 'Username/Email atau Password tidak valid.'
                    : 'Login gagal diproses.'
            );
        }

        if ($authenticator->hasAction()) {
            return $this->successResponse([
                'user'          => $result['user'],
            ], 'Login berhasil.');
        }

        return $this->successResponse([
            'user' => $result['user'],
        ], 'Login berhasil.');
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

    public function logout(): RedirectResponse
    {
        $url = config('Auth')->logoutRedirect();

        auth()->logout();
        return $this->successResponse(lang('Auth.successLogout'));
    }

    public function meX()
    {
        /*  if (auth()->loggedIn()) {
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
        } */
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
        // $profile['identities'] = $user->identities;

        return $this->successResponse(
            $profile,
            'Profil pengguna berhasil diambil.'
        );
    }
}
