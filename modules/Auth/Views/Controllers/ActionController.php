<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\BackendController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Shield\Authentication\Actions\ActionInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;

class ActionController extends BackendController
{
    protected ?ActionInterface $action = null;

    /**
     * @param list<string> $params
     *
     * @return Response|string
     */
    public function _remap(string $method, ...$params)
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $this->action = $authenticator->getAction();

        if (! $this->action instanceof ActionInterface) {
            throw new PageNotFoundException();
        }

        return $this->{$method}(...$params);
    }

    /**
     * Menampilkan halaman awal authentication action.
     *
     * @return Response|string
     */

    public function show()
    {
        return $this->action->show();
    }

    /**
     * Memproses form authentication action.
     *
     * @return Response|string
     */
    public function handle()
    {
        return $this->action->handle($this->request);
    }

    /**
     * Memproses verifikasi authentication action.
     *
     * @return Response|string
     */
    public function verify()
    {
        if ($this->request->getUserAgent()->isRobot()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->action->verify($this->request);
    }
}
