<?php

/**
 * app/Views/themes/adminmart/layouts/default.php
 */

$siteName = trim((string) (
    service('settings')->get('App.siteName')
    ?: app_setting('site_name', 'Default Title')
));

$pageTitle = trim((string) ($title ?? ''));
$titleSeparator = (string) app_setting('siteTitleSeparator', '|');

$documentTitle = $pageTitle !== ''
    ? ($siteName !== '' && $pageTitle !== $siteName
        ? $pageTitle . ' ' . $titleSeparator . ' ' . $siteName
        : $pageTitle)
    : $siteName;

$adminTheme  = (string) app_setting('admin_theme', 'light');
$adminLayout = (string) app_setting('admin_layout', 'vertical');

$flashMessages = session()->getFlashdata() ?? [];
?>
<!DOCTYPE html>
<html
    lang="en"
    dir="ltr"
    data-bs-theme="<?= esc($adminTheme) ?>"
    data-color-theme="Blue_Theme"
    data-layout="<?= esc($adminLayout) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= esc($documentTitle) ?></title>

    <?= csrf_meta() ?>
    <?= $theme->metaHTML() ?>
    <?= $this->renderSection('renderMeta') ?>

    <?= $this->renderSection('cssHTML') ?>

    <?= $theme->renderInlineJS('head') ?>
    <?= $this->renderSection('extraTop') ?>
</head>

<body class="<?= esc($bodyClass ?? '') ?>">

    <div
        id="alert-container"
        style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

    <div id="main-wrapper">

        <!-- Sidebar -->
        <?= $this->renderSection('sidebar') ?>

        <div class="page-wrapper">

            <!-- Navbar -->
            <?= $this->renderSection('navbar') ?>

            <!-- Horizontal Sidebar -->
            <?= $this->renderSection('sidebar-horizontal') ?>

            <!-- Sticker -->
            <?= $this->renderSection('sticker') ?>

            <!-- Banner -->
            <?= $this->renderSection('banner') ?>

            <!-- Main Content -->
            <?= $this->renderSection('content') ?>

            <!-- Wrapper -->
            <?= $this->renderSection('wrapper') ?>

            <!-- Footer -->
            <?= $this->renderSection('footer') ?>

            <!-- Modal -->
            <?= $this->renderSection('renderModal') ?>
            <?= $this->renderSection('offcanvasBottom') ?>
            <?= $theme->renderModalShells() ?>

        </div>

        <!-- Sidebar Overlay -->
        <div class="dark-transparent sidebartoggler"></div>

    </div>

    <!-- Footer JavaScript -->
    <?= $this->renderSection('jsFooter') ?>

    <?= $theme->renderInlineJS('footer') ?>

    <?= $this->renderSection('jsFooterInline') ?>
    <?= $this->renderSection('pluginHooks') ?>
    <?= $this->renderSection('extraBottom') ?>

    <?php
    /*
     * Pesan error, success, dan validation ditangani oleh baseView.
     * Pesan flash lainnya ditampilkan sebagai alert global.
     */
    $globalFlashMessages = array_diff_key(
        $flashMessages,
        array_flip([
            'error',
            'success',
            '_ci_validation_errors',
        ])
    );
    ?>

    <?php if ($globalFlashMessages !== []): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.showAlert !== 'function') {
                    return;
                }

                <?php foreach ($globalFlashMessages as $type => $message): ?>
                    <?php
                    $alertType = match ($type) {
                        'success'          => 'success',
                        'error', 'danger'  => 'danger',
                        'warning'          => 'warning',
                        default            => 'info',
                    };
                    ?>

                    window.showAlert(
                        <?= json_encode(
                            is_scalar($message) ? (string) $message : '',
                            JSON_HEX_TAG
                                | JSON_HEX_APOS
                                | JSON_HEX_QUOT
                                | JSON_HEX_AMP
                        ) ?>,
                        <?= json_encode($alertType) ?>
                    );
                <?php endforeach; ?>
            });
        </script>
    <?php endif; ?>

</body>

</html>
