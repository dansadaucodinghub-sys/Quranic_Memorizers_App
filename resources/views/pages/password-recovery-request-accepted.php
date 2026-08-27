<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <?= $renderer->render('fragments.password-recovery-request-accepted', $view, $translator)->trustedHtml() ?>
</article>
