<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\BackendController;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Exceptions\ValidationException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Traits\Viewable;
use CodeIgniter\Shield\Validation\ValidationRules;

class RegisterController extends BackendController
{
    use Viewable;

    /**
     * Menampilkan formulir registrasi ACN ERP.
     *
     * @return RedirectResponse|string
     */
    public function index()
    {
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->registerRedirect());
        }

        if (! setting('Auth.allowRegistration')) {
            return redirect()->back()
                ->withInput()
                ->with('error', lang('Auth.registerDisabled'));
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show');
        }

        return $this->view(setting('Auth.views')['register']);
    }

    /**
     * Memproses registrasi sesuai mekanisme Shield.
     */
    public function action(): RedirectResponse
    {
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->registerRedirect());
        }

        if (! setting('Auth.allowRegistration')) {
            return redirect()->back()
                ->withInput()
                ->with('error', lang('Auth.registerDisabled'));
        }

        $users = $this->getUserProvider();
        $rules = $this->getValidationRules();

        if (! $this->validateData(
            $this->request->getPost(),
            $rules,
            [],
            config('Auth')->DBGroup
        )) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $allowedPostFields = array_keys($rules);

        $user = $users->createNewUser(
            $this->request->getPost($allowedPostFields)
        );

        // Kompatibilitas registrasi tanpa username.
        if ($user->username === null) {
            $user->username = null;
        }

        try {
            $users->save($user);
        } catch (ValidationException) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $users->errors());
        }

        // Ambil kembali entity lengkap beserta ID.
        $user = $users->findById($users->getInsertID());

        if (! $user instanceof User) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Registrasi gagal. Silakan coba kembali.');
        }

        $users->addToDefaultGroup($user);

        Events::trigger('register', $user);

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $authenticator->startLogin($user);

        if ($authenticator->startUpAction('register', $user)) {
            return redirect()->route('auth-action-show');
        }

        // Shield mengaktifkan akun jika tidak ada action registrasi.
        $user->activate();

        $authenticator->completeLogin($user);

        return redirect()
            ->to(config('Auth')->registerRedirect())
            ->with('message', lang('Auth.registerSuccess'));
    }

    /**
     * Mengambil provider pengguna yang dikonfigurasi di Shield.
     */
    protected function getUserProvider(): UserModel
    {
        $provider = model(setting('Auth.userProvider'));

        assert(
            $provider instanceof UserModel,
            'Config Auth.userProvider is not a valid UserProvider.'
        );

        return $provider;
    }

    /**
     * Aturan validasi registrasi Shield.
     *
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getValidationRules(): array
    {
        return (new ValidationRules())->getRegistrationRules();
    }
}
