<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\BackendController;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\LoginModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Traits\Viewable;

class MagicController extends BackendController
{
    use Viewable;

    protected UserModel $provider;

    public function __construct()
    {
        /** @var class-string<UserModel> $providerClass */
        $providerClass = setting('Auth.userProvider');
        $this->provider = new $providerClass();
    }

    /**
     * Tampilkan formulir permintaan Magic Link.
     */
    public function loginView(): RedirectResponse|string
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')
                ->with('error', lang('Auth.magicLinkDisabled'));
        }

        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return $this->view(setting('Auth.views')['magic-link-login']);
    }

    /**
     * Buat dan kirim tautan login melalui email.
     */
    public function loginAction(): RedirectResponse|string
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')
                ->with('error', lang('Auth.magicLinkDisabled'));
        }

        $rules = $this->getValidationRules();

        if (! $this->validateData(
            $this->request->getPost(),
            $rules,
            [],
            config('Auth')->DBGroup
        )) {
            return redirect()->route('magic-link')
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = trim((string) $this->request->getPost('email'));
        $user  = $this->provider->findByCredentials(['email' => $email]);

        if ($user === null) {
            return redirect()->route('magic-link')
                ->withInput()
                ->with('error', lang('Auth.invalidEmail', [$email]));
        }

        $identityModel = model(UserIdentityModel::class);

        $identityModel->deleteIdentitiesByType(
            $user,
            Session::ID_TYPE_MAGIC_LINK
        );

        helper('text');
        $token = random_string('crypto', 20);

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(
                setting('Auth.magicLinkLifetime')
            ),
        ]);

        /** @var IncomingRequest $request */
        $request   = service('request');
        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        helper('email');

        $emailMessage = emailer(['mailType' => 'html'])
            ->setFrom(
                setting('Email.fromEmail'),
                setting('Email.fromName') ?? ''
            )
            ->setTo($user->email)
            ->setSubject(lang('Auth.magicLinkSubject'))
            ->setMessage($this->view(
                setting('Auth.views')['magic-link-email'],
                [
                    'token'     => $token,
                    'user'      => $user,
                    'ipAddress' => $ipAddress,
                    'userAgent' => $userAgent,
                    'date'      => $date,
                ],
                ['debug' => false]
            ));

        if ($emailMessage->send(false) === false) {
            log_message(
                'error',
                $emailMessage->printDebugger(['headers'])
            );

            $emailMessage->clear();

            $identityModel->deleteIdentitiesByType(
                $user,
                Session::ID_TYPE_MAGIC_LINK
            );

            return redirect()->route('magic-link')
                ->with('error', lang(
                    'Auth.unableSendEmailToUser',
                    [$user->email]
                ));
        }

        $emailMessage->clear();

        return $this->displayMessage();
    }

    /**
     * Tampilkan pesan setelah permintaan email diproses.
     */
    protected function displayMessage(): string
    {
        return $this->view(
            setting('Auth.views')['magic-link-message']
        );
    }

    /**
     * Verifikasi token dan login pengguna.
     */
    public function verify(): RedirectResponse
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')
                ->with('error', lang('Auth.magicLinkDisabled'));
        }

        if ($this->request->getUserAgent()->isRobot()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $token = $this->request->getGet('token');

        if (! is_string($token) || $token === '') {
            $this->recordLoginAttempt('', false);

            Events::trigger('failedLogin', [
                'magicLinkToken' => $token,
            ]);

            return redirect()->route('magic-link')
                ->with('error', lang('Auth.magicTokenNotFound'));
        }

        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(
            Session::ID_TYPE_MAGIC_LINK,
            $token
        );

        if ($identity === null) {
            $this->recordLoginAttempt($token, false);

            Events::trigger('failedLogin', [
                'magicLinkToken' => $token,
            ]);

            return redirect()->route('magic-link')
                ->with('error', lang('Auth.magicTokenNotFound'));
        }

        // Token hanya dapat digunakan sekali.
        $identityModel->delete($identity->id);

        if (Time::now()->isAfter($identity->expires)) {
            $this->recordLoginAttempt($token, false);

            Events::trigger('failedLogin', [
                'magicLinkToken' => $token,
            ]);

            return redirect()->route('magic-link')
                ->with('error', lang('Auth.magicLinkExpired'));
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($authenticator->hasAction($identity->user_id)) {
            return redirect()->route('auth-action-show')
                ->with('error', lang('Auth.needActivate'));
        }

        $user = $this->provider->findById($identity->user_id);

        if (! $user instanceof User) {
            $this->recordLoginAttempt($token, false);

            return redirect()->route('magic-link')
                ->with('error', lang('Auth.magicTokenNotFound'));
        }

        if (
            $authenticator->startUpAction('login', $user)
            && $authenticator->hasAction($user->id)
        ) {
            $this->recordLoginAttempt($token, true, $user->id);
            $authenticator->setPendingLoginMethod(
                Session::ID_TYPE_MAGIC_LINK
            );

            return redirect()->route('auth-action-show');
        }

        $authenticator->setPendingLoginMethod(
            Session::ID_TYPE_MAGIC_LINK
        );

        $authenticator->loginById($identity->user_id);

        $loggedInUser = $authenticator->getUser();

        $this->recordLoginAttempt(
            $token,
            true,
            $loggedInUser?->id
        );

        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Catat percobaan login untuk audit Shield.
     *
     * @param int|string|null $userId
     */
    private function recordLoginAttempt(
        string $identifier,
        bool $success,
        $userId = null
    ): void {
        $loginModel = model(LoginModel::class);

        $loginModel->recordLoginAttempt(
            Session::ID_TYPE_MAGIC_LINK,
            $identifier,
            $success,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $userId
        );
    }

    /**
     * Aturan validasi email Shield.
     *
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getValidationRules(): array
    {
        return [
            'email' => config('Auth')->emailValidationRules,
        ];
    }
}
