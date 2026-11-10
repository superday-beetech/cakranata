<?php

declare(strict_types=1);

/**
 * Error response view.
 *
 * Expected variables:
 * @var int|null    $status
 * @var string|null $code
 * @var string|null $title
 * @var string|null $message
 */

$status = (int) ($status ?? 500);
$code   = trim((string) ($code ?? 'INTERNAL_ERROR'));

if ($status < 100 || $status > 599) {
    $status = 500;
}

if ($code === '') {
    $code = 'INTERNAL_ERROR';
}

$errors = [
    303 => [
        'title'       => 'Pengalihan Halaman',
        'message'     => 'Permintaan ini mengarah ke lokasi lain.',
        'icon'        => 'bi-arrow-left-right',
        'accent'      => '#2563eb',
        'surface'     => '#eff6ff',
        'action'      => 'home',
    ],
    401 => [
        'title'       => 'Autentikasi Diperlukan',
        'message'     => 'Silakan autentikasi terlebih dahulu untuk melanjutkan.',
        'icon'        => 'bi-shield-lock',
        'accent'      => '#2563eb',
        'surface'     => '#eff6ff',
        'action'      => 'login',
    ],
    403 => [
        'title'       => 'Akses Tidak Diizinkan',
        'message'     => 'Akun Anda tidak memiliki akses yang diperlukan untuk halaman ini.',
        'icon'        => 'bi-shield-x',
        'accent'      => '#dc2626',
        'surface'     => '#fef2f2',
        'action'      => 'back',
    ],
    404 => [
        'title'       => 'Halaman Tidak Ditemukan',
        'message'     => 'Alamat yang diminta tidak tersedia atau sudah tidak dapat diakses.',
        'icon'        => 'bi-search',
        'accent'      => '#475569',
        'surface'     => '#f1f5f9',
        'action'      => 'back',
    ],
    405 => [
        'title'       => 'Metode Tidak Diizinkan',
        'message'     => 'Metode permintaan yang digunakan tidak didukung oleh endpoint ini.',
        'icon'        => 'bi-slash-circle',
        'accent'      => '#c2410c',
        'surface'     => '#fff7ed',
        'action'      => 'back',
    ],
    429 => [
        'title'       => 'Batas Permintaan Tercapai',
        'message'     => 'Permintaan sedang dibatasi untuk menjaga kestabilan layanan.',
        'icon'        => 'bi-hourglass-split',
        'accent'      => '#b45309',
        'surface'     => '#fffbeb',
        'action'      => 'retry',
    ],
    500 => [
        'title'       => 'Kesalahan Sistem',
        'message'     => 'Terjadi kendala internal saat memproses permintaan Anda.',
        'icon'        => 'bi-exclamation-triangle',
        'accent'      => '#dc2626',
        'surface'     => '#fef2f2',
        'action'      => 'retry',
    ],
];

$default = [
    'title'   => 'Permintaan Tidak Dapat Diproses',
    'message' => 'Sistem tidak dapat menyelesaikan permintaan Anda.',
    'icon'    => 'bi-exclamation-circle',
    'accent'  => '#475569',
    'surface' => '#f1f5f9',
    'action'  => 'back',
];

$config = $errors[$status] ?? $default;

$title = trim((string) ($title ?? ''));
$title = $title !== '' ? $title : $config['title'];

$message = trim((string) ($message ?? ''));
$message = $message !== '' ? $message : $config['message'];

$icon = $config['icon'];
$accent = $config['accent'];
$surface = $config['surface'];

$homeUrl = site_url('/');
$loginUrl = site_url('auth/login');

$showRetry = in_array($status, [429, 500], true);
$showLogin = $status === 401;

