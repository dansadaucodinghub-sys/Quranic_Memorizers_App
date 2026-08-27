<?php

declare(strict_types=1);
?>
<?= $translator->trans('email.recovery.heading') . "\n\n" ?>
<?= $translator->trans('email.recovery.instructions') . "\n" ?>
<?= $view->string('url') . "\n\n" ?>
<?= $translator->trans('email.recovery.expires', ['expiry' => $view->string('expiry')]) . "\n" ?>
<?= $translator->trans('email.recovery.ignore') . "\n" ?>
