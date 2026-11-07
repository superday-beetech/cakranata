<?php

declare(strict_types=1);

namespace Modules\RouteRegistry\Controllers;

use App\Core\BackendController;
use Modules\RouteRegistry\Services\RouteRegistryService;

class RouteRegistryController extends BackendController
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

        $view = (string) $this->request->getGet('view');

        if (! in_array($view, ['registered', 'scan', 'comparison', 'index'], true)) {
            $view = 'registered';
        }

        $this->data = [
            'verbs'      => $verbs,
            'seg'        => $this->getFirstSegment(),
            'activeView' => $view,
        ];

        return $this->render($view, $this->data);
    }


    protected function getFirstSegment(): string
    {
        return strtolower(
            trim((string) $this->request->getUri()->getSegment(1), '/')
        );
    }
}
