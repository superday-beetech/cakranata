<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><?= esc($template['name']) ?></h4>
            <p class="text-muted mb-0">
                Preview template email — <?= esc($template['code']) ?>
            </p>
        </div>

        <a href="<?= site_url('backend/email-templates') ?>"
            class="btn btn-light">
            <i class="ti ti-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-2">
                <strong>Subjek:</strong>
                <?= esc($template['subject']) ?>
            </div>

            <div class="mb-2">
                <strong>Pengirim:</strong>
                <?= esc($template['from_name'] ?: 'Menggunakan pengaturan email global') ?>
            </div>

            <div>
                <strong>Status:</strong>
                <?php if ((int) $template['is_active'] === 1): ?>
                    <span class="badge bg-success">Aktif</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Nonaktif</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Isi Email</h5>
        </div>

        <div class="card-body">
            <?php if (! empty($template['body_html'])): ?>
                <iframe
                    title="Preview isi email"
                    sandbox=""
                    style="width:100%; min-height:600px; border:1px solid #dee2e6; border-radius:6px;"
                    srcdoc="<?= esc($template['body_html'], 'attr') ?>">
                </iframe>
            <?php else: ?>
                <pre class="mb-0 text-wrap"><?= esc($template['body_text'] ?? '') ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>
