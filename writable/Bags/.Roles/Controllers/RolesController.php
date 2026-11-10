<?php

declare(strict_types=1);

namespace Modules\Roles\Controllers;

use App\Core\BackendController;
use Modules\Roles\Services\RouteRegistryService;

class RolesController extends BackendController
{
    protected RouteRegistryService $service;

    public function __construct()
    {

        $this->service = new RouteRegistryService();
    }

    public function index()
    {
        $settings = service('settings')->get('App.httpVerb') ?? '';

        if (is_string($settings)) {
            $verbs = array_values(array_unique(array_filter(
                array_map(
                    static fn(string $verb): string => strtoupper(trim($verb)),
                    explode(',', $settings)
                ),
                static fn(string $verb): bool => $verb !== ''
            )));
        } else {
            $verbs = [];
        }

        $this->data = [
            'verbs' => $verbs,
            'seg'   => $this->getFirstSegment(),
        ];

        return $this->render('index', $this->data);
    }

    protected function getFirstSegment(): string
    {
        return strtolower(
            trim((string) $this->request->getUri()->getSegment(1), '/')
        );
    }
}
