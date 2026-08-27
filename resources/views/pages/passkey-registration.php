<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('passkey.eyebrow')) ?></p>
    <h1 id="passkey-registration-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('passkey.register_heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('passkey.register_intro')) ?></p>
    <form data-qmdb-passkey-registration data-options-url="/account/security/passkeys/registration/options"
        data-verify-url="/account/security/passkeys/registration/verify"
        data-csrf-token="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <label for="passkey-display-name"><?= $escape->escapeText($translator->trans('passkey.name')) ?></label>
        <input id="passkey-display-name" name="display_name" maxlength="120" required>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('passkey.register')) ?></button>
    </form>
    <p><noscript><?= $escape->escapeText($translator->trans('passkey.javascript_required')) ?></noscript></p>
</article>
