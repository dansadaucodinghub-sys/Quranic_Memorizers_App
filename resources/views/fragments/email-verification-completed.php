<?php

declare(strict_types=1);
?>
<section class="identity-result" data-qmdb-fragment-root tabindex="-1" data-qmdb-completion-heading>
    <h1><?= $escape->escapeText($translator->trans('verification.completed.heading')) ?></h1>
    <p><?= $escape->escapeText($translator->trans('verification.completed.body')) ?></p>
    <p><a href="/"><?= $escape->escapeText($translator->trans('action.home')) ?></a></p>
</section>
