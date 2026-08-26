<?php

declare(strict_types=1);
?>
<dialog id="qmdb-dialog" class="modal" aria-labelledby="qmdb-dialog-title">
    <div class="modal-frame">
        <header class="modal-header">
            <h2 id="qmdb-dialog-title" tabindex="-1"><?= $escape->escapeText($translator->trans('modal.title')) ?></h2>
            <button type="button" class="button button-quiet" data-qmdb-modal-close><?= $escape->escapeText($translator->trans('action.close')) ?></button>
        </header>
        <div data-qmdb-modal-loading hidden><p tabindex="-1"><?= $escape->escapeText($translator->trans('modal.loading')) ?></p></div>
        <div data-qmdb-modal-error hidden tabindex="-1"></div>
        <div data-qmdb-modal-content></div>
        <a data-qmdb-modal-fallback href="/system/about"><?= $escape->escapeText($translator->trans('error.fallback')) ?></a>
    </div>
</dialog>
