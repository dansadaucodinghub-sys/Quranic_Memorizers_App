<?php

declare(strict_types=1);

$account = $view->array('account');
$events = $view->list('events');
?>
<section id="account-state-panel" data-qmdb-fragment-root>
    <dl class="status-card">
        <dt><?= $escape->escapeText($translator->trans('account_state.public_id')) ?></dt><dd><?= $escape->escapeText($account['public_id']) ?></dd>
        <dt><?= $escape->escapeText($translator->trans('account_state.status')) ?></dt><dd><?= $escape->escapeText($account['status']) ?></dd>
        <dt><?= $escape->escapeText($translator->trans('account_state.version')) ?></dt><dd><?= $escape->escapeText((string)$account['version']) ?></dd>
        <dt><?= $escape->escapeText($translator->trans('account_state.updated')) ?></dt><dd><?= $escape->escapeText($account['updated_at']) ?></dd>
    </dl>
    <?php if ($account['status'] === 'ACTIVE'): ?>
        <a class="button danger" data-qmdb-modal href="/platform/security/accounts/<?= $escape->escapeAttribute($account['public_id']) ?>/suspend"><?= $escape->escapeText($translator->trans('account_state.suspend')) ?></a>
    <?php elseif ($account['status'] === 'SUSPENDED'): ?>
        <a class="button" data-qmdb-modal href="/platform/security/accounts/<?= $escape->escapeAttribute($account['public_id']) ?>/reactivate"><?= $escape->escapeText($translator->trans('account_state.reactivate')) ?></a>
    <?php endif; ?>
    <h2><?= $escape->escapeText($translator->trans('account_state.recent_events')) ?></h2>
    <ul>
        <?php foreach ($events as $event): ?>
            <?php if (is_array($event)): ?><li><?= $escape->escapeText((string)($event['occurred_at'] ?? '')) ?> — <?= $escape->escapeText((string)($event['code'] ?? '')) ?> (<?= $escape->escapeText((string)($event['outcome'] ?? '')) ?>)</li><?php endif; ?>
        <?php endforeach; ?>
    </ul>
</section>
