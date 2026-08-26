<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <h1><?= $escape->escapeText($translator->trans('device_revoke.heading')) ?></h1>
    <?= $renderer->render('fragments.device-revoke-dialog', $view, $translator)->trustedHtml() ?>
</article>
