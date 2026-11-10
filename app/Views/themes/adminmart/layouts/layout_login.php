<?php /* app/Views/themes/adminlte/layouts/default.php */ ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= esc($title ?? app_setting('site_name', 'Default Title') ?? 'Default Title') ?> <?= app_setting('siteTitleSeparator', '|')?> <?= app_setting('siteTitle')?></title>
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

<?= $this->renderSection('navbar') ?>

<!-- **************** MAIN CONTENT START **************** -->

<?= $this->renderSection('sidebar') ?>
<?= $this->renderSection('banner') ?>
<?= $this->renderSection('content') ?>

<!-- **************** MAIN CONTENT END **************** -->

<?= $this->renderSection('footer') ?>
<!-- Back to top -->

<?= $theme->renderModals() ?>
<?= $theme->renderModalShells() ?>

<!-- ./wrapper -->
<?= $this->renderSection('jsFooter') ?>
<!--<div class="sidebar-overlay"></div>-->
<?= $theme->renderInlineJS('footer') ?>
<?= $this->renderSection('inlineJs') ?>
<?= $this->renderSection('pluginHooks') ?>
<?= $this->renderSection('extraBottom') ?>
</body>
</html>
