<?php

declare(strict_types=1);

namespace Modules\RouteRegistry\Controllers;

use App\Core\BackendController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\RouteRegistry\Services\RouteRegistryService;
use Throwable;

class RouteRegistryController extends BackendController
{
    protected RouteRegistryService $service;

    public function __construct()
    {
        $this->service = new RouteRegistryService();
    }

    /**
     * Halaman utama.
     */
    public function index()
    {
        $this->ensureAuthenticated();
        $slug = $this->getFirstSegment();
        $settings = service('settings')->get('App.httpVerb') ?? '';

        $verbs = array_values(array_filter(
            array_map('trim', explode(',', $settings)),
            static fn($verb) => $verb !== ''
        ));

        $this->data = [
            'verbs' => $verbs,
            'seg' => $slug
        ];

        return $this->render('index', $this->data);
    }

    protected function getFirstSegment(): string
    {
        return strtolower(
            trim((string) $this->request->getUri()->getSegment(1), '/')
        );
    }

    protected function ensureAuthenticated(): bool
    {
        return function_exists('auth')
            && auth('session')->loggedIn();
    }

    protected function unauthorized(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(401)
            ->setJSON([
                'status'  => 'error',
                'message' => 'Silakan login terlebih dahulu.',
            ]);
    }

    protected function badRequest(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(400)
            ->setJSON([
                'status'  => 'error',
                'message' => $message,
            ]);
    }
}
