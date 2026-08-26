<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <?= $renderer->render('fragments.email-verification-completed', $view, $translator)->trustedHtml() ?>
</article>
