<?php
$organizations = $organizations ?? [];
$units = $units ?? [];
$selectedOrganizationId = $selectedOrganizationId ?? null;
$keyword = $keyword ?? '';
$status = $status ?? '';

$e = static fn($value): string => esc((string) ($value ?? ''));

$unitMap = [];
foreach ($units as $unit) {
    $unitMap[(string) $unit['id']] = $unit;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Master Organization Unit</h4>
            <p class="text-muted mb-0">
                Kelola struktur organisasi dan hierarki unit.
            </p>
        </div>

        <?php if ($selectedOrganizationId !== null): ?>
            <a href="<?= site_url(
                            'core/organization-units/create?organization_id=' .
                                (int) $selectedOrganizationId
                        ) ?>" class="btn btn-primary">
                Tambah Unit
            </a>
        <?php endif ?>
    </div>

    <?php if ($message = session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= $e($message) ?></div>
    <?php endif ?>

    <?php if ($message = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= $e($message) ?></div>
    <?php endif ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get"
                action="<?= site_url('core/organization-units') ?>"
                class="row g-2">

                <div class="col-md-4">
                    <label for="organization_id" class="form-label">
                        Organisasi
                    </label>

                    <select id="organization_id" name="organization_id"
                        class="form-select" required>
                        <option value="">Pilih organisasi</option>

                        <?php foreach ($organizations as $organization): ?>
                            <option
                                value="<?= (int) $organization['id'] ?>"
                                <?= (int) $selectedOrganizationId ===
                                    (int) $organization['id'] ? 'selected' : '' ?>>
                                <?= $e($organization['code']) ?> —
                                <?= $e($organization['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="q" class="form-label">Pencarian</label>
                    <input id="q" name="q" type="search"
                        class="form-control"
                        value="<?= $e($keyword) ?>"
                        placeholder="Kode, nama, jenis unit">
                </div>

                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="active"
                            <?= $status === 'active' ? 'selected' : '' ?>>
                            Aktif
                        </option>
                        <option value="inactive"
                            <?= $status === 'inactive' ? 'selected' : '' ?>>
                            Nonaktif
                        </option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        Tampilkan
                    </button>
                    <a href="<?= site_url('core/organization-units') ?>"
                        class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedOrganizationId === null): ?>
        <div class="alert alert-info">
            Pilih organisasi terlebih dahulu untuk melihat unit-unitnya.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Unit</th>
                            <th>Parent</th>
                            <th>Jenis</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($units === []): ?>
                            <tr>
                                <td colspan="6"
                                    class="text-center text-muted py-4">
                                    Belum ada unit untuk organisasi ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($units as $unit): ?>
                                <?php
                                $parentId = $unit['parent_id'] ?? null;
                                $parent = $parentId !== null
                                    ? ($unitMap[(string) $parentId] ?? null)
                                    : null;
                                ?>
                                <tr>
                                    <td><?= $e($unit['code']) ?></td>

                                    <td>
                                        <strong><?= $e($unit['name']) ?></strong>
                                    </td>

                                    <td>
                                        <?= $parent
                                            ? $e($parent['name'])
                                            : '<span class="text-muted">Unit utama</span>' ?>
                                    </td>

                                    <td><?= $e($unit['unit_type']) ?></td>

                                    <td>
                                        <?php if ($unit['status'] === 'active'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif ?>
                                    </td>

                                    <td class="text-end text-nowrap">
                                        <a href="<?= site_url(
                                                        'core/organization-units/edit/' .
                                                            (int) $unit['id']
                                                    ) ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>

                                        <?php if ($unit['status'] === 'active'): ?>
                                            <form method="post"
                                                action="<?= site_url(
                                                            'core/organization-units/deactivate/' .
                                                                (int) $unit['id']
                                                        ) ?>"
                                                class="d-inline"
                                                onsubmit="return confirm('Nonaktifkan unit ini?')">
                                                <?= csrf_field() ?>

                                                <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                    Nonaktifkan
                                                </button>
                                            </form>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif ?>
</div>
