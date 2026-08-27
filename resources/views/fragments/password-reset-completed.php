<?php

declare(strict_types=1);
?>
<section data-qmdb-fragment-root tabindex="-1" role="status">
    <h1><?= $escape->escapeText($translator->trans('password_reset.completed.heading')) ?></h1>
    <p><?= $escape->escapeText($translator->trans('password_reset.completed.body')) ?></p>
    <p><?= $escape->escapeText($translator->trans('password_reset.completed.sign_in')) ?></p>
    <p><a class="button primary" href="/login"><?= $escape->escapeText($translator->trans('password_recovery.login')) ?></a></p>
    <p><a href="/"><?= $escape->escapeText($translator->trans('action.home')) ?></a></p>
</section>
