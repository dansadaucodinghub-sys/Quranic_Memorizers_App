<?php

declare(strict_types=1);
?>
<?= $translator->trans('email.verification.heading') . "\n\n" ?>
<?= $translator->trans('email.verification.instructions') . "\n" ?>
<?= $view->string('url') . "\n\n" ?>
<?= $translator->trans('email.verification.expires', ['expiry' => $view->string('expiry')]) . "\n" ?>
<?= $translator->trans('email.verification.ignore') . "\n" ?>
