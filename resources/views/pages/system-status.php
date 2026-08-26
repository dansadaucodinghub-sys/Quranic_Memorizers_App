<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;
?>
<div class="shell prose-page"><h1><?= $escape->escapeText($translator->trans('system.status.heading')) ?></h1><?= $renderer->render('fragments.system-status-card', new ViewData($view->array('status')), $translator)->trustedHtml() ?></div>
