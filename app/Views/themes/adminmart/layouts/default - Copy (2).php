<?php /* app/Views/themes/amdinmart/layouts/default.php */ ?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-bs-theme="<?= app_setting('admin_theme'); ?>" data-color-theme="Blue_Theme" data-layout="<?= app_setting('admin_layout'); ?>">

<head>
    <meta charset="UTF-8">
    <title><?= esc($title ?? app_setting('site_name', 'Default Title') ?? 'Default Title') ?><?= app_setting('siteTitleSeparator', '|') ?><?= esc(service('settings')->get('App.siteName')) ?></title>

    <?= csrf_meta(); ?>
    <?= $theme->metaHTML(); ?>
    <?= $this->renderSection('renderMeta') ?>

    <!-- Section CSS tambahan dari baseView atau halaman -->
    <?= $this->renderSection('cssHTML') ?? ''; ?>

    <?= $theme->renderInlineJS('head') ?>
    <?= $this->renderSection('extraTop') ?>
</head>

<body class="<?= esc($bodyClass ?? '') ?>">
    <div id="alert-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
    <!-- Site wrapper -->
    <div id="main-wrapper">

        <!-- **************** MAIN CONTENT START **************** -->

        <?= $this->renderSection('sidebar') ?>
        <div class="page-wrapper">
            <?= $this->renderSection('navbar') ?>
            <?= $this->renderSection('sidebar-horizontal') ?>
            <?= $this->renderSection('banner') ?>
            <?= $this->renderSection('content') ?>

            <!-- **************** MAIN CONTENT END **************** -->

            <?= $this->renderSection('wrapper') ?>
            <?= $this->renderSection('footer') ?>
            <?= $this->renderSection('renderModal')     ?? ''; ?>
            <?= $this->renderSection('offcanvasBottom') ?? ''; ?>
            <?= $theme->renderModalShells() ?>

        </div>
        <div class="dark-transparent sidebartoggler"></div>

        <!-- ./wrapper -->
        <?= $this->renderSection('jsFooter') ?>
        <!--<div class="sidebar-overlay"></div>-->
        <?= $theme->renderInlineJS('footer') ?>
        <?= $this->renderSection('jsFooterInline') ?>
        <?= $this->renderSection('pluginHooks') ?>
        <?= $this->renderSection('extraBottom') ?>


        <?php
        $flashMessages = session()->getFlashdata();
        ?>

        <?php if (! empty($flashMessages) && is_array($flashMessages)): ?>
            <script>
                <?php foreach ($flashMessages as $type => $message): ?>
                    <?php
                    $alertType = match ($type) {
                        'success' => 'success',
                        'error', 'danger' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    };
                    ?>
                    showAlert(
                        <?= json_encode(
                            $message,
                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                        ) ?>,
                        <?= json_encode($alertType) ?>
                    );
                <?php endforeach; ?>
            </script>
        <?php endif; ?>


</body>

</html>
