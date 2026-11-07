<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\BackendController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Traits\Viewable;

class LoginController extends BackendController
{
    use Viewable;

    public function index()
    {
        if (auth()->loggedIn()) {
            return redirect()
                ->to(config('Auth')->loginRedirect())
                ->withCookies();
        }

        /** @var Session $authenticator */
        $authenticator = auth()->getAuthenticator();

        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show');
        }

        return $this->view('Modules\Auth\Views\login');
    }

    public function loginEmail(): RedirectResponse
    {
        $post = $this->request->getPost();

        if (! $this->validateData($post, [
            'identity' => 'required|string',
            'password' => 'required|string',
        ])) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $identity = trim((string) ($post['identity'] ?? ''));
        $password = (string) ($post['password'] ?? '');

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
        $authenticator = auth()->getAuthenticator();

        $result = $authenticator
            ->remember((bool) ($post['remember'] ?? false))
            ->attempt($credentials);

        if (! $result->isOK()) {
            return redirect()
                ->route('login')
                ->withInput()
                ->with('error', $result->reason());
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
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
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
            return redirect()
                ->route('login')
                ->withInput()
                ->with('error', 'Email/username atau password tidak valid.');
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

    public function logout(): RedirectResponse
    {
        $url = config('Auth')->logoutRedirect();

        auth()->logout();

        return redirect()
            ->to($url)
            ->with('message', lang('Auth.successLogout'));
    }
}
