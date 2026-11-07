<?php
$template = $template ?? [];
$errors = session()->getFlashdata('errors') ?? [];

$value = static function (string $key, mixed $default = '') use ($template): string {
    return (string) old($key, $template[$key] ?? $default);
};

$variables = $value('variables');

if ($variables !== '') {
    $decoded = json_decode($variables, true);

    if (is_array($decoded)) {
        $variables = json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: $variables;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><?= esc($title) ?></h4>
            <p class="text-muted mb-0">
                Atur konten dan identitas pengiriman template.
            </p>
        </div>

        <a href="<?= site_url('backend/email-templates') ?>"
            class="btn btn-light">
            <i class="ti ti-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if ($message = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc($message) ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <strong>Periksa kembali data berikut:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post"
        action="<?= $isEdit
                    ? site_url('backend/email-templates/' . $template['id'] . '/update')
                    : site_url('backend/email-templates/create') ?>">

        <?= csrf_field() ?>

        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Informasi Template</h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="code">
                                    Kode Template <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                    class="form-control"
                                    id="code"
                                    name="code"
                                    maxlength="100"
                                    required
                                    value="<?= esc(old('code', $template['code'] ?? '')) ?>"
                                    <?= $isEdit ? 'readonly' : '' ?>>

                                <div class="form-text">
                                    Contoh: test_email, account_activation.
                                    Kode menjadi identitas unik template.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="name">
                                    Nama Template <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                    class="form-control"
                                    id="name"
                                    name="name"
                                    maxlength="150"
                                    required
                                    value="<?= esc($value('name')) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="category">Kategori</label>
                                <input type="text"
                                    class="form-control"
                                    id="category"
                                    name="category"
                                    maxlength="50"
                                    required
                                    value="<?= esc($value('category', 'system')) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="channel">Kanal</label>
                                <select class="form-select" id="channel" name="channel">
                                    <option value="email" selected>Email</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="description">Deskripsi</label>
                                <textarea class="form-control"
                                    id="description"
                                    name="description"
                                    rows="2"
                                    maxlength="500"><?= esc($value('description')) ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="subject">Subjek Email</label>
                                <input type="text"
                                    class="form-control"
                                    id="subject"
                                    name="subject"
                                    maxlength="255"
                                    value="<?= esc($value('subject')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Isi Email</h5>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="body_html">Body HTML</label>
                            <textarea class="form-control font-monospace"
                                id="body_html"
                                name="body_html"
                                rows="14"><?= esc($value('body_html')) ?></textarea>
                            <div class="form-text">
                                Mendukung HTML. Variabel ditulis dengan format
                                <code>{{recipient_name}}</code>.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="body_text">Body Text</label>
                            <textarea class="form-control"
                                id="body_text"
                                name="body_text"
                                rows="8"><?= esc($value('body_text')) ?></textarea>
                        </div>

                        <div>
                            <label class="form-label" for="variables">
                                Variabel (JSON)
                            </label>
                            <textarea class="form-control font-monospace"
                                id="variables"
                                name="variables"
                                rows="4"
                                placeholder='["recipient_name","sent_at"]'><?= esc($variables) ?></textarea>
                            <div class="form-text">
                                Daftar nama variabel yang boleh digunakan oleh template.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Pengirim Email</h5>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-info py-2">
                            Kosongkan kolom ini untuk memakai pengaturan email global.
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="from_name">Nama Pengirim</label>
                            <input type="text"
                                class="form-control"
                                id="from_name"
                                name="from_name"
                                maxlength="150"
                                value="<?= esc($value('from_name')) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="from_email">Email Pengirim</label>
                            <input type="email"
                                class="form-control"
                                id="from_email"
                                name="from_email"
                                maxlength="254"
                                value="<?= esc($value('from_email')) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="reply_to">Reply-To</label>
                            <input type="email"
                                class="form-control"
                                id="reply_to"
                                name="reply_to"
                                maxlength="254"
                                value="<?= esc($value('reply_to')) ?>">
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Status dan Versi</h5>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="version">Versi</label>
                            <input type="number"
                                class="form-control"
                                id="version"
                                name="version"
                                min="1"
                                required
                                value="<?= esc($value('version', '1')) ?>">
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="is_active"
                                name="is_active"
                                value="1"
                                <?= (int) $value('is_active', '1') === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                Template aktif
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="is_system" value="0">
                            <input class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="is_system"
                                name="is_system"
                                value="1"
                                <?= (int) $value('is_system', '0') === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_system">
                                Template sistem
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-device-floppy me-1"></i>
                            Simpan Template
                        </button>

                        <a href="<?= site_url('backend/email-templates') ?>"
                            class="btn btn-light w-100 mt-2">
                            Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
