<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-1">Template Email</h4>
            <p class="text-muted mb-0">
                Kelola template email sistem ACN ERP.
            </p>
        </div>

        <?php if ($canManage): ?>
            <a href="<?= site_url('backend/email-templates/new') ?>"
                class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Tambah Template
            </a>
        <?php endif; ?>
    </div>

    <?php if ($message = session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc($message) ?></div>
    <?php endif; ?>

    <?php if ($message = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="get"
                action="<?= site_url('backend/email-templates') ?>"
                class="row g-2 mb-3">

                <div class="col-md-4">
                    <label class="form-label" for="category">Kategori</label>
                    <input type="text"
                        id="category"
                        name="category"
                        class="form-control"
                        value="<?= esc($category) ?>"
                        placeholder="system, account, notification">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="active">Status</label>
                    <select name="active" id="active" class="form-select">
                        <option value="">Semua status</option>
                        <option value="1" <?= $active === 1 ? 'selected' : '' ?>>
                            Aktif
                        </option>
                        <option value="0" <?= $active === 0 ? 'selected' : '' ?>>
                            Nonaktif
                        </option>
                    </select>
                </div>

                <div class="col-md-5 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>

                    <a href="<?= site_url('backend/email-templates') ?>"
                        class="btn btn-light">
                        Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px">No.</th>
                            <th>Kode</th>
                            <th>Nama Template</th>
                            <th>Kategori</th>
                            <th>Subjek</th>
                            <th>Status</th>
                            <th>Sistem</th>
                            <?php if ($canManage): ?>
                                <th style="width:220px">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($templates === []): ?>
                            <tr>
                                <td colspan="<?= $canManage ? 8 : 7 ?>"
                                    class="text-center text-muted py-4">
                                    Belum ada template email.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $index => $row): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><code><?= esc($row['code']) ?></code></td>
                                    <td>
                                        <strong><?= esc($row['name']) ?></strong>
                                        <?php if (! empty($row['description'])): ?>
                                            <div class="small text-muted">
                                                <?= esc($row['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc($row['category']) ?></td>
                                    <td><?= esc($row['subject']) ?></td>
                                    <td>
                                        <?php if ((int) $row['is_active'] === 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= (int) $row['is_system'] === 1
                                            ? '<span class="badge bg-info">Sistem</span>'
                                            : '<span class="badge bg-light text-dark">Kustom</span>' ?>
                                    </td>

                                    <?php if ($canManage): ?>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <a href="<?= site_url('backend/email-templates/edit/' . $row['id']) ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                                <a href="<?= site_url('backend/email-templates/preview/' . $row['id']) ?>"
                                                    class="btn btn-sm btn-outline-info"
                                                    title="Preview"
                                                    target="_self">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                <form method="post"
                                                    action="<?= site_url('backend/email-templates/' . $row['id'] . '/toggle-active') ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit"
                                                        class="btn btn-sm btn-outline-warning"
                                                        title="<?= (int) $row['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                        <i class="ti ti-power"></i>
                                                    </button>
                                                </form>

                                                <?php if ((int) $row['is_system'] !== 1): ?>
                                                    <form method="post"
                                                        action="<?= site_url('backend/email-templates/' . $row['id'] . '/delete') ?>"
                                                        onsubmit="return confirm('Yakin ingin menghapus template ini?')">
                                                        <?= csrf_field() ?>
                                                        <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Hapus">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
