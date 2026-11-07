<?php
$organizations = $organizations ?? [];
$keyword       = $keyword ?? '';
$status        = $status ?? '';

$e = static fn($value): string => esc((string) ($value ?? ''));
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Master Organization</h4>
            <p class="text-muted mb-0">
                Kelola organisasi dan perusahaan dalam ekosistem ERP.
            </p>
        </div>

        <a href="<?= site_url('core/organizations/create') ?>"
            class="btn btn-primary">
            Tambah Organisasi
        </a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success">
            <?= $e(session()->getFlashdata('success')) ?>
        </div>
    <?php endif ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger">
            <?= $e(session()->getFlashdata('error')) ?>
        </div>
    <?php endif ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get"
                action="<?= site_url('core/organizations') ?>"
                class="row g-2">

                <div class="col-md-6">
                    <input
                        type="search"
                        name="q"
                        class="form-control"
                        placeholder="Cari kode, nama, nama legal, atau slug"
                        value="<?= $e($keyword) ?>">
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
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

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        Cari
                    </button>

                    <a href="<?= site_url('core/organizations') ?>"
                        class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Organisasi</th>
                        <th>Jenis</th>
                        <th>Mata Uang</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($organizations === []): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Belum ada data organisasi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($organizations as $organization): ?>
                            <tr>
                                <td>
                                    <strong><?= $e($organization['code']) ?></strong>
                                    <div class="small text-muted">
                                        <?= $e($organization['slug']) ?>
                                    </div>
                                </td>

                                <td>
                                    <strong><?= $e($organization['name']) ?></strong>

                                    <?php if (! empty($organization['legal_name'])): ?>
                                        <div class="small text-muted">
                                            <?= $e($organization['legal_name']) ?>
                                        </div>
                                    <?php endif ?>
                                </td>

                                <td><?= $e($organization['organization_type']) ?></td>
                                <td><?= $e($organization['currency']) ?></td>

                                <td>
                                    <?php if ($organization['status'] === 'active'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif ?>
                                </td>

                                <td class="text-end text-nowrap">
                                    <a
                                        href="<?= site_url(
                                                    'core/organizations/edit/' . (int) $organization['id']
                                                ) ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        Edit
                                    </a>

                                    <?php if ($organization['status'] === 'active'): ?>
                                        <form
                                            method="post"
                                            action="<?= site_url(
                                                        'core/organizations/deactivate/' .
                                                            (int) $organization['id']
                                                    ) ?>"
                                            class="d-inline"
                                            onsubmit="return confirm('Nonaktifkan organisasi ini?')">

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
</div>
