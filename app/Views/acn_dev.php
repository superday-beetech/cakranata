<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="api-base-url" content="<?= site_url('api/v1') ?>">

    <title>ACN ERP</title>
    <script>
        var userSettings = {
            Layout: "vertical", // vertical | horizontal
            SidebarType: "full", // full | mini-sidebar
            BoxedLayout: true, // true | false
            Direction: "ltr", // ltr | rtl
            Theme: "light", // light | dark
            ColorTheme: "Blue_Theme", // Blue_Theme | Aqua_Theme | Purple_Theme | Green_Theme | Cyan_Theme | Orange_Theme
            cardBorder: false, // true | false
        };
    </script>
</head>

<body>
    <div id="app"></div>

    <div id="acn-app-fallback" class="acn-app-fallback">
        <div class="acn-fallback-card">

            <div class="acn-fallback-icon">
                <i class="ti ti-cloud-off"></i>
            </div>

            <span class="acn-fallback-label">
                CONNECTION ERROR
            </span>

            <h4>Aplikasi Tidak Dapat Dimuat</h4>

            <p class="acn-fallback-description">
                Maaf, kami mengalami kendala saat menghubungkan
                aplikasi ke server. Beberapa komponen mungkin
                tidak dapat dimuat dengan baik.
            </p>

            <div class="acn-fallback-divider"></div>

            <div class="acn-fallback-help">
                <i class="ti ti-info-circle"></i>
                <span>
                    Periksa koneksi internet Anda, kemudian
                    coba muat ulang halaman.
                </span>
            </div>

            <button
                type="button"
                onclick="window.location.reload()"
                class="btn btn-primary acn-fallback-button">
                <i class="ti ti-refresh me-2"></i>
                Coba Muat Ulang
            </button>

            <div class="acn-fallback-footer">
                <span>ACN</span>
                <span class="acn-footer-dot"></span>
                <span>Integrated Commodity Group</span>
            </div>

        </div>
    </div>

    <style>
        /* =========================================
   ACN Application Loading Fallback
========================================= */

        #acn-app-fallback {
            position: fixed;
            inset: 0;
            z-index: 999999;

            display: none;
            align-items: center;
            justify-content: center;

            padding: 24px;

            background:
                radial-gradient(circle at 50% 35%,
                    rgba(93, 135, 255, .10),
                    transparent 45%),
                var(--bs-body-bg, #f6f9fc);
        }

        /* Card */

        .acn-fallback-card {
            width: 100%;
            max-width: 460px;

            padding: 42px 36px 28px;

            text-align: center;

            background: var(--bs-card-bg, #fff);

            border: 1px solid var(--bs-border-color, #ebf1f6);
            border-radius: 16px;

            box-shadow:
                0 10px 40px rgba(0, 0, 0, .06),
                0 2px 8px rgba(0, 0, 0, .02);

            animation: acn-fallback-enter .35s ease;
        }

        /* Icon */

        .acn-fallback-icon {
            width: 76px;
            height: 76px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 22px;

            border-radius: 50%;

            color: var(--bs-danger, #fa896b);
            background: rgba(250, 137, 107, .12);

            font-size: 36px;
        }

        /* Label */

        .acn-fallback-label {
            display: inline-block;

            margin-bottom: 12px;

            color: var(--bs-danger, #fa896b);

            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.4px;
        }

        /* Heading */

        .acn-fallback-card h4 {
            margin-bottom: 12px;

            color: var(--bs-heading-color, #2a3547);

            font-size: 21px;
            font-weight: 700;
            letter-spacing: -.4px;
        }

        /* Description */

        .acn-fallback-description {
            max-width: 350px;

            margin: 0 auto;

            color: var(--bs-secondary-color, #7c8fac);

            font-size: 14px;
            line-height: 1.8;
        }

        /* Divider */

        .acn-fallback-divider {
            height: 1px;
            margin: 25px 0;

            background: var(--bs-border-color, #ebf1f6);
        }

        /* Help information */

        .acn-fallback-help {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            padding: 13px 15px;
            margin-bottom: 24px;

            text-align: left;

            color: var(--bs-secondary-color, #7c8fac);
            background: rgba(93, 135, 255, .07);

            border-radius: 8px;

            font-size: 12px;
            line-height: 1.7;
        }

        .acn-fallback-help i {
            flex-shrink: 0;

            margin-top: 2px;

            color: var(--bs-primary, #5d87ff);
            font-size: 17px;
        }

        /* Button */

        .acn-fallback-button {
            min-height: 46px;

            padding: 11px 24px;

            border-radius: 7px;

            font-size: 13px;
            font-weight: 600;

            box-shadow: 0 4px 10px rgba(93, 135, 255, .20);

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .acn-fallback-button:hover {
            transform: translateY(-2px);

            box-shadow: 0 7px 16px rgba(93, 135, 255, .25);
        }

        /* Footer */

        .acn-fallback-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            margin-top: 30px;
            padding-top: 20px;

            border-top: 1px solid var(--bs-border-color, #ebf1f6);

            color: var(--bs-secondary-color, #7c8fac);

            font-size: 11px;
        }

        .acn-fallback-footer span:first-child {
            color: var(--bs-primary, #5d87ff);
            font-weight: 800;
            letter-spacing: 1px;
        }

        .acn-footer-dot {
            width: 4px;
            height: 4px;

            border-radius: 50%;
            background: var(--bs-secondary-color, #7c8fac);
        }

        /* Animation */

        @keyframes acn-fallback-enter {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile */

        @media (max-width: 575.98px) {
            #acn-app-fallback {
                padding: 16px;
            }

            .acn-fallback-card {
                padding: 34px 22px 24px;
                border-radius: 14px;
            }

            .acn-fallback-card h4 {
                font-size: 19px;
            }

            .acn-fallback-icon {
                width: 68px;
                height: 68px;
                font-size: 32px;
            }
        }

        /* Reduced motion */

        @media (prefers-reduced-motion: reduce) {

            .acn-fallback-card,
            .acn-fallback-button {
                animation: none;
                transition: none;
            }
        }
    </style>

    <script>
        window.addEventListener("error", function(event) {
            const target = event.target;

            if (
                target instanceof HTMLScriptElement &&
                target.type === "module" &&
                !window.__ACN_APP_MOUNTED__
            ) {
                const fallback = document.getElementById(
                    "acn-app-fallback"
                );

                if (fallback) {
                    fallback.style.display = "flex";
                }
            }
        }, true);
    </script>

    <script type="module"
        src="<?= esc('http://127.0.0.1:5173/acn/@vite/client') ?>">
    </script>

    <script type="module"
        src="<?= esc('http://127.0.0.1:5173/acn/src/main.ts') ?>">
    </script>
</body>

</html>
