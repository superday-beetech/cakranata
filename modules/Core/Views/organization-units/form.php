<?php
$unit = $unit ?? [];
$organizations = $organizations ?? [];
$formAction = $formAction ?? '';

$e = static fn($value): string => esc((string) ($value ?? ''));

$value = static function (string $field, $default = '') use ($unit) {
    $old = old($field);

    return $old !== null ? $old : ($unit[$field] ?? $default);
};

$isEdit = ! empty($unit['id']);
$organizationId = (int) $value('organization_id', 0);
$parentId = (string) $value('parent_id', '');
?>

<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">
            <?= $isEdit ? 'Edit Unit Organisasi' : 'Tambah Unit Organisasi' ?>
        </h4>
        <p class="text-muted mb-0">
            Atur identitas unit dan hubungan hierarkinya.
        </p>
    </div>

    <?php if ($message = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= $e($message) ?></div>
    <?php endif ?>

    <div class="card">
        <div class="card-body">
            <form method="post" action="<?= $e($formAction) ?>">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="organization_id" class="form-label">
                            Organisasi *
                        </label>

                        <select id="organization_id"
                            name="organization_id"
                            class="form-select"
                            required
                            <?= $isEdit ? 'disabled' : '' ?>>
                            <option value="">Pilih organisasi</option>

                            <?php foreach ($organizations as $organization): ?>
                                <option
                                    value="<?= (int) $organization['id'] ?>"
                                    <?= $organizationId ===
                                        (int) $organization['id'] ? 'selected' : '' ?>>
                                    <?= $e($organization['code']) ?> —
                                    <?= $e($organization['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>

                        <?php if ($isEdit): ?>
                            <input type="hidden"
                                name="organization_id"
                                value="<?= $organizationId ?>">
                            <div class="form-text">
                                Organisasi tidak dapat diubah saat mengedit unit.
                            </div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="parent_id" class="form-label">
                            Parent Unit
                        </label>

                        <select id="parent_id" name="parent_id"
                            class="form-select">
                            <option value="">Tidak ada (unit utama)</option>
                        </select>

                        <div class="form-text">
                            Pilih unit induk dalam organisasi yang sama.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="code" class="form-label">
                            Kode Unit *
                        </label>
                        <input id="code" name="code" type="text"
                            maxlength="50" class="form-control"
                            value="<?= $e($value('code')) ?>" required>
                    </div>

                    <div class="col-md-8">
                        <label for="name" class="form-label">
                            Nama Unit *
                        </label>
                        <input id="name" name="name" type="text"
                            maxlength="150" class="form-control"
                            value="<?= $e($value('name')) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="unit_type" class="form-label">
                            Jenis Unit *
                        </label>

                        <?php
                        $types = [
                            'division'     => 'Divisi',
                            'department'   => 'Departemen',
                            'branch'       => 'Cabang',
                            'business_unit' => 'Unit Usaha',
                            'team'         => 'Tim',
                            'other'        => 'Lainnya',
                        ];
                        $selectedType = (string) $value(
                            'unit_type',
                            'department'
                        );
                        ?>

                        <select id="unit_type" name="unit_type"
                            class="form-select" required>
                            <?php foreach ($types as $type => $label): ?>
                                <option value="<?= $e($type) ?>"
                                    <?= $selectedType === $type
                                        ? 'selected' : '' ?>>
                                    <?= $e($label) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label">
                            Status *
                        </label>

                        <select id="status" name="status"
                            class="form-select" required>
                            <option value="active"
                                <?= $value('status', 'active') === 'active'
                                    ? 'selected' : '' ?>>
                                Aktif
                            </option>
                            <option value="inactive"
                                <?= $value('status', 'active') === 'inactive'
                                    ? 'selected' : '' ?>>
                                Nonaktif
                            </option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="metadata" class="form-label">
                            Metadata (JSON)
                        </label>
                        <textarea id="metadata" name="metadata" rows="4"
                            class="form-control"
                            placeholder='{"key":"value"}'><?= $e($value('metadata')) ?></textarea>
                        <div class="form-text">
                            Opsional. Kosongkan jika belum diperlukan.
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="<?= site_url(
                                    'core/organization-units?organization_id=' .
                                        $organizationId
                                ) ?>" class="btn btn-outline-secondary">
                        Kembali
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Unit' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->section('jsFooterInline'); ?>
<script>
    (() => {
        const organizationSelect = document.getElementById('organization_id');
        const parentSelect = document.getElementById('parent_id');
        const selectedParentId = <?= json_encode($parentId) ?>;
        const currentUnitId = <?= json_encode((string) ($unit['id'] ?? '')) ?>;

        async function loadParents() {
            const organizationId = organizationSelect.value;

            parentSelect.innerHTML = '';

            const rootOption = document.createElement('option');
            rootOption.value = '';
            rootOption.textContent = 'Tidak ada (unit utama)';
            parentSelect.appendChild(rootOption);

            if (!organizationId) {
                return;
            }

            const url = new URL(
                <?= json_encode(site_url('core/organization-units/parent-options')) ?>,
                window.location.origin
            );
            url.searchParams.set('organization_id', organizationId);
            if (currentUnitId) {
                url.searchParams.set('exclude_id', currentUnitId);
            }

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat parent unit.');
                }

                const result = await response.json();

                for (const item of (result.data || [])) {
                    const option = document.createElement('option');
                    option.value = String(item.id);
                    option.textContent = `${item.code} — ${item.name}`;

                    if (String(item.id) === selectedParentId) {
                        option.selected = true;
                    }

                    parentSelect.appendChild(option);
                }
            } catch (error) {
                console.error(error);
            }
        }

        if (organizationSelect && !organizationSelect.disabled) {
            organizationSelect.addEventListener('change', loadParents);
        }

        loadParents();
    })();
</script>
<?= $this->endSection(); ?>
