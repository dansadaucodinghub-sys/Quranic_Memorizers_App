<?php

declare(strict_types=1);

$errors = $view->array('errors');
$globalError = $view->string('global_error');
?>
<div class="form-error-summary" data-qmdb-error-summary role="alert" tabindex="-1"<?= $errors === [] && $globalError === '' ? ' hidden' : '' ?>>
    <h2><?= $escape->escapeText($translator->trans('form.error.summary')) ?></h2>
    <?php if ($globalError !== ''): ?>
        <p><?= $escape->escapeText($translator->trans($globalError)) ?></p>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <ul>
            <?php foreach ($errors as $field => $message): ?>
                <?php if (is_string($message)): ?>
                    <li><a href="#<?= $escape->escapeAttribute($field) ?>"><?= $escape->escapeText($translator->trans($message)) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
