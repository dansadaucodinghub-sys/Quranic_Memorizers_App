<?php

declare(strict_types=1);

$mode = $view->string('mode');
$csrf = $view->string('csrf_token');
$submission = $view->string('submission_id');
$requestId = $view->string('request_id');
$requests = $view->list('requests');
$text = static fn (string $key): string => $escape->escapeText($translator->trans($key));
$formAction = match ($mode) {
    'temporary_request' => '/account/security/privileged-access/temporary/request',
    'support_request' => '/account/security/support-access/request',
    'break_glass' => '/account/security/break-glass/activate',
    'approve' => '/account/security/privileged-access/' . rawurlencode($requestId) . '/approve',
    'review' => '/account/security/privileged-access/' . rawurlencode($requestId) . '/review',
    default => '',
};
?>
<section class="auth-card privileged-access-panel" data-qmdb-fragment-root aria-labelledby="privileged-access-heading">
    <header class="section-heading">
        <div>
            <p class="eyebrow"><?= $text('privileged_access.eyebrow') ?></p>
            <h1 id="privileged-access-heading"><?= $text('privileged_access.heading') ?></h1>
            <p><?= $text('privileged_access.intro') ?></p>
        </div>
        <?php if ($mode === 'inventory'): ?>
        <div class="button-group">
            <a class="button button-quiet" href="/account/security/privileged-access/temporary/request"><?= $text('privileged_access.request_temporary') ?></a>
            <a class="button button-quiet" href="/account/security/support-access/request"><?= $text('privileged_access.request_support') ?></a>
            <a class="button button-danger" href="/account/security/break-glass/activate"><?= $text('privileged_access.break_glass') ?></a>
        </div>
        <?php endif; ?>
    </header>

    <?php if ($mode === 'inventory'): ?>
        <?php if ($requests === []): ?>
            <p class="workspace-empty" role="status"><?= $text('privileged_access.empty') ?></p>
        <?php else: ?>
        <div class="workspace-list">
            <?php foreach ($requests as $item): ?>
                <?php if (!is_array($item) || !is_string($item['id'] ?? null)): continue; endif; ?>
                <article class="workspace-option">
                    <span><strong><?= $escape->escapeText((string) ($item['type'] ?? '')) ?></strong>
                        <small><?= $escape->escapeText((string) ($item['scope'] ?? '')) ?> · <?= $escape->escapeText((string) ($item['status'] ?? '')) ?>
                        <?= ($item['workspace_name'] ?? '') !== '' ? ' · ' . $escape->escapeText((string) $item['workspace_name']) : '' ?></small></span>
                    <a class="button button-quiet" href="/account/security/privileged-access/<?= $escape->escapeAttribute($item['id']) ?>"><?= $text('privileged_access.view') ?></a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <form method="post" action="/account/security/privileged-access/active/end" class="form-actions">
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>">
            <button class="button button-danger" type="submit"><?= $text('privileged_access.end_active') ?></button>
        </form>
    <?php elseif (in_array($mode, ['temporary_request', 'support_request', 'break_glass'], true)): ?>
        <form method="post" action="<?= $escape->escapeAttribute($formAction) ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>">
            <input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($submission) ?>">
            <label><?= $text('privileged_access.scope') ?><select name="scope" required>
                <option value="PLATFORM"><?= $text('privileged_access.platform') ?></option><option value="WORKSPACE"><?= $text('privileged_access.workspace') ?></option>
            </select></label>
            <label><?= $text('privileged_access.workspace_id') ?> <input name="workspace_id" maxlength="36" autocomplete="off"></label>
            <label><?= $text('privileged_access.permission_codes') ?> <input name="permission_codes" maxlength="1024" required placeholder="workspace.auth.view, workspace.security.view"></label>
            <label><?= $text('privileged_access.duration') ?> <input type="number" name="duration_seconds" min="1" required value="900"></label>
            <?php if ($mode !== 'temporary_request'): ?><label><?= $text('privileged_access.reference') ?> <input name="reference_code" maxlength="128" required></label><?php endif; ?>
            <label><?= $text('privileged_access.justification') ?> <textarea name="justification" rows="5" maxlength="2000" required></textarea></label>
            <p class="form-hint"><?= $text('privileged_access.hint') ?></p>
            <div class="form-actions"><button class="button <?= $mode === 'break_glass' ? 'button-danger' : 'button-primary' ?>" type="submit"><?= $mode === 'break_glass' ? $text('privileged_access.activate_emergency') : $text('privileged_access.submit') ?></button></div>
        </form>
    <?php elseif ($mode === 'approve'): ?>
        <form method="post" action="<?= $escape->escapeAttribute($formAction) ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>">
            <label>Approved duration in seconds <input type="number" name="duration_seconds" min="1" required value="900"></label>
            <label>Reason<select name="reason_code"><option value="TEMPORARY_OPERATIONAL_NEED">Operational need</option><option value="SUPPORT_REQUEST">Support request</option><option value="SECURITY_INVESTIGATION">Security investigation</option></select></label>
            <div class="form-actions"><button class="button button-primary" type="submit">Confirm approval</button></div>
        </form>
    <?php elseif ($mode === 'review'): ?>
        <form method="post" action="<?= $escape->escapeAttribute($formAction) ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>">
            <label>Outcome<select name="outcome"><option value="ACCEPTED_USE">Accepted use</option><option value="CONCERN">Concern</option><option value="ESCALATED">Escalated</option></select></label>
            <label>Review summary <textarea name="summary" rows="5" maxlength="2000" required></textarea></label>
            <div class="form-actions"><button class="button button-primary" type="submit">Complete review</button></div>
        </form>
    <?php else: ?>
        <p class="workspace-empty"><?= $text('privileged_access.unavailable') ?></p>
    <?php endif; ?>
</section>
