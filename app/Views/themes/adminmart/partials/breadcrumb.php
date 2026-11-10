<?php if (!empty($breadcrumbs)): ?>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <?php foreach ($breadcrumbs as $item): ?>
                <li class="breadcrumb-item<?= empty($item['url']) ? ' active' : '' ?>">
                    <?php if (!empty($item['url'])): ?>
                        <a href="<?= esc($item['url']) ?>"><?= esc($item['label']) ?></a>
                    <?php else: ?>
                        <?= esc($item['label']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach ?>
        </ol>
    </nav>
<?php endif ?>