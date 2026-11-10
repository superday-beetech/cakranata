<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use Throwable;

class ErpApiController extends ApiController
{
    use ResponseTrait;

    protected $settings;
    protected $db;
    protected string $table = 'settings';
    private const MAX_KEYS = 100;
    public function __construct()
    {
        $this->settings = service('settings');

        $config = config('Settings');

        $group = $config->database['group'] ?? null;

        $this->table = $config->database['table'] ?? 'settings';

        $this->db = Database::connect($group);
    }


    public function index()
    {
        $menuModel = new \App\Models\AdminModel();

        $navigation = $menuModel->getAllNavigation('backend');

        return $this->respond([
            'status'  => 200,
            'error'   => false,
            'message' => 'Navigasi berhasil dimuat.',
            'data'    => [
                'menus' => $navigation,
            ],
        ]);
    }

    /**
     * POST /api/settings/bulk
     */
    public function settings()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || ! isset($payload['keys'])) {
            return $this->failValidationErrors([
                'keys' => 'Daftar settings wajib dikirim.',
            ]);
        }

        $keys = $payload['keys'];

        if (! is_array($keys) || ! array_is_list($keys)) {
            return $this->failValidationErrors([
                'keys' => 'Format keys harus berupa array.',
            ]);
        }

        if (count($keys) > self::MAX_KEYS) {
            return $this->failValidationErrors([
                'keys' => 'Maksimal 100 settings per request.',
            ]);
        }

        $keys = array_values(array_unique($keys));

        foreach ($keys as $key) {
            if (
                ! is_string($key)
                || ! preg_match(
                    '/^[A-Za-z_][A-Za-z0-9_\\\\]*(?:\.[A-Za-z_][A-Za-z0-9_]*)$/D',
                    $key
                )
            ) {
                return $this->failValidationErrors([
                    'keys' => 'Format Class.Key tidak valid.',
                ]);
            }
        }

        try {
            $data = service('settings')->getMany($keys);

            return $this->respond([
                'status' => true,
                'data' => $data,
                'meta' => [
                    'count' => count($keys),
                ],
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[Settings API] ' . $e->getMessage()
            );

            return $this->failServerError(
                'Gagal mengambil konfigurasi.'
            );
        }
    }
    /**
     * Get all App settings.
     */
    public function setting123()
    {
        $appConfig = config('App');
        $class = $appConfig::class;

        $rows = $this->db
            ->table($this->table)
            ->select('key')
            ->where('class', $class)
            ->where('context', null)
            ->get()
            ->getResultArray();

        $data = [];

        foreach ($rows as $row) {
            $key = $row['key'];

            $data[$key] = $this->settings->get(
                'App.' . $key
            );
        }

        return $this->respond([
            'status' => true,
            'data'   => $data,
        ]);
    }

    /**
     * Get individual setting.
     */
    public function show($key = null)
    {
        $key = $this->request->getGet('key');

        if (!$key || !preg_match('/^App\.[a-zA-Z0-9_]+$/', $key)) {
            return $this->errorResponse('Invalid setting key.');
        }

        [$className, $property] = explode('.', $key, 2);

        $appConfig = config('App');
        $class = $appConfig::class;

        $exists = $this->db
            ->table($this->table)
            ->where('class', $class)
            ->where('key', $property)
            ->where('context', null)
            ->countAllResults();

        if (!$exists) {
            return $this->failNotFound('Setting not found.');
        }

        return $this->respond([
            'status' => true,
            'data'   => $this->settings->get($key),
        ]);
    }


    /* public function index()
    {
        helper('vite');
        $menuModel = new \App\Models\AdminModel();

        $data = ($menuModel->getNestedMenu('backend', 'sidebar'));
        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'HTTP verb berhasil dimuat.',
            'data'    => $data,
        ]);
    } */
}
