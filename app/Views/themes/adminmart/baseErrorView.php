<?= $this->extend($_layout_override ?? "themes/{$theme}/layouts/{$layout}") ?>
<?= $this->section('extraTop') ?><?= $extraTop ?? '' ?><?= $this->endSection() ?>

<?= $this->section('renderMeta') ?><?= is_array($meta ?? []) ? implode('', $meta ?? []) : '' ?>


<?= $this->endSection() ?>

<!-- </?= is_array($cssRender ?? null) ? implode('', $cssRender) : ($cssRender ?? '') ?> -->
<?= $this->section('cssHTML') ?>
<?= $theme->renderCss() ?>
<?= $this->endSection() ?>

<?= $this->section('navbar') ?>
<?= $theme->renderPartial('navbar') ?? ''; ?>
<?= $this->endSection() ?>

<?= $this->section('sidebar') ?>
<?= $theme->renderPartial('sidebar') ?? ''; ?>
<?= $this->endSection() ?>
<?= $this->section('sidebar-horizontal') ?>
<?= $theme->renderPartial('sidebar-horizontal') ?? ''; ?>
<?= $this->endSection() ?>

<?= $this->section('sticker') ?>
<?= $theme->renderPartial('sticker') ?? ''; ?>
<?= $this->endSection() ?>

<?= $this->section('banner') ?>
<?= $theme->renderPartial('banner') ?? ''; ?>
<?= $this->endSection() ?>


<?= $this->section('content') ?>
<div class="body-wrapper">
    <div class="container-fluid">
        <div class="card bg-info-subtle shadow-none position-relative overflow-hidden mb-4">
            <div class="card-body px-4 py-3">
                <div class="row justify-content-between align-items-center">
                    <div class="col-8">
                        <h4 class="fw-semibold mb-0">
                            <?= $label ?? ''; ?>
                        </h4>
                    </div>
                    <div class="col-4 ms-auto d-flex justify-content-end">
                        <?= $theme->breadcrumbHTML() ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <?php if (session()->getFlashdata('_ci_validation_errors')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= \Config\Services::validation()->listErrors() ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= session('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= session('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?= !empty($_content_view) ? view($_content_view, get_defined_vars()) : '' ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('wrapper') ?>
<?= $theme->renderPartial('wrapper') ?? ''; ?>
<?= $this->endSection() ?>
<?= $this->section('footer') ?>
<?= $theme->renderPartial('footer') ?? ''; ?>
<?= $this->endSection() ?>

<?= $this->section('jsFooter') ?>
<?= $theme->renderJs('footer') ?>
<?= $this->endSection() ?>
-->
<?= $this->section('extraBottom') ?><?= $extraBottom ?? '' ?><?= $this->endSection() ?>
<?= $this->section('pluginHooks') ?>
<?= $pluginHooks ?? '' ?>
<?= $this->endSection() ?>
