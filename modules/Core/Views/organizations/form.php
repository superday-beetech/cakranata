<?php
$organization = $organization ?? [];
$formAction   = $formAction ?? site_url('core/organizations/store');

$e = static fn($value): string => esc((string) ($value ?? ''));

$value = static function (string $field, $default = '') use ($organization) {
    $old = old($field);

    if ($old !== null) {
        return $old;
    }

    return $organization[$field] ?? $default;
};

$isEdit = ! empty($organization['id']);
?>

<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">
            <?= $isEdit ? 'Edit Organisasi' : 'Tambah Organisasi' ?>
        </h4>

        <p class="text-muted mb-0">
            Informasi identitas dan konfigurasi dasar organisasi.
        </p>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger">
            <?= $e(session()->getFlashdata('error')) ?>
        </div>
    <?php endif ?>

    <div class="card">
        <div class="card-body">
            <form method="post" action="<?= $e($formAction) ?>">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="code" class="form-label">
                            Kode Organisasi *
                        </label>

                        <input
                            id="code"
                            name="code"
                            type="text"
                            maxlength="50"
                            class="form-control"
                            value="<?= $e($value('code')) ?>"
                            required>
                    </div>

                    <div class="col-md-8">
                        <label for="name" class="form-label">
                            Nama Organisasi *
                        </label>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            maxlength="150"
                            class="form-control"
                            value="<?= $e($value('name')) ?>"
                            required>
                    </div>

                    <div class="col-md-6">
                        <label for="slug" class="form-label">
                            Slug *
                        </label>

                        <input
                            id="slug"
                            name="slug"
                            type="text"
                            maxlength="100"
                            class="form-control"
                            value="<?= $e($value('slug')) ?>"
                            required>

                        <div class="form-text">
                            Identitas unik organisasi untuk kebutuhan sistem.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="legal_name" class="form-label">
                            Nama Legal
                        </label>

                        <input
                            id="legal_name"
                            name="legal_name"
                            type="text"
                            maxlength="200"
                            class="form-control"
                            value="<?= $e($value('legal_name')) ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="organization_type" class="form-label">
                            Jenis Organisasi *
                        </label>

                        <select
                            id="organization_type"
                            name="organization_type"
                            class="form-select"
                            required>
                            <?php
                            $types = [
                                'business' => 'Bisnis',
                                'company'  => 'Perusahaan',
                                'cooperative' => 'Koperasi',
                                'government' => 'Pemerintahan',
                                'nonprofit' => 'Nirlaba',
                                'other' => 'Lainnya',
                            ];
                            $selectedType = (string) $value(
                                'organization_type',
                                'business'
                            );
                            ?>

                            <?php foreach ($types as $type => $label): ?>
                                <option
                                    value="<?= $e($type) ?>"
                                    <?= $selectedType === $type ? 'selected' : '' ?>>
                                    <?= $e($label) ?>
                                </option>
                            <?php endforeach ?>
                        </select>

                        <div class="form-text">
                            Jenis dapat diperluas sesuai kebutuhan organisasi.
                        </div>
                    </div>

                    <div class="col-md-4">
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

                    <div class="col-md-4">
                        <label for="timezone" class="form-label">
                            Timezone *
                        </label>

                        <input
                            id="timezone"
                            name="timezone"
                            type="text"
                            maxlength="64"
                            class="form-control"
                            value="<?= $e($value('timezone', 'Asia/Jakarta')) ?>"
                            required>
                    </div>

                    <div class="col-md-4">
                        <label for="currency" class="form-label">
                            Mata Uang *
                        </label>

                        <input
                            id="currency"
                            name="currency"
                            type="text"
                            maxlength="3"
                            minlength="3"
                            pattern="[A-Za-z]{3}"
                            class="form-control text-uppercase"
                            value="<?= $e($value('currency', 'IDR')) ?>"
                            required>
                    </div>

                    <div class="col-12">
                        <label for="settings" class="form-label">
                            Settings (JSON)
                        </label>

                        <textarea
                            id="settings"
                            name="settings"
                            rows="4"
                            class="form-control"
                            placeholder='{"key":"value"}'><?= $e($value('settings')) ?></textarea>

                        <div class="form-text">
                            Opsional. Kosongkan jika belum membutuhkan konfigurasi khusus.
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="<?= site_url('core/organizations') ?>"
                        class="btn btn-outline-secondary">
                        Kembali
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Organisasi' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
