<?php

declare(strict_types=1);

?>
<article class="shell identity-page">
    <h1><?= $escape->escapeText($translator->trans('community.report.title')) ?></h1>
    <?= $renderer->render('fragments.community-report', $view, $translator)->trustedHtml() ?>
</article>
