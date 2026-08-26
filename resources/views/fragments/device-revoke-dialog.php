<?php

declare(strict_types=1);
?>
<section data-qmdb-fragment-root data-qmdb-form-region>
    <h2 id="device-revoke-title" tabindex="-1"><?= $escape->escapeText($translator->trans('device_revoke.heading')) ?></h2>
    <p><?= $escape->escapeText($translator->trans('device_revoke.consequence')) ?></p>
    <form method="post" action="/account/security/devices/<?= $escape->escapeAttribute($view->string('public_id')) ?>/revoke" data-qmdb-progressive-form data-qmdb-success-target="#account-security-session-panel" data-qmdb-close-modal-on-success="true">
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string)$view->integer('version')) ?>">
        <a class="button" href="/account/security/sessions" data-qmdb-modal-close><?= $escape->escapeText($translator->trans('action.cancel')) ?></a>
        <button class="button danger" type="submit"><?= $escape->escapeText($translator->trans('account_security.revoke_device')) ?></button>
    </form>
</section>
