<div class="py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><?= esc($title) ?></h4>
            <div class="text-muted">
                User: <strong><?= esc($user['username']) ?></strong>
                (ID <?= (int) $user['id'] ?>)
            </div>
        </div>

        <a href="<?= site_url('rbac/users') ?>"
            class="btn btn-outline-secondary">
            Kembali ke Users
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Tambah / Perbarui Keanggotaan</h5>
        </div>

        <div class="card-body">
            <form method="post"
                action="<?= site_url('core/organization-memberships/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="organization_id" class="form-label">
                            Organisasi
                        </label>
                        <select id="organization_id"
                            name="organization_id"
                            class="form-select"
                            required>
                            <option value="">-- Pilih Organisasi --</option>
                            <?php foreach ($organizations as $organization): ?>
                                <option
                                    value="<?= (int) $organization['id'] ?>"
                                    <?= (string) old('organization_id') === (string) $organization['id'] ? 'selected' : '' ?>>
                                    <?= esc($organization['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="unit_id" class="form-label">
                            Unit Organisasi
                        </label>
                        <select id="unit_id" name="unit_id" class="form-select">
                            <option value="">-- Tanpa Unit --</option>
                            <?php foreach ($units as $unit): ?>
                                <option
                                    value="<?= (int) $unit['id'] ?>"
                                    data-organization="<?= (int) $unit['organization_id'] ?>"
                                    <?= (string) old('unit_id') === (string) $unit['id'] ? 'selected' : '' ?>>
                                    <?= esc($unit['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="membership_role" class="form-label">
                            Peran Keanggotaan
                        </label>
                        <input
                            id="membership_role"
                            name="membership_role"
                            class="form-control"
                            maxlength="50"
                            required
                            value="<?= esc(old('membership_role', 'member'), 'attr') ?>">
                        <div class="form-text">
                            Peran organisasi, bukan permission aplikasi.
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="active"
                                <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>
                                Aktif
                            </option>
                            <option value="inactive"
                                <?= old('status') === 'inactive' ? 'selected' : '' ?>>
                                Nonaktif
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3 d-flex align-items-center">
                        <div class="form-check mt-3">
                            <input type="hidden" name="is_default" value="0">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="is_default"
                                name="is_default"
                                value="1"
                                <?= old('is_default') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_default">
                                Jadikan organisasi default
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    Simpan Keanggotaan
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Keanggotaan</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Organisasi</th>
                        <th>Unit</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Default</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($memberships)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Belum ada keanggotaan organisasi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($memberships as $membership): ?>
                            <tr>
                                <td><?= esc($membership['organization_name']) ?></td>
                                <td><?= esc($membership['unit_name'] ?? '-') ?></td>
                                <td><?= esc($membership['membership_role']) ?></td>
                                <td>
                                    <?= esc($membership['status']) ?>
                                </td>
                                <td>
                                    <?php if ((int) $membership['is_default'] === 1): ?>
                                        <span class="badge bg-success">Default</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post"
                                        action="<?= site_url('core/organization-memberships/delete') ?>"
                                        onsubmit="return confirm('Hapus keanggotaan ini?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id"
                                            value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="membership_id"
                                            value="<?= (int) $membership['id'] ?>">
                                        <button type="submit"
                                            class="btn btn-sm btn-outline-danger">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->section('jsFooterInline'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const organization = document.getElementById('organization_id');
        const unit = document.getElementById('unit_id');

        function filterUnits() {
            const organizationId = organization.value;

            Array.from(unit.options).forEach(function(option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                option.hidden = option.dataset.organization !== organizationId;

                if (option.hidden && option.selected) {
                    unit.value = '';
                }
            });
        }

        organization.addEventListener('change', filterUnits);
        filterUnits();
    });
</script>

<?= $this->endSection(); ?>
