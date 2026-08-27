<?php

declare(strict_types=1);
?>
<section data-qmdb-fragment-root tabindex="-1" role="status">
    <h1><?= $escape->escapeText($translator->trans('password_recovery.accepted.heading')) ?></h1>
    <p><?= $escape->escapeText($translator->trans('password_recovery.accepted.body')) ?></p>
    <p><?= $escape->escapeText($translator->trans('password_recovery.accepted.delay')) ?></p>
    <p><a href="/login"><?= $escape->escapeText($translator->trans('password_recovery.login')) ?></a></p>
    <p><a href="/forgot-password"><?= $escape->escapeText($translator->trans('password_recovery.again')) ?></a></p>
</section>
