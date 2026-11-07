<?php

declare(strict_types=1);

namespace Modules\Backend\Controllers\API\V1;

use App\Core\ApiController;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

class SettingApiController extends ApiController
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

    /**
     * Mengubah nilai JSON/API menjadi tipe data sesuai pengaturan.
     */
    private function parseValue(mixed $value, string $type): mixed
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Tipe data tidak didukung.');
        }

        return match ($type) {
            'string' => is_scalar($value) || $value === null
                ? (string) ($value ?? '')
                : throw new InvalidArgumentException(
                    'Nilai string tidak valid.'
                ),

            'integer' => filter_var(
                is_scalar($value) ? (string) $value : '',
                FILTER_VALIDATE_INT
            ) !== false
                ? (int) $value
                : throw new InvalidArgumentException(
                    'Nilai harus berupa bilangan bulat.'
                ),

            'float' => is_numeric($value)
                && is_finite((float) $value)
                ? (float) $value
                : throw new InvalidArgumentException(
                    'Nilai harus berupa angka yang valid.'
                ),

            'boolean' => is_bool($value)
                ? $value
                : match (strtolower(trim((string) $value))) {
                    'true', '1', 'yes', 'on' => true,
                    'false', '0', 'no', 'off' => false,
                    default => throw new InvalidArgumentException(
                        'Nilai boolean tidak valid.'
                    ),
                },

            'json' => $this->parseJsonValue($value),
        };
    }

    private function parseJsonValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        // JSON API dapat mengirim array, object, angka, boolean, atau null.
        json_encode($value, JSON_THROW_ON_ERROR);

        return $value;
    }

    private function inputString(mixed $value, string $field): string
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

    private function contextValue(array $data): ?string
    {
        $context = $data['context'] ?? null;

        if ($context === null || $context === '') {
            return null;
        }

        return trim($this->inputString($context, 'context'));
    }

    private function contextWhere($builder, ?string $context)
    {
        return $context === null
            ? $builder->where('context', null)
            : $builder->where('context', $context);
    }

    private function jsonBody(): array
    {
        $data = $this->request->getJSON(true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('Body JSON tidak valid.');
        }

        return $data;
    }

    /**
     * GET /api/v1/backend/settings
     * Daftar pengaturan berdasarkan class dan context.
     */
    public function index()
    {
        try {
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
                    'type' => $this->normalizeType(
                        (string) $row['type']
                    ),
                    'value' => $settings->get(
                        $groupName . '.' . $property,
                        $context
                    ),
                ];
            }

            return $this->successResponse([
                'groups' => array_values($groups),
                'label' => 'Pengaturan Aplikasi',
            ], 'Pengaturan berhasil dimuat.');
        } catch (Throwable $e) {
            log_message(
                'error',
                '[Backend Settings][getIndex] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memuat pengaturan aplikasi.',
                500
            );
        }
    }

    /**
     * POST /api/v1/backend/settings/add-class
     * Menambahkan class melalui properti awal.
     */
    public function addClass()
    {
        $transactionStarted = false;
        $db = null;

        try {
            $data = $this->jsonBody();

            $classInput = trim($this->inputString(
                $data['class'] ?? '',
                'class'
            ));

            $property = trim($this->inputString(
                $data['property'] ?? '',
                'property'
            ));

            $type = strtolower(trim($this->inputString(
                $data['type'] ?? 'string',
                'type'
            )));

            $rawValue = $data['value'] ?? '';
            $context = $this->contextValue($data);

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
            $groupName = $databaseClass;
            $value = $this->parseValue($rawValue, $type);

            if (! empty($this->settingsConfig()->database['deferWrites'])) {
                return $this->errorResponse(
                    'Tambah class memerlukan deferWrites=false.',
                    409
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
            $transactionStarted = true;

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

            $transactionStarted = false;

            return $this->successResponse([
                'class' => $databaseClass,
                'property' => $property,
                'context' => $context,
            ], 'Class dan properti awal berhasil ditambahkan.', 201);
        } catch (InvalidArgumentException | JsonException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            if ($transactionStarted && $db !== null) {
                $db->transRollback();
            }

            log_message(
                'error',
                '[Backend Settings][addClass] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Class baru gagal disimpan. Periksa konfigurasi dan log aplikasi.',
                500
            );
        }
    }

    /**
     * PUT /api/v1/backend/settings/bulk-save
     * Menyimpan perubahan pada satu kelompok class/context.
     */
    public function bulkSave()
    {
        $transactionStarted = false;
        $db = null;

        try {
            $data = $this->jsonBody();

            $group = trim($this->inputString(
                $data['group'] ?? '',
                'group'
            ));

            $context = $this->contextValue($data);
            $rows = $data['rows'] ?? [];
            $forgetPost = $data['forget'] ?? [];

            if (! is_array($rows) || ! is_array($forgetPost)) {
                throw new InvalidArgumentException(
                    'Format data pengaturan tidak valid.'
                );
            }

            if (
                $group === ''
                || preg_match('~^[A-Za-z_][A-Za-z0-9_\\\\]*$~', $group) !== 1
            ) {
                throw new InvalidArgumentException('Nama class tidak valid.');
            }

            $databaseClass = $group;
            $groupName = $this->displayGroup($databaseClass);

            if (! empty($this->settingsConfig()->database['deferWrites'])) {
                return $this->errorResponse(
                    'Bulk save memerlukan deferWrites=false.',
                    409
                );
            }

            $db = $this->db();
            $table = $this->settingsTable();

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

                $property = trim($this->inputString(
                    $row['property'] ?? '',
                    'property'
                ));

                $type = strtolower(trim($this->inputString(
                    $row['type'] ?? 'string',
                    'type'
                )));

                $rawValue = $row['value'] ?? '';

                // Abaikan baris baru yang belum diisi.
                if (
                    $property === ''
                    && ($rawValue === '' || $rawValue === null)
                ) {
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
                $property = trim($this->inputString(
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

            return $this->successResponse([
                'group' => $databaseClass,
                'context' => $context,
                'saved' => count($values),
                'deleted' => count($forgetKeys),
            ], 'Semua perubahan pada tab berhasil disimpan.');
        } catch (InvalidArgumentException | JsonException $e) {
            if ($transactionStarted && $db !== null) {
                $db->transRollback();
            }

            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            if ($transactionStarted && $db !== null) {
                $db->transRollback();
            }

            log_message(
                'error',
                '[Backend Settings][bulkSave] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Pengaturan gagal disimpan. Periksa log aplikasi.',
                500
            );
        }
    }

    /**
     * DELETE /api/v1/backend/settings/delete
     * Menghapus override agar nilai konfigurasi default digunakan.
     */
    public function getDelete()
    {
        try {
            $data = $this->jsonBody();

            $group = trim($this->inputString(
                $data['group'] ?? '',
                'group'
            ));

            $property = trim($this->inputString(
                $data['property'] ?? '',
                'property'
            ));

            $context = $this->contextValue($data);

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

            return $this->successResponse([
                'group' => $databaseClass,
                'property' => $property,
                'context' => $context,
            ], 'Override pengaturan berhasil dihapus.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[Backend Settings][delete] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Pengaturan gagal dihapus.',
                500
            );
        }
    }
}
