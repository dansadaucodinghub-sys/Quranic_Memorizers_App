<?php

declare(strict_types=1);

$methods = $view->list('methods');
$error = $view->string('error');
?>
<section data-qmdb-fragment-root data-qmdb-form-region aria-labelledby="mfa-login-heading">
    <?php if ($error !== ''): ?><p class="form-error" role="alert"><?= $escape->escapeText($translator->trans('mfa.invalid')) ?></p><?php endif; ?>
    <?php if (in_array('TOTP', $methods, true)): ?>
    <form method="post" action="/login/mfa/totp" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_totp')) ?>">
        <label for="mfa-totp"><?= $escape->escapeText($translator->trans('mfa.totp_code')) ?></label>
        <input id="mfa-totp" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('mfa.verify')) ?></button>
    </form>
    <?php endif; ?>
    <?php if (in_array('RECOVERY_CODE', $methods, true)): ?>
    <details>
        <summary><?= $escape->escapeText($translator->trans('mfa.use_recovery_code')) ?></summary>
        <form method="post" action="/login/mfa/recovery-code" data-qmdb-progressive-form>
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_recovery')) ?>">
            <label for="mfa-recovery"><?= $escape->escapeText($translator->trans('mfa.recovery_code')) ?></label>
            <input id="mfa-recovery" name="code" autocomplete="one-time-code" maxlength="128" required>
            <button class="button" type="submit"><?= $escape->escapeText($translator->trans('mfa.verify')) ?></button>
        </form>
    </details>
    <?php endif; ?>
    <?php if (in_array('PASSKEY', $methods, true)): ?>
    <button class="button" type="button" data-qmdb-passkey-login data-mode="mfa"
        data-options-url="/login/mfa/passkey/options" data-verify-url="/login/mfa/passkey/verify"
        data-csrf-token="<?= $escape->escapeAttribute($view->string('csrf_passkey')) ?>">
        <?= $escape->escapeText($translator->trans('mfa.use_passkey')) ?>
    </button>
    <?php endif; ?>
    <p><a href="/login"><?= $escape->escapeText($translator->trans('mfa.restart_login')) ?></a></p>
</section>
