<?php /* app/Views/themes/adminlte/layouts/default.php */ ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>
        <?= esc($title ?? app_setting('site_name', 'Default Title') ?? 'Default Title') ?>
        <?= app_setting('siteTitleSeparator', '|')?>
        <?= app_setting('siteTitle')?></title>
    <?= csrf_meta(); ?>
    <?= $theme->metaHTML(); ?>
    <?= $this->renderSection('renderMeta') ?>

    <!-- Section CSS tambahan dari baseView atau halaman -->
    <?= $this->renderSection('cssHTML') ?? ''; ?>

    <?= $this->renderSection('jsHeader') ?? ''; ?>
    <?= $theme->renderInlineJS('header') ?>
    <?= $this->renderSection('extraTop') ?>
</head>

<body class="<?= esc($bodyClass ?? '') ?>">
    <!-- Site wrapper -->
    <div class="app-wrapper">
        <?= $this->renderSection('navbar') ?>

        <!-- **************** MAIN CONTENT START **************** -->

        <?= $this->renderSection('sidebar') ?>
        <?= $this->renderSection('banner') ?>
        <?= $this->renderSection('content') ?>

        <!-- **************** MAIN CONTENT END **************** -->

        <?= $this->renderSection('footer') ?>
        <?= $this->renderSection('renderModal')     ?? ''; ?>
        <?= $this->renderSection('offcanvasBottom') ?? ''; ?>
        <!-- Back to top -->

        <?= $theme->renderModals() ?>
        <?= $theme->renderModalShells() ?>
    </div>
    <!-- ./wrapper -->
    <?= $this->renderSection('jsFooter') ?>
    <!--<div class="sidebar-overlay"></div>-->
    <?= $theme->renderInlineJS('footer') ?>
    <?= $this->renderSection('jsFooterInline') ?>
    <?= $this->renderSection('pluginHooks') ?>
    <?= $this->renderSection('extraBottom') ?>
    <script>
        const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
        const Default = {
            scrollbarTheme: 'os-theme-light',
            scrollbarAutoHide: 'leave',
            scrollbarClickScroll: true,
        };
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);

            // Disable OverlayScrollbars on mobile devices to prevent touch interference
            const isMobile = window.innerWidth <= 992;

            if (
                sidebarWrapper &&
                OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined &&
                !isMobile
            ) {
                OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
                    scrollbars: {
                        theme: Default.scrollbarTheme,
                        autoHide: Default.scrollbarAutoHide,
                        clickScroll: Default.scrollbarClickScroll,
                    },
                });
            }
        });
    </script>
    <!--end::OverlayScrollbars Configure-->
    <!--begin::Bootstrap Tooltips-->
    <script>
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach((tooltipTriggerEl) => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    <!--end::Bootstrap Tooltips-->
    <!--begin::Bootstrap Toasts-->
    <script>
        const toastTriggerList = document.querySelectorAll('[data-bs-toggle="toast"]');
        toastTriggerList.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                const toastEle = document.getElementById(btn.getAttribute('data-bs-target'));
                const toastBootstrap = bootstrap.Toast.getOrCreateInstance(toastEle);
                toastBootstrap.show();
            });
        });
    </script>
    <!--end::Bootstrap Toasts-->
    <!--end::Script-->
</body>

</html>
