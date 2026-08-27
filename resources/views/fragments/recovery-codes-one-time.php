<?php

declare(strict_types=1);

?>
<section data-qmdb-fragment-root aria-labelledby="recovery-codes-heading">
    <div class="status-card" role="region" aria-label="<?= $escape->escapeAttribute($translator->trans('recovery_codes.codes')) ?>" data-qmdb-recovery-code-display>
        <ul class="recovery-code-list" dir="ltr" data-qmdb-recovery-code-values>
            <?php foreach ($view->list('codes') as $code) :
                if (!is_string($code)) {
                    continue;
                } ?>
            <li><code><?= $escape->escapeText($code) ?></code></li>
            <?php endforeach; ?>
        </ul>
        <button class="button" type="button" data-qmdb-copy-recovery-codes><?= $escape->escapeText($translator->trans('recovery_codes.copy')) ?></button>
    </div>
    <p><strong><?= $escape->escapeText($translator->trans('recovery_codes.save_warning')) ?></strong></p>
    <a class="button primary" href="/account/security/authentication"><?= $escape->escapeText($translator->trans('recovery_codes.saved')) ?></a>
</section>
