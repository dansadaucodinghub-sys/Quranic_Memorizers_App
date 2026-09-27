<?php

declare(strict_types=1);

$section = $view->string('section');
$scope = $view->string('scope');
$csrf = $view->string('csrf_token');
$error = $view->string('error');
$success = $view->string('success');
$base = $scope === 'WORKSPACE' ? '/workspace/integrations' : '/platform/integrations';
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('p12.title')) ?>">
    <nav aria-label="<?= $escape->escapeAttribute($translator->trans('p12.navigation')) ?>">
        <a href="/account/notifications"><?= $escape->escapeText($translator->trans('p12.notifications')) ?></a>
        <a href="/account/privacy/requests"><?= $escape->escapeText($translator->trans('p12.privacy')) ?></a>
        <?php if ($scope !== 'ACCOUNT') :
            ?><a href="<?= $escape->escapeAttribute($base) ?>"><?= $escape->escapeText($translator->trans('p12.integrations')) ?></a><?php
        endif; ?>
        <?php if ($scope === 'PLATFORM') :
            ?><a href="/platform/operations"><?= $escape->escapeText($translator->trans('p12.operations')) ?></a><?php
        endif; ?>
    </nav>
    <div role="status" aria-live="polite" aria-atomic="true">
        <?php if ($error !== '') :
            ?><p role="alert"><?= $escape->escapeText($translator->trans($error)) ?></p><?php
        endif; ?>
        <?php if ($success !== '') :
            ?><p><?= $escape->escapeText($translator->trans($success)) ?></p><?php
        endif; ?>
    </div>
    <?php if ($view->string('one_time_credential') !== '') : ?>
        <section aria-labelledby="credential-title"><h2 id="credential-title"><?= $escape->escapeText($translator->trans('p12.credential_once')) ?></h2><p><?= $escape->escapeText($translator->trans('p12.credential_once_help')) ?></p><output dir="ltr" tabindex="0"><?= $escape->escapeText($view->string('one_time_credential')) ?></output></section>
    <?php endif; ?>
    <?php if ($view->string('one_time_webhook_secret') !== '') : ?>
        <section aria-labelledby="webhook-secret-title"><h2 id="webhook-secret-title"><?= $escape->escapeText($translator->trans('p12.webhook_secret_once')) ?></h2><output dir="ltr" tabindex="0"><?= $escape->escapeText($view->string('one_time_webhook_secret')) ?></output></section>
    <?php endif; ?>
    <?php if ($section === 'notifications') : ?>
        <h2><?= $escape->escapeText($translator->trans('p12.notifications')) ?></h2>
        <?php $notifications = $view->list('notifications'); ?>
        <?php if ($notifications === []) :
            ?><p><?= $escape->escapeText($translator->trans('p12.empty')) ?></p><?php
        else :
            ?><ol><?php foreach ($notifications as $item) :
    ?><li><article><h3><?= $escape->escapeText($item['safe_subject']) ?></h3><p><?= $escape->escapeText($item['safe_body']) ?></p><p><small><?= $escape->escapeText((string) $item['created_at']) ?> · <?= $escape->escapeText($item['status_code']) ?></small></p><?php if ($item['read_at'] === null) :
    ?><form method="post" action="/account/notifications/read"><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>"><input type="hidden" name="notification_id" value="<?= $escape->escapeAttribute($item['public_id']) ?>"><button type="submit"><?= $escape->escapeText($translator->trans('p12.mark_read')) ?></button></form><?php
    endif; ?></article></li><?php
            endforeach; ?></ol><?php
        endif; ?>
    <?php elseif ($section === 'privacy') : ?>
        <h2><?= $escape->escapeText($translator->trans('p12.privacy')) ?></h2>
        <?php if ($scope === 'ACCOUNT') :
            ?><form method="post" action="/account/privacy/requests"><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>"><label for="p12-subject"><?= $escape->escapeText($translator->trans('p12.subject_id')) ?></label><input id="p12-subject" name="subject_id" required maxlength="36" autocomplete="off" dir="ltr"><label for="p12-request-type"><?= $escape->escapeText($translator->trans('p12.request_type')) ?></label><select id="p12-request-type" name="request_type" required><option value="ACCESS">ACCESS</option><option value="CORRECTION">CORRECTION</option><option value="EXPORT">EXPORT</option><option value="ERASURE">ERASURE</option><option value="CHILD_DATA">CHILD_DATA</option></select><label for="p12-authority"><?= $escape->escapeText($translator->trans('p12.authority')) ?></label><select id="p12-authority" name="authority_code" required><option value="SELF">SELF</option><option value="GUARDIAN">GUARDIAN</option><option value="AUTHORIZED_REPRESENTATIVE">AUTHORIZED_REPRESENTATIVE</option></select><button type="submit"><?= $escape->escapeText($translator->trans('p12.submit_request')) ?></button></form><?php
        endif; ?>
        <div tabindex="0"><table><caption><?= $escape->escapeText($translator->trans('p12.privacy_cases')) ?></caption><thead><tr><th scope="col">ID</th><th scope="col"><?= $escape->escapeText($translator->trans('p12.request_type')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.status')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.deadline')) ?></th></tr></thead><tbody><?php foreach ($view->list('privacy_requests') as $item) :
            ?><tr><td dir="ltr"><?= $escape->escapeText($item['public_id']) ?></td><td><?= $escape->escapeText($item['request_type']) ?></td><td><?= $escape->escapeText($item['status_code']) ?></td><td><?= $escape->escapeText((string) $item['due_at']) ?></td></tr><?php
                                          endforeach; ?></tbody></table></div>
    <?php elseif ($section === 'integrations') : ?>
        <h2><?= $escape->escapeText($translator->trans('p12.api_clients')) ?></h2>
        <form method="post" action="<?= $escape->escapeAttribute($base . '/clients') ?>"><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>"><label for="p12-client-code"><?= $escape->escapeText($translator->trans('p12.client_code')) ?></label><input id="p12-client-code" name="client_code" pattern="[a-z0-9][a-z0-9._-]{2,79}" required maxlength="80" dir="ltr"><label for="p12-client-name"><?= $escape->escapeText($translator->trans('p12.display_name')) ?></label><input id="p12-client-name" name="display_name" required maxlength="191"><fieldset><legend><?= $escape->escapeText($translator->trans('p12.scopes')) ?></legend><label><input type="checkbox" name="scopes[]" value="projections.results.read" checked> projections.results.read</label><label><input type="checkbox" name="scopes[]" value="webhooks.manage"> webhooks.manage</label></fieldset><button type="submit"><?= $escape->escapeText($translator->trans('p12.create_client')) ?></button></form>
        <div tabindex="0"><table><caption><?= $escape->escapeText($translator->trans('p12.api_clients')) ?></caption><thead><tr><th scope="col"><?= $escape->escapeText($translator->trans('p12.client_code')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.display_name')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.status')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.actions')) ?></th></tr></thead><tbody><?php foreach ($view->list('clients') as $item) :
            ?><tr><td dir="ltr"><?= $escape->escapeText($item['client_code']) ?></td><td><?= $escape->escapeText($item['display_name']) ?></td><td><?= $escape->escapeText($item['status_code']) ?></td><td><form method="post" action="<?= $escape->escapeAttribute($base . '/clients/rotate') ?>"><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>"><input type="hidden" name="client_id" value="<?= $escape->escapeAttribute($item['public_id']) ?>"><button type="submit"><?= $escape->escapeText($translator->trans('p12.rotate')) ?></button></form><form method="post" action="<?= $escape->escapeAttribute($base . '/clients/revoke') ?>"><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>"><input type="hidden" name="client_id" value="<?= $escape->escapeAttribute($item['public_id']) ?>"><button type="submit"><?= $escape->escapeText($translator->trans('p12.revoke')) ?></button></form></td></tr><?php
                                          endforeach; ?></tbody></table></div>
        <h2><?= $escape->escapeText($translator->trans('p12.webhooks')) ?></h2>
        <form method="post" action="<?= $escape->escapeAttribute($base . '/webhooks') ?>"><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>"><label for="p12-client-id">Client ID</label><input id="p12-client-id" name="client_id" required maxlength="36" dir="ltr"><label for="p12-event"><?= $escape->escapeText($translator->trans('p12.event')) ?></label><select id="p12-event" name="event_code"><option value="competition.result.published">competition.result.published</option><option value="certificate.issued">certificate.issued</option><option value="privacy.request.updated">privacy.request.updated</option></select><label for="p12-endpoint"><?= $escape->escapeText($translator->trans('p12.endpoint')) ?></label><input id="p12-endpoint" type="url" name="endpoint_url" required maxlength="1000" placeholder="https://partner.example/webhooks"><button type="submit"><?= $escape->escapeText($translator->trans('p12.create_webhook')) ?></button></form>
        <div tabindex="0"><table><caption><?= $escape->escapeText($translator->trans('p12.webhooks')) ?></caption><thead><tr><th scope="col"><?= $escape->escapeText($translator->trans('p12.event')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.endpoint')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.status')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('p12.actions')) ?></th></tr></thead><tbody><?php foreach ($view->list('webhooks') as $item) :
            ?><tr><td dir="ltr"><?= $escape->escapeText($item['event_code']) ?></td><td dir="ltr"><?= $escape->escapeText($item['endpoint_url']) ?></td><td><?= $escape->escapeText($item['status_code']) ?></td><td><form method="post" action="<?= $escape->escapeAttribute($base . '/webhooks/suspend') ?>"><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrf) ?>"><input type="hidden" name="webhook_id" value="<?= $escape->escapeAttribute($item['public_id']) ?>"><button type="submit"><?= $escape->escapeText($translator->trans('p12.suspend')) ?></button></form></td></tr><?php
                                          endforeach; ?></tbody></table></div>
    <?php else : ?>
        <h2><?= $escape->escapeText($translator->trans('p12.operations')) ?></h2><dl><?php foreach ($view->array('summary') as $name => $value) :
            ?><div><dt><?= $escape->escapeText(str_replace('_', ' ', $name)) ?></dt><dd><?= $escape->escapeText((string) $value) ?></dd></div><?php
            endforeach; ?></dl><p><?= $escape->escapeText($translator->trans('p12.operations_help')) ?></p>
    <?php endif; ?>
</section>