// Escape values when rendering them in HTML.
$escapedTitle = esc($title);
$escapedMessage = esc($message);
$escapedCode = esc($code);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f8fafc">

    <title><?= esc((string) $status) ?> — <?= $escapedTitle ?></title>

    <link
        rel="stylesheet"
        href="<?= base_url() . 'themes/adminmart/css/styles.css' ?>">

    <link
        rel="stylesheet"
        href="<?= base_url() . 'themes/adminmart/plugins/bootstrap-icons/bootstrap-icons.min.css?'; ?>">

    <style>
        :root {
            color-scheme: light;
            --page-bg: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --accent-color: <?= esc($accent) ?>;
            --accent-surface: <?= esc($surface) ?>;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--page-bg);
            color: var(--text-primary);
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .error-shell {
            width: 100%;
            max-width: 560px;
            margin-inline: auto;
        }

        .error-card {
            position: relative;
            overflow: hidden;
            padding: clamp(28px, 6vw, 48px);
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow:
                0 4px 6px -2px rgb(15 23 42 / 3%),
                0 16px 40px -16px rgb(15 23 42 / 10%);
            text-align: center;
        }

        .error-card::before {
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: var(--accent-color);
            content: "";
        }

        .error-icon {
            display: inline-flex;
            width: 76px;
            height: 76px;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background: var(--accent-surface);
            color: var(--accent-color);
        }

        .error-icon i {
            font-size: 2rem;
            line-height: 1;
        }

        .error-status {
            display: block;
            margin-bottom: 14px;
            color: var(--accent-color);
            font-size: clamp(3.5rem, 12vw, 5rem);
            font-weight: 750;
            letter-spacing: -0.065em;
            line-height: 1;
        }

        .error-title {
            margin-bottom: 12px;
            color: var(--text-primary);
            font-size: clamp(1.25rem, 4vw, 1.5rem);
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.35;
        }

        .error-message {
            max-width: 420px;
            margin: 0 auto 28px;
            color: var(--text-secondary);
            font-size: .95rem;
            line-height: 1.75;
            overflow-wrap: anywhere;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .error-actions .btn {
            min-height: 44px;
            padding: 10px 18px;
            border-radius: 9px;
            font-size: .9rem;
            font-weight: 600;
            transition:
                background-color .15s ease,
                border-color .15s ease;
        }

        .btn-error-primary {
            border-color: var(--accent-color);
            background: var(--accent-color);
            color: #fff;
        }

        .btn-error-primary:hover {
            border-color: var(--accent-color);
            background: var(--accent-color);
            color: #fff;
            filter: brightness(.92);
        }

        .btn-error-secondary {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
        }

        .btn-error-secondary:hover {
            border-color: #94a3b8;
            background: #f8fafc;
            color: #0f172a;
        }

        .error-footer {
            display: inline-block;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            /* gap: 12px; */
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            color: #94a3b8;
            font-size: .78rem;
            text-align: left;
        }

        .error-reference {
            display: inline-block;
            max-width: 100%;
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
            color: #475569;
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: .75rem;
            overflow-wrap: anywhere;
        }


        .visually-hidden-focusable:focus {
            position: static;
            width: auto;
            height: auto;
            padding: .5rem;
            overflow: visible;
            clip: auto;
            white-space: normal;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .error-card {
                border-radius: 16px;
            }

            .error-actions {
                flex-direction: column;
            }

            .error-actions .btn {
                width: 100%;
            }

            .error-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .error-footer-brand {
                margin-left: 0;
                text-align: left;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>
    <main class="error-shell">
        <section class="error-card" aria-labelledby="error-title">

            <div class="error-icon" aria-hidden="true">
                <i class="bi <?= esc($icon) ?>"></i>
            </div>

            <span class="error-status" aria-label="HTTP status <?= esc((string) $status) ?>">
                <?= esc((string) $status) ?>
            </span>

            <h1 id="error-title" class="error-title">
                <?= $escapedTitle ?>
            </h1>
            <div>
                <!-- <span class="error-footer-label">Kode referensi</span> -->
                <span class="error-reference mb-4"><?= $escapedCode ?></span>
            </div>
            <p class="error-message">
                <?= $escapedMessage ?>
            </p>

            <div class="error-actions">
                <?php if ($showLogin): ?>
                    <a href="<?= esc($loginUrl) ?>" class="btn btn-error-primary">
                        <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>
                        Masuk
                    </a>
                <?php endif; ?>

                <a href="<?= esc($homeUrl) ?>" class="btn btn-error-primary">
                    <i class="bi bi-house-door me-2" aria-hidden="true"></i>
                    Halaman Utama
                </a>

                <?php if ($showRetry): ?>
                    <button
                        type="button"
                        class="btn btn-error-secondary"
                        onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
                        Coba Lagi
                    </button>
                <?php else: ?>
                    <button
                        type="button"
                        class="btn btn-error-secondary btn-kembali"
                        data-home-url="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>
                        Kembali
                    </button>
                <?php endif; ?>
            </div>

            <footer class="error-footer ">

                <div class="error-footer-brand">
                    <span class="error-footer-label">ADI CAKRA NATA</span>
                    <span>&copy; <?= esc(date('Y')) ?></span>
                </div>
            </footer>

        </section>
    </main>
    <script>
        document.querySelectorAll('.btn-kembali').forEach(button => {
            button.addEventListener('click', function() {
                if (document.referrer) {
                    history.back();
                } else {
                    window.location.assign(this.dataset.homeUrl);
                }
            });
        });
    </script>
</body>

</html>
