<?php
$membership = $membership ?? [];
$organizations = $organizations ?? [];
$units = $units ?? [];
$isEdit = (bool) ($isEdit ?? false);

$organizationId = (int) ($membership['organization_id'] ?? 0);
$userId = (int) ($membership['user_id'] ?? 0);
$unitId = $membership['unit_id'] ?? '';
$role = (string) ($membership['membership_role'] ?? 'member');
$status = (string) ($membership['status'] ?? 'active');
$isDefault = (int) ($membership['is_default'] ?? 0);
?>

<div class="container-fluid">
    <div class="mb-4">
        <h4 class="mb-1">
            <?= $isEdit ? 'Edit Membership' : 'Tambah Membership' ?>
        </h4>
        <p class="text-muted mb-0">
            Hubungkan user dengan organisasi dan unitnya.
        </p>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" action="<?= esc($formAction ?? '') ?>">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Organisasi <span class="text-danger">*</span></label>

                        <select name="organization_id"
                            id="organization_id"
                            class="form-select"
                            required
                            <?= $isEdit ? 'disabled' : '' ?>>
                            <option value="">Pilih organisasi</option>
                            <?php foreach ($organizations as $organization): ?>
                                <option
                                    value="<?= (int) $organization['id'] ?>"
                                    <?= $organizationId === (int) $organization['id']
                                        ? 'selected' : '' ?>>
                                    <?= esc($organization['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($isEdit): ?>
                            <input type="hidden"
                                name="organization_id"
                                value="<?= $organizationId ?>">
                            <small class="text-muted">
                                Organisasi tidak dapat diganti saat mengedit.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">User ID <span class="text-danger">*</span></label>

                        <?php if ($isEdit): ?>
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            <input type="text"
                                class="form-control"
                                value="<?= $userId ?>"
                                disabled>
                            <small class="text-muted">
                                User tidak dapat diganti saat mengedit.
                            </small>
                        <?php else: ?>
                            <select name="user_id"
                                id="user_id"
                                class="form-select"
                                required>
                                <option value="">Pilih user</option>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Unit Organisasi</label>
                        <select name="unit_id"
                            id="unit_id"
                            class="form-select">
                            <option value="">Tanpa unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option
                                    value="<?= (int) $unit['id'] ?>"
                                    <?= (string) $unitId === (string) $unit['id']
                                        ? 'selected' : '' ?>>
                                    <?= esc($unit['code'] . ' — ' . $unit['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Role Organisasi <span class="text-danger">*</span></label>
                        <input type="text"
                            name="membership_role"
                            class="form-control"
                            maxlength="50"
                            required
                            value="<?= esc($role) ?>"
                            placeholder="Contoh: owner, manager, member">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="membership_status" class="form-select">
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                                Aktif
                            </option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>
                                Nonaktif
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="checkbox"
                                class="form-check-input"
                                name="is_default"
                                id="is_default"
                                value="1"
                                <?= $isDefault === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_default">
                                Jadikan organisasi default user
                            </label>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="<?= site_url(
                                    'core/organization-memberships?organization_id='
                                        . $organizationId
                                ) ?>" class="btn btn-light">
                        Kembali
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Simpan Membership
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->section('jsFooterInline'); ?>
<script>
    document.addEventListener('DOMContentLoaded', async function() {
        const organizationSelect = document.getElementById('organization_id');
        const userSelect = document.getElementById('user_id');
        const unitSelect = document.getElementById('unit_id');
        const statusSelect = document.getElementById('membership_status');
        const defaultCheckbox = document.getElementById('is_default');

        async function loadUsers(organizationId) {
            if (!userSelect) return;

            userSelect.innerHTML = '<option value="">Memuat user...</option>';

            if (!organizationId) {
                userSelect.innerHTML = '<option value="">Pilih organisasi dahulu</option>';
                return;
            }

            try {
                const url = new URL(
                    <?= json_encode(site_url('core/organization-memberships/user-options')) ?>,
                    window.location.origin
                );

                url.searchParams.set('organization_id', organizationId);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();

                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Gagal memuat user.');
                }

                userSelect.innerHTML = '<option value="">Pilih user</option>';

                result.data.forEach(function(user) {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.username + ' (ID: ' + user.id + ')';
                    userSelect.appendChild(option);
                });
            } catch (error) {
                userSelect.innerHTML = '<option value="">Gagal memuat user</option>';
                console.error(error);
            }
        }

        async function loadUnits(organizationId) {
            const previousValue = unitSelect.value;
            unitSelect.innerHTML = '<option value="">Tanpa unit</option>';

            if (!organizationId) return;

            try {
                const url = new URL(
                    <?= json_encode(site_url('core/organization-units/parent-options')) ?>,
                    window.location.origin
                );

                url.searchParams.set('organization_id', organizationId);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();

                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Gagal memuat unit.');
                }

                result.data.forEach(function(unit) {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.code + ' — ' + unit.name;
                    unitSelect.appendChild(option);
                });

                if ([...unitSelect.options].some(
                        option => option.value === previousValue
                    )) {
                    unitSelect.value = previousValue;
                }
            } catch (error) {
                console.error(error);
            }
        }

        if (organizationSelect && !organizationSelect.disabled) {
            organizationSelect.addEventListener('change', async function() {
                await Promise.all([
                    loadUsers(this.value),
                    loadUnits(this.value)
                ]);
            });

            if (organizationSelect.value && userSelect) {
                await loadUsers(organizationSelect.value);
            }
        }

        if (organizationSelect && organizationSelect.value) {
            await loadUnits(organizationSelect.value);
        }

        function updateDefaultAvailability() {
            if (statusSelect.value !== 'active') {
                defaultCheckbox.checked = false;
                defaultCheckbox.disabled = true;
            } else {
                defaultCheckbox.disabled = false;
            }
        }

        statusSelect.addEventListener('change', updateDefaultAvailability);
        updateDefaultAvailability();
    });
</script>

<?= $this->endSection(); ?>
