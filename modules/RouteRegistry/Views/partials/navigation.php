<?php

declare(strict_types=1);

$activeView = $activeView ?? 'registered';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <a
        href="<?= esc(site_url('route-registry?view=registered'), 'attr') ?>"
        class="btn <?= $activeView === 'registered' ? 'btn-primary' : 'btn-outline-primary' ?>"
        <?= $activeView === 'registered' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-list-check me-1"></i>
        Registered Endpoints
    </a>

    <a
        href="<?= esc(site_url('route-registry?view=scan'), 'attr') ?>"
        class="btn <?= $activeView === 'scan' ? 'btn-primary' : 'btn-outline-primary' ?>"
        <?= $activeView === 'scan' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-scan me-1"></i>
        Scan Preview
    </a>

    <a
        href="<?= esc(site_url('route-registry?view=comparison'), 'attr') ?>"
        class="btn <?= $activeView === 'comparison' ? 'btn-primary' : 'btn-outline-primary' ?>"
        <?= $activeView === 'comparison' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-git-compare me-1"></i>
        Comparison
    </a>

    <a
        href="<?= esc(site_url('route-registry?view=index'), 'attr') ?>"
        class="btn <?= $activeView === 'index' ? 'btn-primary' : 'btn-outline-primary' ?>"
        <?= $activeView === 'index' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-list-numbers me-1"></i>
        Index
    </a>
</div>
