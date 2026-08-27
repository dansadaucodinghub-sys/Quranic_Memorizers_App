<?php

declare(strict_types=1);

$mode = $view->string('mode');
?>
<section data-qmdb-fragment-root data-qmdb-form-region aria-labelledby="totp-heading">
    <?php if ($mode === 'start'): ?>
    <form method="post" action="/account/security/mfa/totp/enroll" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('totp.begin')) ?></button>
    </form>
    <?php else: ?>
    <figure class="status-card">
        <img src="/account/security/mfa/totp/<?= $escape->escapeAttribute($view->string('authenticator_id')) ?>/qr" alt="<?= $escape->escapeAttribute($translator->trans('totp.qr_alt')) ?>">
        <figcaption><?= $escape->escapeText($translator->trans('totp.manual_secret')) ?> <code dir="ltr"><?= $escape->escapeText($view->string('manual_secret')) ?></code></figcaption>
    </figure>
    <form method="post" action="/account/security/mfa/totp/<?= $escape->escapeAttribute($view->string('authenticator_id')) ?>/confirm" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <label for="totp-confirm-code"><?= $escape->escapeText($translator->trans('mfa.totp_code')) ?></label>
        <input id="totp-confirm-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('totp.confirm')) ?></button>
    </form>
    <?php endif; ?>
</section>
