<?php

declare(strict_types=1);

namespace Modules\Backend\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;
use Modules\Rbac\Repositories\PermissionRepository;
use RuntimeException;
use Throwable;

class IntegrationApiKeyService
{
    private const CLIENTS_TABLE = 'integration_api_clients';
    private const KEYS_TABLE = 'integration_api_keys';
    private const KEY_PREFIX = 'acn_live_';
    private const KEY_RANDOM_BYTES = 32;

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function createClient(
        string $name,
        string $slug,
        ?string $description,
        ?int $ownerUserId,
        ?int $createdBy
    ): int {
        $name = trim($name);
        $slug = strtolower(trim($slug));
        $description = $description !== null ? trim($description) : null;

        if ($name === '' || $slug === '') {
            throw new InvalidArgumentException(
                'Nama dan slug client wajib diisi.'
            );
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new InvalidArgumentException(
                'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.'
            );
        }

        if ($ownerUserId !== null && $ownerUserId < 1) {
            throw new InvalidArgumentException('Owner user ID tidak valid.');
        }

        if ($createdBy !== null && $createdBy < 1) {
            throw new InvalidArgumentException('Created by tidak valid.');
        }

        $exists = $this->db->table(self::CLIENTS_TABLE)
            ->where('slug', $slug)
            ->countAllResults();

        if ($exists > 0) {
            throw new RuntimeException('Slug client sudah digunakan.');
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table(self::CLIENTS_TABLE)->insert([
            'name'          => $name,
            'slug'          => $slug,
            'description'   => $description !== '' ? $description : null,
            'owner_user_id' => $ownerUserId,
            'status'        => 'active',
            'created_by'    => $createdBy,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Secret mentah hanya dikembalikan saat pembuatan key.
     * Database hanya menyimpan hash SHA-256.
     */
    public function createKey(
        int $clientId,
        string $name,
        array $scopes = [],
        ?string $expiresAt = null,
        ?int $createdBy = null
    ): array {
        $name = trim($name);

        if ($clientId < 1 || $name === '') {
            throw new InvalidArgumentException(
                'Client dan nama API Key wajib diisi.'
            );
        }

        if ($createdBy !== null && $createdBy < 1) {
            throw new InvalidArgumentException('Created by tidak valid.');
        }

        $normalizedScopes = $this->normalizeScopes($scopes);
        $expiresAt = $this->normalizeExpiry($expiresAt);

        $client = $this->db->table(self::CLIENTS_TABLE)
            ->select(['id', 'status'])
            ->where('id', $clientId)
            ->get()
            ->getRowArray();

        if ($client === null || $client['status'] !== 'active') {
            throw new RuntimeException(
                'Client tidak ditemukan atau tidak aktif.'
            );
        }

        $rawKey = self::KEY_PREFIX . bin2hex(random_bytes(self::KEY_RANDOM_BYTES));
        $prefix = substr($rawKey, 0, 24);
        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            $this->db->table(self::KEYS_TABLE)->insert([
                'client_id'    => $clientId,
                'name'         => $name,
                'key_prefix'   => $prefix,
                'secret_hash'  => hash('sha256', $rawKey),
                'scopes'       => json_encode(
                    $normalizedScopes,
                    JSON_THROW_ON_ERROR
                ),
                'expires_at'   => $expiresAt,
                'last_used_at' => null,
                'revoked_at'   => null,
                'created_by'   => $createdBy,
                'created_at'   => $now,
            ]);

            $keyId = (int) $this->db->insertID();

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Gagal menyimpan API Key.');
            }

            $this->db->transCommit();

            return [
                'id'         => $keyId,
                'client_id'  => $clientId,
                'name'       => $name,
                'key_prefix' => $prefix,
                'api_key'    => $rawKey,
                'scopes'     => $normalizedScopes,
                'expires_at' => $expiresAt,
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function listClients(): array
    {
        return $this->db->table(self::CLIENTS_TABLE)
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'owner_user_id',
                'status',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function listKeys(int $clientId): array
    {
        if ($clientId < 1) {
            throw new InvalidArgumentException('Client ID tidak valid.');
        }

        $keys = $this->db->table(self::KEYS_TABLE)
            ->select([
                'id',
                'client_id',
                'name',
                'key_prefix',
                'scopes',
                'expires_at',
                'last_used_at',
                'revoked_at',
                'created_by',
                'created_at',
            ])
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($keys as &$key) {
            $decoded = json_decode($key['scopes'] ?? '[]', true);
            $key['scopes'] = is_array($decoded) ? $decoded : [];
        }
        unset($key);

        return $keys;
    }

    public function revokeKey(int $keyId): bool
    {
        if ($keyId < 1) {
            return false;
        }

        $this->db->table(self::KEYS_TABLE)
            ->where('id', $keyId)
            ->where('revoked_at', null)
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows() === 1;
    }

    /**
     * Mengembalikan metadata key valid atau null.
     * Secret mentah dan secret_hash tidak pernah dikembalikan.
     */
    public function validateKey(string $rawKey): ?array
    {
        if (
            ! str_starts_with($rawKey, self::KEY_PREFIX)
            || strlen($rawKey) !== strlen(self::KEY_PREFIX) + (self::KEY_RANDOM_BYTES * 2)
            || ! ctype_xdigit(substr($rawKey, strlen(self::KEY_PREFIX)))
        ) {
            return null;
        }

        $prefix = substr($rawKey, 0, 24);
        $now = date('Y-m-d H:i:s');

        $keys = $this->db->table(self::KEYS_TABLE)
            ->where('key_prefix', $prefix)
            ->where('revoked_at', null)
            ->groupStart()
            ->where('expires_at', null)
            ->orWhere('expires_at >', $now)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $hash = hash('sha256', $rawKey);
        $matched = null;

        foreach ($keys as $key) {
            $storedHash = (string) ($key['secret_hash'] ?? '');

            if (
                strlen($storedHash) === 64
                && hash_equals($storedHash, $hash)
            ) {
                $matched = $key;
                break;
            }
        }

        if ($matched === null) {
            return null;
        }

        $client = $this->db->table(self::CLIENTS_TABLE)
            ->where('id', (int) $matched['client_id'])
            ->where('status', 'active')
            ->get()
            ->getRowArray();

        if ($client === null) {
            return null;
        }

        $decodedScopes = json_decode($matched['scopes'] ?? '[]', true);
        $scopes = is_array($decodedScopes)
            ? $this->normalizeScopes($decodedScopes)
            : [];

        // Perbarui penggunaan hanya setelah key dan client dinyatakan valid.
        $this->db->table(self::KEYS_TABLE)
            ->where('id', (int) $matched['id'])
            ->where('revoked_at', null)
            ->groupStart()
            ->where('expires_at', null)
            ->orWhere('expires_at >', $now)
            ->groupEnd()
            ->update([
                'last_used_at' => $now,
            ]);

        return [
            'key_id'   => (int) $matched['id'],
            'client_id' => (int) $matched['client_id'],
            'client'   => $client,
            'scopes'   => $scopes,
        ];
    }

    public function updateClientStatus(int $clientId, string $status): bool
    {
        if ($clientId < 1) {
            throw new InvalidArgumentException('Client ID tidak valid.');
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Status client tidak valid.');
        }

        $client = $this->db->table(self::CLIENTS_TABLE)
            ->select(['id', 'status'])
            ->where('id', $clientId)
            ->get()
            ->getRowArray();

        if ($client === null) {
            throw new RuntimeException('Client integrasi tidak ditemukan.');
        }

        if ($client['status'] === $status) {
            return true;
        }

        $this->db->table(self::CLIENTS_TABLE)
            ->where('id', $clientId)
            ->update([
                'status'     => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows() === 1;
    }

    /**
     * Normalisasi scope untuk penyimpanan dan validasi.
     */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = [];

        foreach ($scopes as $scope) {
            if (! is_string($scope)) {
                throw new InvalidArgumentException(
                    'Format scope API Key tidak valid.'
                );
            }

            $scope = trim($scope);

            if ($scope === '') {
                continue;
            }

            if (! preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $scope)) {
                throw new InvalidArgumentException(
                    'Format scope API Key tidak valid: ' . $scope
                );
            }

            $normalized[] = $scope;
        }

        return array_values(array_unique($normalized));
    }

    public function listAvailableScopes(): array
    {
        $scopes = (new IntegrationApiScopeService())
            ->listScopes(true);

        return array_map(
            static fn(array $item): array => [
                'scope'       => $item['scope'],
                'description' => $item['description'] ?? '',
            ],
            $scopes
        );
    }

    public function listAvailableScopes2(): array
    {
        $permissions = (new PermissionRepository($this->db))->findAll();
        $scopes = [];

        foreach ($permissions as $permission) {
            $permissionName = (string) ($permission['name'] ?? '');

            // Hanya permission backend yang dipertimbangkan.
            if (! str_starts_with($permissionName, 'backend.')) {
                continue;
            }

            // backend.inventory.read menjadi inventory.read.
            $scope = substr($permissionName, strlen('backend.'));

            if (! preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $scope)) {
                continue;
            }

            $scopes[] = [
                'scope'       => $scope,
                'description' => (string) ($permission['description'] ?? ''),
            ];
        }

        return $scopes;
        return [
            [
                'scope'       => 'inventory.read',
                'description' => 'Membaca data inventaris.',
            ],
            [
                'scope'       => 'inventory.write',
                'description' => 'Menambah dan memperbarui data inventaris.',
            ],
            [
                'scope'       => 'products.read',
                'description' => 'Membaca data produk.',
            ],
            [
                'scope'       => 'products.write',
                'description' => 'Menambah dan memperbarui data produk.',
            ],
            [
                'scope'       => 'orders.read',
                'description' => 'Membaca data pesanan.',
            ],
            [
                'scope'       => 'orders.write',
                'description' => 'Membuat dan memperbarui data pesanan.',
            ],
            [
                'scope'       => 'customers.read',
                'description' => 'Membaca data pelanggan.',
            ],
            [
                'scope'       => 'customers.write',
                'description' => 'Menambah dan memperbarui data pelanggan.',
            ],
            [
                'scope'       => 'reports.read',
                'description' => 'Membaca laporan bisnis.',
            ],
            [
                'scope'       => 'transactions.read',
                'description' => 'Membaca data transaksi.',
            ],
        ];
    }

    /**
     * Menolak tanggal yang tidak valid atau sudah lewat.
     */
    private function normalizeExpiry(?string $expiresAt): ?string
    {
        if ($expiresAt === null || trim($expiresAt) === '') {
            return null;
        }

        $timestamp = strtotime($expiresAt);

        if ($timestamp === false || $timestamp <= time()) {
            throw new InvalidArgumentException(
                'Masa berlaku API Key harus berada di masa depan.'
            );
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
