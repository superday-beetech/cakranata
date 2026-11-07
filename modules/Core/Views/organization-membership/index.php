<?php
$organizations = $organizations ?? [];
$memberships = $memberships ?? [];
$user = $user ?? null;

$userId = (int) ($userId ?? (is_array($user)
    ? ($user['id'] ?? 0)
    : ($user->id ?? 0)));

$isUserContext = $userId > 0;

$selectedOrganizationId = (int) ($selectedOrganizationId ?? 0);
$keyword = (string) ($keyword ?? '');
$status = (string) ($status ?? '');

$userName = is_array($user)
    ? (string) ($user['nama_lengkap'] ?? $user['username'] ?? '')
    : (string) ($user->nama_lengkap ?? $user->username ?? '');
// echo $selectedOrganizationId;
?>

<div class="py-1">

    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">Organization Membership</h4>
            <p class="text-muted mb-0">
                <?php if ($isUserContext): ?>
                    Keanggotaan organisasi untuk
                    <strong><?= esc($userName ?: 'User #' . $userId) ?></strong>
                <?php else: ?>
                    Kelola keanggotaan user pada organisasi.
                <?php endif; ?>
            </p>
        </div>
        <a class="btn btn-primary mt-3 mt-md-0" href="<?= site_url('core/organization-memberships/create' . ($selectedOrganizationId > 0 ? '?organization_id=' . $selectedOrganizationId : '')) ?>">
            <i class="ti ti-plus me-1"></i> Tambah Membership
        </a>
    </div>


    <div class="card">
        <div class="card-body">
            <form method="get"
                action="<?= site_url('core/organization-memberships') ?>"
                class="row g-3 align-items-end">

                <div class="col-md-4">
                    <label class="form-label">Organisasi</label>
                    <select name="organization_id" class="form-select" required>
                        <option value="">Pilih organisasi</option>
                        <?php foreach ($organizations as $organization): ?>
                            <option
                                value="<?= (int) $organization['id'] ?>"
                                <?= $selectedOrganizationId === (int) $organization['id']
                                    ? 'selected' : '' ?>>
                                <?= esc($organization['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Kata kunci</label>
                    <input type="text"
                        name="q"
                        class="form-control"
                        value="<?= esc($keyword) ?>"
                        placeholder="User ID atau role">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Status</label>
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
                        Filter
                    </button>
                    <a href="<?= site_url('core/organization-memberships') ?>"
                        class="btn btn-light">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Unit ID</th>
                            <th>Role Organisasi</th>
                            <th>Status</th>
                            <th>Organisasi Default</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (! $isUserContext && $selectedOrganizationId <= 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Pilih organisasi terlebih dahulu.
                                </td>
                            </tr>
                        <?php elseif (empty($memberships)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Belum ada keanggotaan untuk pengguna atau filter ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($memberships as $membership): ?>
                                <tr>
                                    <td>
                                        <?= (int) $membership['user_id'] ?>
                                    </td>
                                    <td>
                                        <?= $membership['unit_id'] !== null ? (int) $membership['unit_id'] : '—' ?>
                                    </td>
                                    <td>
                                        <?= esc($membership['membership_role']) ?>
                                    </td>
                                    <td>
                                        <?php if ($membership['status'] === 'active'): ?>
                                            <span class="badge bg-success-subtle text-success">
                                                Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">
                                                Nonaktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= (int) $membership['is_default'] === 1 && $membership['status'] === 'active' ? 'Ya' : 'Tidak' ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-light"
                                            href="<?= site_url('core/organization-memberships/edit/' . (int) $membership['id']) ?>">
                                            Edit
                                        </a>

                                        <?php if ($membership['status'] === 'active'): ?>
                                            <form method="post" action="<?= site_url('core/organization-memberships/deactivate/' . (int) $membership['id']) ?>" class="d-inline" onsubmit="return confirm('Nonaktifkan membership ini?')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Nonaktifkan
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
