<?php

declare(strict_types=1);
?>
<section class="dialog-content" data-qmdb-fragment-root data-qmdb-dialog-title="<?= $escape->escapeAttribute($translator->trans('system.about.heading')) ?>">
    <p class="lead"><?= $escape->escapeText($translator->trans('system.about.purpose')) ?></p>
    <dl class="facts"><div><dt><?= $escape->escapeText($translator->trans('system.about.phase')) ?></dt><dd><?= $escape->escapeText($view->string('phase')) ?></dd></div><div><dt><?= $escape->escapeText($translator->trans('system.about.batch')) ?></dt><dd><?= $escape->escapeText($view->string('batch')) ?></dd></div><div><dt><?= $escape->escapeText($translator->trans('system.about.baseline')) ?></dt><dd><?= $escape->escapeText($view->string('baseline')) ?></dd></div></dl>
</section>
