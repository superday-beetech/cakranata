<?php
$userList = $users ?? [];
$groupList = $userGroups ?? [];
$selected = $selectedUser ?? null;
?>

<div class="py-2">
    <div class="row mb-3">
        <div class="col-12">
            <h4 class="mb-1">Group Users</h4>
            <p class="text-muted mb-0">
                Atur group yang dimiliki setiap user.
            </p>
        </div>
    </div>
    <?php if ($userList === []): ?>
        <div class="card">
            <div class="card-body">
                Belum ada user yang tersedia.
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <form method="get"
                    action="<?= site_url('rbac/user-groups') ?>"
                    class="mb-4">
                    <label for="user_id" class="form-label">
                        Pilih User
                    </label>

                    <div class="d-flex gap-2">
                        <select
                            name="user_id"
                            id="user_id"
                            class="form-select"
                            required>
                            <?php foreach ($userList as $user): ?>
                                <option
                                    value="<?= (int) $user['id'] ?>"
                                    <?= $selected !== null
                                        && (int) $selected['id'] === (int) $user['id']
                                        ? 'selected'
                                        : '' ?>>
                                    <?= esc($user['username'] ?: 'User #' . $user['id']) ?>
                                    (ID: <?= (int) $user['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-secondary">
                            Tampilkan
                        </button>
                    </div>
                </form>

                <?php if ($selected !== null): ?>
                    <hr>

                    <h5 class="mb-1">
                        Group untuk
                        <?= esc($selected['username'] ?: 'User #' . $selected['id']) ?>
                    </h5>

                    <p class="text-muted mb-3">
                        Centang group yang ingin diberikan kepada user ini.
                    </p>

                    <form
                        method="post"
                        action="<?= site_url('rbac/user-groups/save') ?>">
                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="user_id"
                            value="<?= (int) $selected['id'] ?>">

                        <?php if ($groupList === []): ?>
                            <div class="alert alert-warning">
                                Belum ada group. Buat group terlebih dahulu.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px">Pilih</th>
                                            <th>Nama Group</th>
                                            <th>Deskripsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groupList as $group): ?>
                                            <tr>
                                                <td>
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="groups[]"
                                                        value="<?= esc($group['name']) ?>"
                                                        id="group_<?= (int) $group['id'] ?>"
                                                        <?= $group['assigned']
                                                            ? 'checked'
                                                            : '' ?>>
                                                </td>
                                                <td>
                                                    <label
                                                        class="form-check-label"
                                                        for="group_<?= (int) $group['id'] ?>">
                                                        <?= esc($group['name']) ?>
                                                    </label>
                                                </td>
                                                <td>
                                                    <?= esc($group['description'] ?? '') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                Simpan Group User
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
