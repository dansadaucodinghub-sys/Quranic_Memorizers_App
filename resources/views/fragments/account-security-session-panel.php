<?php

declare(strict_types=1);

$sessions = $view->list('sessions');
$devices = $view->list('devices');
?>
<section id="account-security-session-panel" data-qmdb-fragment-root aria-labelledby="session-inventory-heading">
    <div class="section-heading">
        <h2 id="session-inventory-heading"><?= $escape->escapeText($translator->trans('account_security.sessions')) ?></h2>
        <form method="post" action="/logout" data-qmdb-progressive-form data-qmdb-navigation-form>
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('logout_csrf_token')) ?>">
            <button class="button" type="submit"><?= $escape->escapeText($translator->trans('account_security.logout')) ?></button>
        </form>
    </div>
    <div class="card-grid">
        <?php foreach ($sessions as $session): ?>
            <?php if (!is_array($session)) { continue; } ?>
            <article class="status-card">
                <h3><?= $escape->escapeText(($session['current'] ?? false) === true ? $translator->trans('account_security.current_session') : $translator->trans('account_security.other_session')) ?></h3>
                <dl>
                    <dt><?= $escape->escapeText($translator->trans('account_security.status')) ?></dt>
                    <dd><?= $escape->escapeText(is_string($session['status'] ?? null) ? $session['status'] : '') ?></dd>
                    <dt><?= $escape->escapeText($translator->trans('account_security.last_seen')) ?></dt>
                    <dd><?= $escape->escapeText(is_string($session['last_seen_at'] ?? null) ? $session['last_seen_at'] : '') ?></dd>
                    <dt><?= $escape->escapeText($translator->trans('account_security.absolute_expiry')) ?></dt>
                    <dd><?= $escape->escapeText(is_string($session['absolute_expires_at'] ?? null) ? $session['absolute_expires_at'] : '') ?></dd>
                </dl>
                <?php if (($session['current'] ?? false) !== true && ($session['status'] ?? '') === 'ACTIVE'): ?>
                    <a class="button" data-qmdb-modal href="/account/security/sessions/<?= $escape->escapeAttribute(is_string($session['public_id'] ?? null) ? $session['public_id'] : '') ?>/revoke"><?= $escape->escapeText($translator->trans('account_security.revoke_session')) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <h2><?= $escape->escapeText($translator->trans('account_security.devices')) ?></h2>
    <div class="card-grid">
        <?php foreach ($devices as $device): ?>
            <?php if (!is_array($device)) { continue; } ?>
            <article class="status-card">
                <h3><?= $escape->escapeText(($device['current'] ?? false) === true ? $translator->trans('account_security.current_device') : $translator->trans('account_security.other_device')) ?></h3>
                <p><?= $escape->escapeText($translator->trans('account_security.active_sessions')) ?>: <?= $escape->escapeText((string)($device['active_session_count'] ?? 0)) ?></p>
                <p><?= $escape->escapeText($translator->trans('account_security.last_seen')) ?>: <?= $escape->escapeText(is_string($device['last_seen_at'] ?? null) ? $device['last_seen_at'] : '') ?></p>
                <?php if (($device['current'] ?? false) !== true && ($device['status'] ?? '') === 'ACTIVE'): ?>
                    <a class="button" data-qmdb-modal href="/account/security/devices/<?= $escape->escapeAttribute(is_string($device['public_id'] ?? null) ? $device['public_id'] : '') ?>/revoke"><?= $escape->escapeText($translator->trans('account_security.revoke_device')) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
