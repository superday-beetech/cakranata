<?php
$selectedGroupId = (int) ($selectedGroup['id'] ?? 0);
?>

<div class="py-2">
    <h4 class="mb-3"><?= esc($title) ?></h4>

    <?php if (empty($groups)): ?>
        <div class="alert alert-info">
            Belum ada group yang tersedia.
        </div>
    <?php else: ?>

        <form method="get"
            action="<?= site_url('rbac/group-permissions') ?>"
            class="mb-3">
            <label for="group_id" class="form-label">Pilih Group</label>

            <div class="d-flex gap-2">
                <select name="group_id"
                    id="group_id"
                    class="form-select"
                    onchange="this.form.submit()">
                    <?php foreach ($groups as $group): ?>
                        <option
                            value="<?= (int) $group['id'] ?>"
                            <?= (int) $group['id'] === $selectedGroupId
                                ? 'selected' : '' ?>>
                            <?= esc($group['name']) ?>
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

        <?php if ($selectedGroupId > 0): ?>
            <div class="card">
                <div class="card-header">
                    Permission untuk:
                    <strong><?= esc($selectedGroup['name'] ?? '') ?></strong>
                </div>

                <div class="card-body">
                    <form method="post"
                        action="<?= site_url('rbac/group-permissions/save') ?>">
                        <?= csrf_field() ?>

                        <input type="hidden"
                            name="group_id"
                            value="<?= $selectedGroupId ?>">

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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($permissions)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center">
                                                Belum ada permission.
                                                Sinkronkan permission terlebih dahulu.
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
                                                        value="<?= (int) $permission['id'] ?>"
                                                        <?= ! empty($permission['assigned'])
                                                            ? 'checked' : '' ?>>
                                                </td>
                                                <td>
                                                    <?= esc($permission['name']) ?>
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
        <?php else: ?>
            <div class="alert alert-info">
                Pilih group untuk mengatur permission.
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
