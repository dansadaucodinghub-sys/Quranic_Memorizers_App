<?php

declare(strict_types=1);

$methods = $view->list('methods');
$passkeys = $view->list('passkeys');
$totp = $view->array('totp_authenticator');
?>
<section data-qmdb-fragment-root aria-labelledby="authentication-security-heading">
    <div class="status-card">
        <h2><?= $escape->escapeText($translator->trans('auth_security.mfa_status')) ?></h2>
        <p><strong><?= $escape->escapeText($view->string('policy_status')) ?></strong></p>
        <p><?= $escape->escapeText($translator->trans('auth_security.active_methods')) ?>: <?= $escape->escapeText(implode(', ', array_filter($methods, 'is_string'))) ?></p>
    </div>
    <nav class="button-row" aria-label="<?= $escape->escapeAttribute($translator->trans('auth_security.actions')) ?>">
        <a class="button" href="/account/step-up/MFA_ENROLL_TOTP"><?= $escape->escapeText($translator->trans('auth_security.add_totp')) ?></a>
        <a class="button" href="/account/step-up/MFA_REGISTER_PASSKEY"><?= $escape->escapeText($translator->trans('auth_security.add_passkey')) ?></a>
        <a class="button" href="/account/security/mfa/recovery-codes"><?= $escape->escapeText($translator->trans('auth_security.recovery_codes')) ?></a>
    </nav>
    <?php if ($view->string('policy_status') === 'DISABLED' && $methods !== []) : ?>
        <?php if ($view->boolean('can_enable_mfa')) : ?>
    <form method="post" action="/account/security/mfa/enable" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_enable')) ?>">
        <label for="preferred-method"><?= $escape->escapeText($translator->trans('auth_security.preferred_method')) ?></label>
        <select id="preferred-method" name="preferred_method">
            <?php if (in_array('TOTP', $methods, true)) :
                ?><option value="TOTP">TOTP</option><?php
            endif; ?>
            <?php if (in_array('PASSKEY', $methods, true)) :
                ?><option value="PASSKEY"><?= $escape->escapeText($translator->trans('auth_security.passkey')) ?></option><?php
            endif; ?>
        </select>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('auth_security.enable_mfa')) ?></button>
    </form>
        <?php else : ?>
        <a class="button primary" href="/account/step-up/MFA_ENABLE"><?= $escape->escapeText($translator->trans('auth_security.verify_before_enable')) ?></a>
        <?php endif; ?>
    <?php elseif ($view->string('policy_status') === 'ENABLED') : ?>
        <a class="button danger" href="/account/step-up/MFA_DISABLE"><?= $escape->escapeText($translator->trans('auth_security.disable_mfa')) ?></a>
    <?php endif; ?>
    <?php if ($totp !== []) : ?>
    <h2><?= $escape->escapeText($translator->trans('auth_security.totp_authenticator')) ?></h2>
    <article class="status-card">
        <p><strong><?= $escape->escapeText(is_string($totp['status'] ?? null) ? $totp['status'] : '') ?></strong></p>
        <?php if ($view->boolean('can_revoke_totp')) : ?>
        <a class="button danger" data-qmdb-modal href="/account/security/mfa/totp/<?= $escape->escapeAttribute(is_string($totp['id'] ?? null) ? $totp['id'] : '') ?>/revoke"><?= $escape->escapeText($translator->trans('auth_security.revoke')) ?></a>
        <?php else : ?>
        <a class="button" href="/account/step-up/MFA_REVOKE_TOTP"><?= $escape->escapeText($translator->trans('auth_security.verify_before_revoke')) ?></a>
        <?php endif; ?>
    </article>
    <?php endif; ?>
    <h2><?= $escape->escapeText($translator->trans('auth_security.passkeys')) ?></h2>
    <div class="card-grid">
    <?php foreach ($passkeys as $passkey) :
        if (!is_array($passkey)) {
            continue;
        } ?>
        <article class="status-card">
            <h3><?= $escape->escapeText(is_string($passkey['name'] ?? null) ? $passkey['name'] : '') ?></h3>
            <p><?= $escape->escapeText(is_string($passkey['status'] ?? null) ? $passkey['status'] : '') ?></p>
            <?php if (($passkey['status'] ?? '') === 'ACTIVE') : ?>
                <?php if ($view->boolean('can_revoke_passkey')) : ?>
            <a class="button" data-qmdb-modal href="/account/security/passkeys/<?= $escape->escapeAttribute(is_string($passkey['id'] ?? null) ? $passkey['id'] : '') ?>/revoke"><?= $escape->escapeText($translator->trans('auth_security.revoke')) ?></a>
                <?php else : ?>
            <a class="button" href="/account/step-up/MFA_REVOKE_PASSKEY"><?= $escape->escapeText($translator->trans('auth_security.verify_before_revoke')) ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
</section>
