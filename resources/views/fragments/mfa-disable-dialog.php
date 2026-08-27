<?php

declare(strict_types=1);
?>
<section data-qmdb-fragment-root data-qmdb-modal-content aria-labelledby="mfa-disable-heading">
    <p><?= $escape->escapeText($translator->trans('mfa.disable_intro')) ?></p>
    <form method="post" action="/account/security/mfa/disable" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <button class="button danger" type="submit"><?= $escape->escapeText($translator->trans('mfa.disable')) ?></button>
        <a class="button" href="/account/security/authentication"><?= $escape->escapeText($translator->trans('action.cancel')) ?></a>
    </form>
</section>
