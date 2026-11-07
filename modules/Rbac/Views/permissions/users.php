<?php
$selectedUserId = (int) ($selectedUser['id'] ?? 0);
?>

<div class="container-fluid">
    <h4 class="mb-3"><?= esc($title) ?></h4>

    <?php if (empty($users)): ?>
        <div class="alert alert-info">
            Belum ada user yang tersedia.
        </div>
    <?php else: ?>

        <form method="get"
            action="<?= site_url('rbac/user-permissions') ?>"
            class="mb-3">
            <label for="user_id" class="form-label">Pilih User</label>

            <div class="d-flex gap-2">
                <select name="user_id"
                    id="user_id"
                    class="form-select"
                    onchange="this.form.submit()">
                    <?php foreach ($users as $user): ?>
                        <option
                            value="<?= (int) $user['id'] ?>"
                            <?= (int) $user['id'] === $selectedUserId
                                ? 'selected' : '' ?>>
                            <?= esc($user['username'] ?: 'User #' . $user['id']) ?>
                            (ID: <?= (int) $user['id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <noscript>
                    <button type="submit" class="btn btn-secondary">
                        Pilih
                    </button>
                </noscript>
            </div>
        </form>

        <?php if ($selectedUserId > 0): ?>
            <div class="card">
                <div class="card-header">
                    Permission langsung untuk:
                    <strong>
                        <?= esc($selectedUser['username'] ?: 'User #' . $selectedUserId) ?>
                    </strong>
                </div>

                <div class="card-body">
                    <div class="alert alert-info">
                        Pengaturan ini hanya mengelola permission langsung
                        milik user, bukan permission yang diwarisi dari group.
                    </div>

                    <form method="post"
                        action="<?= site_url('rbac/user-permissions/save') ?>">
                        <?= csrf_field() ?>

                        <input type="hidden"
                            name="user_id"
                            value="<?= $selectedUserId ?>">

                        <div class="d-flex gap-2 mb-3">
                            <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="selectAllPermissions">
                                Pilih Semua
                            </button>

                            <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="clearPermissions">
                                Hapus Pilihan
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 60px">Pilih</th>
                                        <th>Permission</th>
                                        <th>Deskripsi</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (empty($permissions)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">
                                                Belum ada permission.
                                                Tambahkan melalui Permission Management
                                                atau sinkronisasi Route Registry.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($permissions as $permission): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input permission-check"
                                                        name="permissions[]"
                                                        value="<?= esc($permission['name']) ?>"
                                                        <?= ! empty($permission['assigned'])
                                                            ? 'checked' : '' ?>>
                                                </td>

                                                <td>
                                                    <?= esc($permission['name']) ?>
                                                </td>

                                                <td>
                                                    <?= esc($permission['description'] ?? '') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Simpan Permission
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.permission-check');
        const selectAll = document.getElementById('selectAllPermissions');
        const clearAll = document.getElementById('clearPermissions');

        if (selectAll) {
            selectAll.addEventListener('click', function() {
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = true;
                });
            });
        }

        if (clearAll) {
            clearAll.addEventListener('click', function() {
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = false;
                });
            });
        }
    });
</script>
