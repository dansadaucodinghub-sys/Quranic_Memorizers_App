<?php

declare(strict_types=1);

?>
<section data-qmdb-fragment-root data-qmdb-form-region aria-labelledby="recovery-codes-heading">
    <p><?= $escape->escapeText($translator->trans('recovery_codes.remaining')) ?> <strong><?= $escape->escapeText((string)$view->integer('remaining_codes')) ?></strong></p>
    <?php if ($view->boolean('can_regenerate')) : ?>
    <form method="post" action="/account/security/mfa/recovery-codes/regenerate" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <button class="button danger" type="submit"><?= $escape->escapeText($translator->trans('recovery_codes.regenerate')) ?></button>
    </form>
    <?php else : ?>
    <a class="button" href="/account/step-up/MFA_REGENERATE_RECOVERY_CODES"><?= $escape->escapeText($translator->trans('recovery_codes.verify_first')) ?></a>
    <?php endif; ?>
</section>
