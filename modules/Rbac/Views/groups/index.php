<div class="py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= esc($title) ?></h4>

        <a href="<?= site_url('rbac/groups/create') ?>"
            class="btn btn-primary">
            <i class="ti ti-plus"></i> Tambah Group
        </a>
    </div>

    <?php if (empty($groups)): ?>
        <div class="alert alert-info">
            Belum ada group yang tersedia.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 70px">ID</th>
                            <th>Nama Group</th>
                            <th>Deskripsi</th>
                            <th style="width: 340px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                            <?php
                            $groupId = (int) $group['id'];
                            $groupName = (string) $group['name'];
                            $protected = in_array(
                                strtolower($groupName),
                                ['superadmin', 'super_admin', 'root'],
                                true
                            );
                            ?>
                            <tr>
                                <td><?= $groupId ?></td>

                                <td>
                                    <strong><?= esc($groupName) ?></strong>

                                    <?php if ($protected): ?>
                                        <span class="badge bg-warning text-dark">
                                            Sistem
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= esc($group['description'] ?? '') ?>
                                </td>

                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="<?= site_url('rbac/groups/edit/' . $groupId) ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>

                                        <a href="<?= site_url('rbac/group-users?group_id=' . $groupId) ?>"
                                            class="btn btn-sm btn-outline-info">
                                            Users
                                        </a>

                                        <a href="<?= site_url('rbac/group-permissions?group_id=' . $groupId) ?>"
                                            class="btn btn-sm btn-outline-success">
                                            Permissions
                                        </a>

                                        <?php if (! $protected): ?>
                                            <form method="post" action="<?= site_url('rbac/groups/delete/' . $groupId) ?>"
                                                onsubmit="return confirm('Yakin ingin menghapus group ini?')">
                                                <?= csrf_field() ?>

                                                <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                    Hapus
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
