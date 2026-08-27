<?php

declare(strict_types=1);

$methods = $view->list('methods');
?>
<section data-qmdb-fragment-root data-qmdb-form-region aria-labelledby="step-up-heading">
    <?php if ($view->string('error') !== '') :
        ?><p class="form-error" role="alert"><?= $escape->escapeText($translator->trans('mfa.invalid')) ?></p><?php
    endif; ?>
    <?php if (in_array('PASSWORD', $methods, true)) : ?>
    <form method="post" action="/account/step-up/password" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_password')) ?>">
        <label for="step-up-password"><?= $escape->escapeText($translator->trans('form.password')) ?></label>
        <input id="step-up-password" name="password" type="password" autocomplete="current-password" maxlength="1024" required>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('step_up.verify')) ?></button>
    </form>
    <?php endif; ?>
    <?php if (in_array('TOTP', $methods, true)) : ?>
    <form method="post" action="/account/step-up/totp" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_totp')) ?>">
        <label for="step-up-totp"><?= $escape->escapeText($translator->trans('mfa.totp_code')) ?></label>
        <input id="step-up-totp" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
        <button class="button" type="submit"><?= $escape->escapeText($translator->trans('step_up.verify')) ?></button>
    </form>
    <?php endif; ?>
    <?php if (in_array('RECOVERY_CODE', $methods, true)) : ?>
    <form method="post" action="/account/step-up/recovery-code" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_recovery')) ?>">
        <label for="step-up-recovery"><?= $escape->escapeText($translator->trans('mfa.recovery_code')) ?></label>
        <input id="step-up-recovery" name="code" autocomplete="one-time-code" maxlength="128" required>
        <button class="button" type="submit"><?= $escape->escapeText($translator->trans('step_up.verify')) ?></button>
    </form>
    <?php endif; ?>
    <?php if (in_array('PASSKEY', $methods, true)) : ?>
    <button class="button" type="button" data-qmdb-passkey-step-up
        data-options-url="/account/step-up/passkey/options" data-verify-url="/account/step-up/passkey/verify"
        data-csrf-token="<?= $escape->escapeAttribute($view->string('csrf_passkey')) ?>">
        <?= $escape->escapeText($translator->trans('mfa.use_passkey')) ?>
    </button>
    <?php endif; ?>
</section>
