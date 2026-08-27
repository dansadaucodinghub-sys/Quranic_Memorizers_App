<?php

declare(strict_types=1);
?>
<section data-qmdb-fragment-root data-qmdb-modal-content aria-labelledby="totp-revoke-heading">
    <p><?= $escape->escapeText($translator->trans('totp.revoke_intro')) ?></p>
    <form method="post" action="/account/security/mfa/totp/<?= $escape->escapeAttribute($view->string('authenticator_id')) ?>/revoke" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string)$view->integer('expected_version')) ?>">
        <button class="button danger" type="submit"><?= $escape->escapeText($translator->trans('auth_security.revoke')) ?></button>
        <a class="button" href="/account/security/authentication"><?= $escape->escapeText($translator->trans('action.cancel')) ?></a>
    </form>
</section>
