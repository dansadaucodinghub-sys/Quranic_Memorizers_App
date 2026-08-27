<?php

declare(strict_types=1);
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('auth_security.passkeys')) ?>">
    <?php foreach ($view->list('passkeys') as $passkey): if (!is_array($passkey)) { continue; } ?>
    <p><?= $escape->escapeText(is_string($passkey['name'] ?? null) ? $passkey['name'] : '') ?></p>
    <?php endforeach; ?>
</section>
