<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Models\OrganizationModel;
use RuntimeException;
use Throwable;

class OrganizationService
{
    protected OrganizationModel $organizations;

    public function __construct()
    {
        $this->organizations = new OrganizationModel();
    }

    public function getList(string $keyword = '', string $status = ''): array
    {
        $builder = $this->organizations
            ->where('deleted_at', null);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('code', $keyword)
                ->orLike('name', $keyword)
                ->orLike('legal_name', $keyword)
                ->orLike('slug', $keyword)
                ->groupEnd();
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $builder->where('status', $status);
        }

        return $builder->orderBy('name', 'ASC')->findAll();
    }

    public function getById(int $id): ?array
    {
        return $this->organizations->findAvailable($id);
    }

    public function save(array $data, ?int $id = null): int
    {
        $code = trim((string) ($data['code'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $legalName = trim((string) ($data['legal_name'] ?? ''));
        $type = trim((string) ($data['organization_type'] ?? 'business'));
        $status = trim((string) ($data['status'] ?? 'active'));
        $timezone = trim((string) ($data['timezone'] ?? 'Asia/Jakarta'));
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'IDR')));
        $settings = trim((string) ($data['settings'] ?? ''));

        if ($code === '' || $name === '' || $slug === '') {
            throw new RuntimeException('Kode, slug, dan nama organisasi wajib diisi.');
        }

        if (
            mb_strlen($code) > 50 || mb_strlen($slug) > 100
            || mb_strlen($name) > 150 || mb_strlen($legalName) > 200
        ) {
            throw new RuntimeException('Panjang salah satu field melebihi batas database.');
        }

        if ($type === '' || mb_strlen($type) > 50) {
            throw new RuntimeException('Jenis organisasi tidak valid.');
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Status organisasi tidak valid.');
        }

        if ($timezone === '' || mb_strlen($timezone) > 64) {
            throw new RuntimeException('Timezone tidak valid.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new RuntimeException('Mata uang harus berupa kode tiga huruf, misalnya IDR.');
        }

        if ($settings !== '') {
            json_decode($settings);

            if (
                json_last_error() !== JSON_ERROR_NONE
                || ! is_array(json_decode($settings, true))
            ) {
                throw new RuntimeException('Settings harus berupa JSON object atau array yang valid.');
            }
        } else {
            $settings = null;
        }

        $duplicateCode = $this->organizations
            ->where('code', $code)
            ->where('deleted_at', null)
            ->first();

        if ($duplicateCode && (int) $duplicateCode['id'] !== $id) {
            throw new RuntimeException('Kode organisasi sudah digunakan.');
        }

        $duplicateSlug = $this->organizations
            ->where('slug', $slug)
            ->where('deleted_at', null)
            ->first();

        if ($duplicateSlug && (int) $duplicateSlug['id'] !== $id) {
            throw new RuntimeException('Slug organisasi sudah digunakan.');
        }

        $payload = [
            'code'              => $code,
            'slug'              => $slug,
            'name'              => $name,
            'legal_name'        => $legalName !== '' ? $legalName : null,
            'organization_type' => $type,
            'status'            => $status,
            'timezone'          => $timezone,
            'currency'          => $currency,
            'settings'          => $settings,
        ];

        if ($id !== null) {
            if (! $this->organizations->findAvailable($id)) {
                throw new RuntimeException('Organisasi tidak ditemukan.');
            }

            $success = $this->organizations->update($id, $payload);
            if (! $success) {
                throw new RuntimeException(
                    implode(' ', $this->organizations->errors())
                        ?: 'Gagal memperbarui organisasi.'
                );
            }

            return $id;
        }

        $newId = $this->organizations->insert($payload, true);

        if ($newId === false) {
            throw new RuntimeException(
                implode(' ', $this->organizations->errors())
                    ?: 'Gagal menambahkan organisasi.'
            );
        }

        return (int) $newId;
    }

    public function deactivate(int $id): void
    {
        $organization = $this->organizations->findAvailable($id);

        if (! $organization) {
            throw new RuntimeException('Organisasi tidak ditemukan.');
        }

        if ($organization['status'] === 'inactive') {
            return;
        }

        if (! $this->organizations->update($id, ['status' => 'inactive'])) {
            throw new RuntimeException('Gagal menonaktifkan organisasi.');
        }
    }
}
