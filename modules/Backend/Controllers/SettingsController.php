<?php

declare(strict_types=1);

namespace Modules\Backend\Controllers;

use App\Core\BackendController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

class SettingsController extends BackendController
{
    private const ALLOWED_TYPES = [
        'string',
        'integer',
        'float',
        'boolean',
        'json',
    ];

    private function settingsConfig(): object
    {
        return config('Settings');
    }

    private function settingsTable(): string
    {
        $table = $this->settingsConfig()->database['table'] ?? 'settings';

        if (
            ! is_string($table)
            || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1
        ) {
            throw new RuntimeException('Nama tabel Settings tidak valid.');
        }

        return $table;
    }

    private function db()
    {
        return db_connect('default');
    }

    private function displayGroup(string $class): string
    {
        return str_starts_with($class, 'Config\\')
            ? substr($class, strlen('Config\\'))
            : $class;
    }

    private function databaseClass(string $group): string
    {
        $group = trim($group);

        if (
            $group === ''
            || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $group) !== 1
        ) {
            throw new InvalidArgumentException('Nama class tidak valid.');
        }

        $config = config($group);

        return is_object($config) ? $config::class : $group;
    }

    private function validProperty(string $property): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $property) === 1;
    }

    private function normalizeType(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'bool', 'boolean' => 'boolean',
            'int', 'integer' => 'integer',
            'float', 'double' => 'float',
            'array', 'json', 'object' => 'json',
            default => 'string',
        };
    }

    private function parseValue(string $value, string $type): mixed
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Tipe data tidak didukung.');
        }

        return match ($type) {
            'string' => $value,

            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? (int) $value
                : throw new InvalidArgumentException(
                    'Nilai harus berupa bilangan bulat.'
                ),

            'float' => is_numeric($value) && is_finite((float) $value)
                ? (float) $value
                : throw new InvalidArgumentException(
                    'Nilai harus berupa angka yang valid.'
                ),

            'boolean' => match (strtolower(trim($value))) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => throw new InvalidArgumentException(
                    'Nilai boolean tidak valid.'
                ),
            },

            'json' => json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
        };
    }

    private function postString(mixed $value, string $field): string
    {
        if (
            ! is_string($value)
            && ! is_int($value)
            && ! is_float($value)
        ) {
            throw new InvalidArgumentException(
                "Field {$field} tidak valid."
            );
        }

        return (string) $value;
    }

    private function contextWhere($builder, ?string $context)
    {
        return $context === null
            ? $builder->where('context', null)
            : $builder->where('context', $context);
    }

    private function redirectToSettings(): RedirectResponse
    {
        return redirect()->to(site_url('backend/settings'));
    }

    /**
     * Daftar pengaturan dikelompokkan berdasarkan class dan context.
     */
    public function index()
    {
        $db = $this->db();
        $table = $this->settingsTable();
        $rows = $db->table($table)
            ->select('class, key, type, context')
            ->orderBy('class', 'ASC')
            ->orderBy('context', 'ASC')
            ->orderBy('key', 'ASC')
            ->limit(2000)
            ->get()
            ->getResultArray();

        $settings = service('settings');
        // $settings->get('App.siteNameX', 'Cakra Admin', 'module:backend');
        $groups = [];

        foreach ($rows as $row) {
            $databaseClass = (string) $row['class'];
            $groupName = $this->displayGroup($databaseClass);
            $context = $row['context'] === null
                || $row['context'] === ''
                ? null
                : (string) $row['context'];

            $property = (string) $row['key'];
            $groupId = hash(
                'sha256',
                $databaseClass . "\0" . ($context ?? '')
            );

            if (! isset($groups[$groupId])) {
                $groups[$groupId] = [
                    'id' => $groupId,
                    'name' => $groupName,
                    'class' => $databaseClass,
                    'context' => $context,
                    'items' => [],
                ];
            }

            $groups[$groupId]['items'][] = [
                'property' => $property,
                'key' => $groupName . '.' . $property,
                'type' => $this->normalizeType((string) $row['type']),
                'value' => $settings->get(
                    $groupName . '.' . $property,
                    $context
                ),
            ];
        }

        return $this->render('settings/setting', [
            'groups' => $groups,
            'label' => 'Pengaturan Aplikasi',
        ]);
    }

    /**
     * Menyimpan perubahan pada satu tab.
     */
    public function bulkSave(): ResponseInterface
    {
        $input = $this->request->getJSON(true) ?? [];
        $rows = $input['rows'] ?? [];
        $forgetPost = $input['forget'] ?? [];
        $transactionStarted = false;

        try {
            $group = trim($this->postString(
                $input['group'] ?? '',
                'group'
            ));

            $contextInput = $input['context'] ?? null;
            $context = $contextInput === null || $contextInput === ''
                ? null
                : trim($this->postString($contextInput, 'context'));

            if (! is_array($rows) || ! is_array($forgetPost)) {
                throw new InvalidArgumentException(
                    'Format data pengaturan tidak valid.'
                );
            }

            $databaseClass = $this->databaseClass($group);
            $groupName = $this->displayGroup($databaseClass);
            $db = $this->db();
            $table = $this->settingsTable();

            if (! empty($this->settingsConfig()->database['deferWrites'])) {
                throw new RuntimeException(
                    'Bulk save memerlukan deferWrites=false.'
                );
            }

            $groupQuery = $this->contextWhere(
                $db->table($table)->where('class', $databaseClass),
                $context
            );

            if ($groupQuery->countAllResults() === 0) {
                throw new InvalidArgumentException(
                    'Kelompok pengaturan tidak ditemukan.'
                );
            }

            $existingQuery = $this->contextWhere(
                $db->table($table)
                    ->select('key')
                    ->where('class', $databaseClass),
                $context
            );

            $existingProperties = array_map(
                static fn(array $row): string => (string) $row['key'],
                $existingQuery->get()->getResultArray()
            );

            $existingByLower = [];

            foreach ($existingProperties as $property) {
                $existingByLower[strtolower($property)] = $property;
            }

            $values = [];
            $submitted = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new InvalidArgumentException(
                        'Format baris pengaturan tidak valid.'
                    );
                }

                $property = trim($this->postString(
                    $row['property'] ?? '',
                    'property'
                ));

                $type = strtolower(trim($this->postString(
                    $row['type'] ?? 'string',
                    'type'
                )));

                $rawValue = $this->postString(
                    $row['value'] ?? '',
                    'value'
                );

                // Abaikan baris baru yang belum diisi.
                if ($property === '' && trim($rawValue) === '') {
                    continue;
                }

                if (! $this->validProperty($property)) {
                    throw new InvalidArgumentException(
                        'Nama properti tidak valid.'
                    );
                }

                if (! in_array($type, self::ALLOWED_TYPES, true)) {
                    throw new InvalidArgumentException(
                        "Tipe data untuk {$property} tidak valid."
                    );
                }

                $normalized = strtolower($property);

                if (isset($submitted[$normalized])) {
                    throw new InvalidArgumentException(
                        "Properti {$property} dikirim lebih dari sekali."
                    );
                }

                $submitted[$normalized] = true;

                // Pertahankan kapitalisasi key yang sudah ada.
                $actualProperty = $existingByLower[$normalized] ?? $property;

                $values[$groupName . '.' . $actualProperty] =
                    $this->parseValue($rawValue, $type);
            }

            $forgetKeys = [];

            foreach ($forgetPost as $rawProperty) {
                $property = trim($this->postString(
                    $rawProperty,
                    'forget'
                ));

                if (! $this->validProperty($property)) {
                    throw new InvalidArgumentException(
                        'Nama properti yang dihapus tidak valid.'
                    );
                }

                $normalized = strtolower($property);

                if (isset($submitted[$normalized])) {
                    throw new InvalidArgumentException(
                        "Properti {$property} tidak dapat disimpan dan dihapus sekaligus."
                    );
                }

                if (! isset($existingByLower[$normalized])) {
                    throw new InvalidArgumentException(
                        "Properti {$property} tidak ditemukan pada tab ini."
                    );
                }

                $forgetKeys[] = $groupName . '.'
                    . $existingByLower[$normalized];
            }

            $db->transBegin();
            $transactionStarted = true;

            $settings = service('settings');

            if ($forgetKeys !== []) {
                $settings->forgetMany($forgetKeys, $context);
            }

            if ($values !== []) {
                $settings->setMany($values, $context);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException(
                    'Database menolak perubahan pengaturan.'
                );
            }

            if ($db->transCommit() === false) {
                throw new RuntimeException(
                    'Transaksi pengaturan gagal di-commit.'
                );
            }

            $transactionStarted = false;
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db()->transRollback();
            }

            log_message('error', 'Bulk settings gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            $message = $e instanceof InvalidArgumentException
                || $e instanceof JsonException
                ? $e->getMessage()
                : 'Pengaturan gagal disimpan. Periksa log aplikasi.';

            /* return redirect()->back()
                ->withInput()
                ->with('error', $message); */
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => 422,
                    'error'   => true,
                    'message' => $message,
                ]);
        }
        return $this->redirectToSettings()->with(
            'message',
            'Semua perubahan pada tab berhasil disimpan.'
        );
    }

    /**
     * Menambahkan class melalui properti awal.
     */
    public function addClass(): RedirectResponse
    {
        try {
            $classInput = trim($this->postString(
                $this->request->getPost('class') ?? '',
                'class'
            ));

            $property = trim($this->postString(
                $this->request->getPost('property') ?? '',
                'property'
            ));

            $type = strtolower(trim($this->postString(
                $this->request->getPost('type') ?? 'string',
                'type'
            )));

            $rawValue = $this->postString(
                $this->request->getPost('value') ?? '',
                'value'
            );

            $contextInput = $this->request->getPost('context');
            $context = $contextInput === null || $contextInput === ''
                ? null
                : trim($this->postString($contextInput, 'context'));

            if (! $this->validProperty($property)) {
                throw new InvalidArgumentException(
                    'Nama properti tidak valid.'
                );
            }

            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                throw new InvalidArgumentException(
                    'Tipe data tidak didukung.'
                );
            }

            $databaseClass = $this->databaseClass($classInput);
            $groupName = $this->displayGroup($databaseClass);
            $value = $this->parseValue($rawValue, $type);

            if (! empty($this->settingsConfig()->database['deferWrites'])) {
                throw new RuntimeException(
                    'Tambah class memerlukan deferWrites=false.'
                );
            }

            $db = $this->db();
            $table = $this->settingsTable();

            $duplicateQuery = $this->contextWhere(
                $db->table($table)
                    ->where('class', $databaseClass)
                    ->where('key', $property),
                $context
            );

            if ($duplicateQuery->countAllResults() > 0) {
                throw new InvalidArgumentException(
                    'Properti tersebut sudah ada pada class/context ini.'
                );
            }

            $db->transBegin();

            try {
                service('settings')->set(
                    $groupName . '.' . $property,
                    $value,
                    $context
                );

                if ($db->transStatus() === false) {
                    throw new RuntimeException(
                        'Penyimpanan class baru gagal.'
                    );
                }

                if ($db->transCommit() === false) {
                    throw new RuntimeException(
                        'Transaksi class baru gagal di-commit.'
                    );
                }
            } catch (Throwable $e) {
                $db->transRollback();
                throw $e;
            }
        } catch (Throwable $e) {
            log_message('error', 'Tambah class setting gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            $message = $e instanceof InvalidArgumentException
                || $e instanceof JsonException
                ? $e->getMessage()
                : 'Class baru gagal disimpan. Periksa konfigurasi dan log aplikasi.';

            return redirect()->back()
                ->withInput()
                ->with('error', $message);
        }

        return $this->redirectToSettings()->with(
            'message',
            'Class dan properti awal berhasil ditambahkan.'
        );
    }

    /**
     * Menghapus override agar nilai konfigurasi default dapat digunakan.
     */
    public function delete(): RedirectResponse
    {
        try {
            $group = trim($this->postString(
                $this->request->getPost('group') ?? '',
                'group'
            ));

            $property = trim($this->postString(
                $this->request->getPost('property') ?? '',
                'property'
            ));

            $contextInput = $this->request->getPost('context');
            $context = $contextInput === null || $contextInput === ''
                ? null
                : trim($this->postString($contextInput, 'context'));

            if (! $this->validProperty($property)) {
                throw new InvalidArgumentException(
                    'Nama properti tidak valid.'
                );
            }

            $databaseClass = $this->databaseClass($group);
            $groupName = $this->displayGroup($databaseClass);

            $db = $this->db();
            $table = $this->settingsTable();

            $exists = $this->contextWhere(
                $db->table($table)
                    ->where('class', $databaseClass)
                    ->where('key', $property),
                $context
            )->countAllResults();

            if ($exists === 0) {
                throw new InvalidArgumentException(
                    'Properti tidak ditemukan pada class/context ini.'
                );
            }

            service('settings')->forget(
                $groupName . '.' . $property,
                $context
            );
        } catch (Throwable $e) {
            log_message('error', 'Forget setting gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            $message = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Pengaturan gagal dihapus.';

            return redirect()->back()->with('error', $message);
        }

        return $this->redirectToSettings()->with(
            'message',
            'Override pengaturan berhasil dihapus.'
        );
    }
}
