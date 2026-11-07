<?php

/**
 * Data yang tersedia:
 * $title  : judul halaman
 * $result : hasil PermissionHealthCheckService::inspect()
 */

$summary = $result['summary'] ?? [
    'total'    => 0,
    'errors'   => 0,
    'warnings' => 0,
];

$issues = $result['issues'] ?? [];
$healthy = (bool) ($result['healthy'] ?? false);
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-1"><?= esc($title ?? 'Permission Health Check') ?></h4>
            <p class="text-muted mb-0">
                Pemeriksaan integritas route registry dan konfigurasi permission.
            </p>
        </div>

        <a href="<?= esc(current_url()) ?>" class="btn btn-outline-primary">
            <i class="bi bi-arrow-clockwise me-1"></i>
            Periksa Ulang
        </a>
    </div>

    <div class="alert <?= $healthy ? 'alert-success' : 'alert-danger' ?>"
        role="status">
        <strong>
            <?= $healthy ? 'Tidak ditemukan error.' : 'Ditemukan error konfigurasi.' ?>
        </strong>

        <div class="small mt-1">
            <?= $healthy
                ? 'Tetap tinjau peringatan sebelum menyatakan konfigurasi sepenuhnya benar.'
                : 'Tinjau daftar error di bawah dan periksa konfigurasi terkait.' ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total temuan</div>
                    <div class="fs-3 fw-semibold">
                        <?= number_format((int) $summary['total']) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card h-100 border-danger">
                <div class="card-body">
                    <div class="text-danger small">Error</div>
                    <div class="fs-3 fw-semibold text-danger">
                        <?= number_format((int) $summary['errors']) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card h-100 border-warning">
                <div class="card-body">
                    <div class="text-warning small">Peringatan</div>
                    <div class="fs-3 fw-semibold">
                        <?= number_format((int) $summary['warnings']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body d-flex justify-content-between align-items-center">
            <strong>Detail temuan</strong>
            <span class="badge text-bg-secondary">
                <?= count($issues) ?> temuan
            </span>
        </div>

        <div class="card-body p-0">
            <?php if ($issues === []): ?>
                <div class="text-center py-5">
                    <div class="fs-1 text-success">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h5>Tidak ada temuan</h5>
                    <p class="text-muted mb-0">
                        Pemeriksaan tidak menemukan masalah berdasarkan aturan
                        yang saat ini diterapkan.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Tingkat</th>
                                <th style="width: 220px;">Kode</th>
                                <th style="width: 100px;">Route ID</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issues as $issue): ?>
                                <?php
                                $severity = $issue['severity'] ?? 'warning';

                                $badgeClass = match ($severity) {
                                    'error'   => 'text-bg-danger',
                                    'warning' => 'text-bg-warning',
                                    default   => 'text-bg-secondary',
                                };
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= esc($badgeClass) ?>">
                                            <?= esc(strtoupper($severity)) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <code><?= esc($issue['code'] ?? 'unknown') ?></code>
                                    </td>

                                    <td>
                                        <?= isset($issue['route_id'])
                                            && $issue['route_id'] !== null
                                            ? esc((string) $issue['route_id'])
                                            : '—' ?>
                                    </td>

                                    <td>
                                        <div>
                                            <?= esc($issue['message'] ?? 'Tidak ada detail.') ?>
                                        </div>

                                        <?php if (! empty($issue['endpoint'])): ?>
                                            <div class="small text-muted mt-1">
                                                Endpoint:
                                                <code><?= esc($issue['endpoint']) ?></code>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (! empty($issue['permission'])): ?>
                                            <div class="small text-muted mt-1">
                                                Permission:
                                                <code><?= esc($issue['permission']) ?></code>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info mt-3 mb-0">
        <strong>Catatan:</strong>
        hasil pemeriksaan ini merupakan diagnostik integritas data, bukan
        jaminan bahwa seluruh skenario otorisasi aplikasi sudah benar.
        Perbaiki temuan setelah memastikan konfigurasi route yang dimaksud.
    </div>
</div>
