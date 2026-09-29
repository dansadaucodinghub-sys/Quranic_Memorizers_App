<?php

declare(strict_types=1);
?>
<section class="dialog-content" data-qmdb-fragment-root data-qmdb-dialog-title="<?= $escape->escapeAttribute($translator->trans('system.about.heading')) ?>">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('about.eyebrow')) ?></p>
    <p class="lead"><?= $escape->escapeText($translator->trans('system.about.purpose')) ?></p>
    <div class="dialog-values">
        <?php foreach (['integrity', 'dignity', 'access'] as $value): ?>
        <div><strong><?= $escape->escapeText($translator->trans('about.value.' . $value . '.title')) ?></strong><span><?= $escape->escapeText($translator->trans('about.value.' . $value . '.body')) ?></span></div>
        <?php endforeach; ?>
    </div>
    <a class="text-link" href="/system/about"><?= $escape->escapeText($translator->trans('brand.learn_more')) ?></a>
</section>
