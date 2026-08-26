<?php

declare(strict_types=1);

$field = $view->string('field');
$message = $view->string('message');
?>
<?php if ($message !== ''): ?>
    <p class="form-field-error" id="<?= $escape->escapeAttribute($field) ?>-error">
        <?= $escape->escapeText($translator->trans($message)) ?>
    </p>
<?php endif; ?>
