<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= esc($title) ?></h4>

        <span class="badge bg-secondary">
            Total: <?= count($users) ?> user
        </span>
    </div>

    <?php if (empty($users)): ?>
        <div class="alert alert-info">
            Belum ada pengguna yang terdaftar.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $userId = (int) $user->id; ?>

                            <tr>
                                <td><?= $userId ?></td>

                                <td>
                                    <strong>
                                        <?= esc($user->username ?: '-') ?>
                                    </strong>
                                </td>

                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="<?= site_url('rbac/users/edit/' . $userId) ?>" class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>
                                        <a
                                            href="<?= site_url('rbac/user-groups?user_id=' . $userId) ?>" class="btn btn-sm btn-outline-info">
                                            Groups
                                        </a>

                                        <a href="<?= site_url('rbac/user-permissions?user_id=' . $userId) ?>"
                                            class="btn btn-sm btn-outline-success">
                                            Permissions
                                        </a>
                                        <a href="<?= site_url('core/organization-memberships?user_id=' . (int) $user->id) ?>"
                                            class="btn btn-info btn-sm">
                                            Keanggotaan Organisasi
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
