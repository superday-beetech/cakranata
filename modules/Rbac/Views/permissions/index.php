<div class="container-fluid py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-1">
                <?= esc($title) ?>
            </h4>
            <p class="text-muted mb-0">
                Pilih permission dari route registry untuk didaftarkan
                ke katalog permission Shield.
            </p>
        </div>

        <a href="<?= site_url('backend/permission-sync') ?>"
            class="btn btn-outline-secondary">
            <i class="bi bi-arrow-clockwise"></i>
            Muat Ulang
        </a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= esc(session()->getFlashdata('success')) ?>
            <button type="button" class="btn-close"
                data-bs-dismiss="alert"></button>
        </div>
    <?php endif ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= esc(session()->getFlashdata('error')) ?>
            <button type="button" class="btn-close"
                data-bs-dismiss="alert"></button>
        </div>
    <?php endif ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Permission sumber</div>
                    <div class="fs-3 fw-semibold">
                        <?= number_format($total_source) ?>
                    </div>
                    <div class="small text-muted">
                        Dari route_registry_permissions
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Sudah terdaftar</div>
                    <div class="fs-3 fw-semibold text-success">
                        <?= number_format($total_registered) ?>
                    </div>
                    <div class="small text-muted">
                        Ada di auth_permissions
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Belum terdaftar</div>
                    <div class="fs-3 fw-semibold text-warning">
                        <?= number_format($total_missing) ?>
                    </div>
                    <div class="small text-muted">
                        Dapat dipilih untuk disinkronkan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between
                    align-items-center gap-2">
            <span class="fw-semibold">Daftar Permission</span>

            <span class="small text-muted">
                Centang hanya permission yang ingin ditambahkan.
            </span>
        </div>

        <form action="<?= site_url('rbac/group-permissions/synchronize') ?>"
            method="post">

            <?= csrf_field() ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 48px">
                                <input type="checkbox"
                                    class="form-check-input"
                                    id="selectAll"
                                    aria-label="Pilih semua permission yang belum terdaftar">
                            </th>
                            <th>Nama Permission</th>
                            <th class="text-center">Jumlah Route</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($permissions)): ?>
                            <tr>
                                <td colspan="4"
                                    class="text-center text-muted py-4">
                                    Belum ada permission pada tabel sumber.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($permissions as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (! $item['exists']): ?>
                                            <input
                                                type="checkbox"
                                                class="form-check-input permission-checkbox"
                                                name="permissions[]"
                                                value="<?= esc($item['name'], 'attr') ?>"
                                                aria-label="Pilih <?= esc($item['name'], 'attr') ?>">
                                        <?php else: ?>
                                            <input type="checkbox"
                                                class="form-check-input"
                                                disabled
                                                aria-label="Permission sudah terdaftar">
                                        <?php endif ?>
                                    </td>

                                    <td>
                                        <code><?= esc($item['name']) ?></code>
                                    </td>

                                    <td class="text-center">
                                        <?= number_format($item['route_count']) ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($item['exists']): ?>
                                            <span class="badge bg-success">
                                                Sudah terdaftar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                Belum terdaftar
                                            </span>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex flex-wrap justify-content-between
                        align-items-center gap-2">
                <span class="small text-muted">
                    Permission yang sudah terdaftar tidak akan ditambahkan ulang.
                </span>

                <button type="submit"
                    class="btn btn-primary"
                    <?= $total_missing === 0 ? 'disabled' : '' ?>
                    onclick="return confirm(
                            'Sinkronkan permission yang dipilih?'
                        )">
                    <i class="bi bi-arrow-repeat"></i>
                    Sinkronkan Pilihan
                </button>
            </div>
        </form>
    </div>

</div>
<?= $this->section('jsFooterInline'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.permission-checkbox');

        if (!selectAll) {
            return;
        }

        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });

        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                selectAll.checked =
                    checkboxes.length > 0 &&
                    Array.from(checkboxes).every(function(item) {
                        return item.checked;
                    });
            });
        });
    });
</script>

<?= $this->endSection() ?>
