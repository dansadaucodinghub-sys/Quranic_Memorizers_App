<?php

declare(strict_types=1);

$ready = $view->boolean('ready');
?>
<span class="status-badge <?= $ready ? 'status-ready' : 'status-not-ready' ?>">
    <span class="status-symbol" aria-hidden="true"><?= $ready ? '✓' : '!' ?></span>
    <?= $escape->escapeText($translator->trans($ready ? 'status.ready' : 'status.not_ready')) ?>
</span>
