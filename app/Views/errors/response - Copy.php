<?php
$status  = (int) ($status ?? 403);
$code    = (string) ($code ?? 'ACCESS_DENIED');
$title   = (string) ($title ?? 'Akses Ditolak');
$message = (string) ($message ?? 'Anda tidak memiliki izin untuk mengakses halaman ini.');

$description = match ($status) {
    401 => 'Identitas Anda perlu diverifikasi sebelum dapat melanjutkan.',
    403 => 'Halaman ini berada di luar hak akses akun Anda saat ini.',
    404 => 'Sepertinya halaman yang dituju tidak tersedia di alamat ini.',
    405 => 'Permintaan tidak dapat diproses dengan metode yang digunakan.',
    429 => 'Sistem sedang membatasi permintaan untuk menjaga kestabilan layanan.',
    500 => 'Terjadi kendala internal saat memproses permintaan Anda.',
    default => 'Sesuatu tidak berjalan sesuai harapan. Silakan coba kembali.',
};

$icon = match ($status) {
    // Authentication required
    401 => '
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <rect x="5" y="10" width="14" height="11" rx="2"/>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            <path d="M12 14v3"/>
        </svg>',

    // Access denied
    403 => '
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/>
            <path d="m9 9 6 6"/>
            <path d="m15 9-6 6"/>
        </svg>',

    // Not found
    404 => '
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <circle cx="10.5" cy="10.5" r="6.5"/>
            <path d="m16 16 4.5 4.5"/>
            <path d="M8.5 8.5a2.2 2.2 0 0 1 4 1.2c0 1.5-2 2-2 3"/>
            <path d="M10.5 15.5h.01"/>
        </svg>',

    // Method not allowed
    405 => '
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <circle cx="12" cy="12" r="9"/>
            <path d="M8 8l8 8"/>
            <path d="M16 8l-8 8"/>
        </svg>',

    // Rate limit
    429 => '
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 7v5l3 2"/>
        </svg>',

    // Internal server error / fallback
    default => '
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <path d="M12 9v4"/>
            <path d="M12 17h.01"/>
            <path d="M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
        </svg>',
};

$iconStyle = match ($status) {
    401 => [
        'background' => '#eff6ff',
        'color'      => '#2563eb',
    ],
    403 => [
        'background' => '#fef2f2',
        'color'      => '#dc2626',
    ],
    404 => [
        'background' => '#f1f5f9',
        'color'      => '#475569',
    ],
    405 => [
        'background' => '#fff7ed',
        'color'      => '#ea580c',
    ],
    429 => [
        'background' => '#fffbeb',
        'color'      => '#d97706',
    ],
    default => [
        'background' => '#fef2f2',
        'color'      => '#dc2626',
    ],
};
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | Aplikasi</title>

    <link
        href="<?= site_url() . 'themes/adminmart/css/styles.css' ?>"
        rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: #f4f6f9;
        }

        .error-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .error-card {
            width: 100%;
            max-width: 560px;
            border: 0;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .08);
        }

        .error-icon {
            width: 88px;
            height: 88px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            border-radius: 20px;
            background: <?= esc($iconStyle['background']) ?>;
            color: <?= esc($iconStyle['color']) ?>;
        }

        .error-icon svg {
            width: 40px;
            height: 40px;
            display: block;
        }
    </style>
</head>

<body>
    <main class="error-wrapper">
        <section class="card error-card">
            <div class="card-body text-center p-4 p-md-5">
                <div class="error-icon">
                    <?= $icon ?>
                </div>

                <div class="text-muted display-5 fw-semibold mb-2">
                    <?= esc((string) $status) ?>
                </div>

                <h1 class="h3 fw-bold mb-3">
                    <?= esc($title) ?>
                </h1>

                <p class="fw-medium mb-2">
                    <?= esc($message) ?>
                </p>

                <p class="text-secondary mb-4">
                    <?= esc($description) ?>
                </p>


                <div class="mb-4">
                    <span class="badge text-bg-secondary error-code">
                        <?= esc($code) ?>
                    </span>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="history.back()">
                        Kembali
                    </button>

                    <a href="<?= site_url('/') ?>" class="btn btn-outline-secondary">
                        Halaman Utama
                    </a>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
