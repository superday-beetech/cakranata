    <?php
    // app/Views/errors/response.php

    $status = (int) ($status ?? 500);

    $defaults = [
        401 => [
            'title' => 'Sesi Login Diperlukan',
            'icon'  => 'bi bi-person-lock',
            'color' => 'warning',
            'text'  => 'Silakan masuk terlebih dahulu untuk melanjutkan.',
        ],
        403 => [
            'title' => 'Akses Tidak Diizinkan',
            'icon'  => 'bi bi-shield-lock',
            'color' => 'danger',
            'text'  => 'Anda tidak memiliki izin untuk mengakses fitur ini.',
        ],
        404 => [
            'title' => 'Halaman Tidak Ditemukan',
            'icon'  => 'bi bi-search',
            'color' => 'primary',
            'text'  => 'Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.',
        ],
        405 => [
            'title' => 'Metode Tidak Diizinkan',
            'icon'  => 'bi bi-arrow-left-right',
            'color' => 'warning',
            'text'  => 'Metode permintaan tidak didukung oleh fitur ini.',
        ],
        429 => [
            'title' => 'Terlalu Banyak Permintaan',
            'icon'  => 'bi bi-hourglass-split',
            'color' => 'warning',
            'text'  => 'Anda melakukan terlalu banyak permintaan. Silakan coba kembali sebentar lagi.',
        ],
        500 => [
            'title' => 'Terjadi Kesalahan',
            'icon'  => 'bi bi-exclamation-triangle',
            'color' => 'danger',
            'text'  => 'Terjadi kesalahan pada sistem. Silakan coba kembali atau hubungi administrator.',
        ],
    ];

    $config = $defaults[$status] ?? $defaults[500];

    $title = $title ?? $config['title'];
    $message = $message ?? $config['text'];
    $code = $code ?? (string) $status;
    $icon = $icon ?? $config['icon'];
    $color = $color ?? $config['color'];
    $showDashboard = $showDashboard ?? false;
    $dashboardUrl = $dashboardUrl ?? site_url('/');
    $backUrl = $backUrl ?? null;
    $cssUrl = $cssUrl ?? null;
    ?>
    <?php /* app/Views/themes/adminlte/layouts/default.php */ ?>
    <!DOCTYPE html>
    <html lang="en" dir="ltr" data-bs-theme="" data-color-theme="Blue_Theme" data-layout="">

    <head>
        <meta charset="UTF-8">
        <title><?= $title ?? ""; ?></title>

    </head>

    <body class="<?= esc($bodyClass ?? '') ?>">

        <div class="container py-4 py-lg-5">
            <div class="row justify-content-center align-items-center"
                style="min-height: 55vh;">
                <div class="col-12 col-md-10 col-lg-8 col-xl-7">

                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-body p-4 p-md-5 text-center">

                            <div class="d-inline-flex align-items-center justify-content-center
                                rounded-circle bg-<?= esc($color) ?> bg-opacity-10
                                text-<?= esc($color) ?> mb-4"
                                style="width: 100px; height: 100px;">
                                <i class="<?= esc($icon) ?>" style="font-size: 2.75rem;"
                                    aria-hidden="true"></i>
                            </div>

                            <div class="mb-3">
                                <span class="badge rounded-pill text-bg-<?= esc($color) ?> px-3 py-2">
                                    HTTP <?= esc((string) $status) ?>
                                    <span class="mx-1">·</span>
                                    <?= esc($code) ?>
                                </span>
                            </div>

                            <h1 class="h3 fw-bold mb-3">
                                <?= esc($title) ?>
                            </h1>

                            <p class="text-body-secondary fs-6 mb-4 mx-auto"
                                style="max-width: 520px;">
                                <?= esc($message) ?>
                            </p>

                            <div class="d-flex flex-column flex-sm-row
                                justify-content-center gap-2">

                                <button type="button"
                                    class="btn btn-outline-secondary px-4"
                                    onclick="if (window.history.length > 1) {
                                    window.history.back();
                                } else {
                                    window.location.href = <?= json_encode(
                                                                site_url('/'),
                                                                JSON_HEX_TAG | JSON_HEX_APOS |
                                                                    JSON_HEX_QUOT | JSON_HEX_AMP
                                                            ) ?>;
                                }">
                                    <i class="bi bi-arrow-left me-1"
                                        aria-hidden="true"></i>
                                    Kembali
                                </button>

                                <?php if ($showDashboard): ?>
                                    <a href="<?= esc($dashboardUrl) ?>"
                                        class="btn btn-primary px-4">
                                        <i class="bi bi-house-door me-1"
                                            aria-hidden="true"></i>
                                        Ke Dashboard
                                    </a>
                                <?php endif; ?>

                                <?php if ($status === 401): ?>
                                    <a href="<?= esc(site_url('auth/login')) ?>"
                                        class="btn btn-primary px-4">
                                        <i class="bi bi-box-arrow-in-right me-1"
                                            aria-hidden="true"></i>
                                        Masuk
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="border-top mt-4 pt-3">
                                <small class="text-body-secondary">
                                    Jika masalah terus terjadi, silakan hubungi administrator.
                                </small>
                            </div>

                        </div>
                    </div>

                    <p class="text-center text-body-secondary small mt-3 mb-0">
                        Mohon maaf atas ketidaknyamanannya.
                    </p>

                </div>
            </div>
        </div>
