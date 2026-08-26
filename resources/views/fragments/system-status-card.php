<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$locale = $translator->locale()->value();
?>
<section class="status-card" data-qmdb-fragment-root data-qmdb-refresh-root id="system-status-card" aria-labelledby="system-status-title">
    <div class="section-heading"><div><p class="eyebrow">QMDB</p><h2 id="system-status-title"><?= $escape->escapeText($translator->trans('status.heading')) ?></h2></div>
        <a class="button button-secondary" href="/system/status?lang=<?= $escape->escapeAttribute($locale) ?>" data-qmdb-refresh data-qmdb-refresh-target="#system-status-card"><?= $escape->escapeText($translator->trans('action.refresh_status')) ?></a>
    </div>
    <dl class="status-list">
        <?php foreach (['application', 'database', 'schema'] as $item): ?>
        <div><dt><?= $escape->escapeText($translator->trans('status.' . $item)) ?></dt><dd><?= $renderer->render('components.status-badge', new ViewData(['ready' => $view->boolean($item)]), $translator)->trustedHtml() ?></dd></div>
        <?php endforeach; ?>
    </dl>
</section>
