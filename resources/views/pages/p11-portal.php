<?php

declare(strict_types=1);

?>
<?php
$isPublic = $view->string('scope') === 'PUBLIC';
$section = $view->string('section');
$contentKey = $section === 'search' ? 'public.search' : 'public.statistics';
?>
<article class="<?= $isPublic ? 'public-inner-page public-data-page' : 'shell prose-page' ?>" data-qmdb-p11-portal>
    <?php if ($isPublic): ?>
    <header class="public-page-hero shell"><p class="eyebrow"><?= $escape->escapeText($translator->trans($contentKey . '.eyebrow')) ?></p><h1><?= $escape->escapeText($translator->trans($contentKey . '.title')) ?></h1><p class="lead"><?= $escape->escapeText($translator->trans($contentKey . '.body')) ?></p></header>
    <div class="shell public-data-workspace"><?= $renderer->render('fragments.p11-portal', $view, $translator)->trustedHtml() ?></div>
    <?php else: ?>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('analytics.eyebrow')) ?></p><h1><?= $escape->escapeText($translator->trans('analytics.title')) ?></h1><p><?= $escape->escapeText($translator->trans('analytics.description')) ?></p><?= $renderer->render('fragments.p11-portal', $view, $translator)->trustedHtml() ?>
    <?php endif; ?>
</article>
