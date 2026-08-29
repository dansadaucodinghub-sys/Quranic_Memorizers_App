<?php

declare(strict_types=1);
?>
<section class="auth-page privileged-access-page">
    <?= $renderer->render('fragments.privileged-access-panel', $view, $translator)->trustedHtml() ?>
</section>
