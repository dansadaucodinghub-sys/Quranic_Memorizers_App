<?php

declare(strict_types=1);

$section = $view->string('section');
$scope = $view->string('scope');
$error = $view->string('error');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('analytics.title')) ?>">
    <p><strong><?= $escape->escapeText($translator->trans('analytics.scope')) ?>:</strong> <?= $escape->escapeText($scope) ?></p>
    <?php if ($error !== '') :
        ?><p role="alert"><?= $escape->escapeText($translator->trans($error)) ?></p><?php
    endif; ?>
    <?php if ($section === 'search') : ?>
        <form method="get" role="search">
            <label for="p11-search"><?= $escape->escapeText($translator->trans('analytics.search.label')) ?></label>
            <input id="p11-search" name="q" maxlength="200" value="<?= $escape->escapeAttribute($view->string('query')) ?>">
            <button type="submit"><?= $escape->escapeText($translator->trans('analytics.search.submit')) ?></button>
        </form>
        <?php $items = $view->list('items'); ?>
        <?php if ($items === []) :
            ?><p><?= $escape->escapeText($translator->trans('analytics.empty')) ?></p><?php
        else : ?>
            <ol><?php foreach ($items as $item) :
                ?><li>
                <article><h2><bdi><?= $escape->escapeText($item['title']) ?></bdi></h2>
                    <p><?= $escape->escapeText($item['summary']) ?></p>
                    <p><small><?= $escape->escapeText($item['source_kind']) ?> · <?= $escape->escapeText($item['status_code']) ?> · <?= $escape->escapeText($item['provenance_code']) ?> · <?= $escape->escapeText((string) $item['projected_at']) ?></small></p>
                </article>
            </li>
                <?php endforeach; ?></ol>
        <?php endif; ?>
    <?php elseif ($section === 'analytics') : ?>
        <h2><?= $escape->escapeText($translator->trans('analytics.dashboards')) ?></h2>
        <?php $dashboards = $view->list('dashboards'); ?>
        <?php if ($dashboards === []) :
            ?><p><?= $escape->escapeText($translator->trans('analytics.empty')) ?></p><?php
        else : ?>
            <table><caption><?= $escape->escapeText($translator->trans('analytics.dashboard.caption')) ?></caption><thead><tr><th scope="col"><?= $escape->escapeText($translator->trans('analytics.code')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('analytics.widgets')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('analytics.freshness')) ?></th></tr></thead><tbody>
            <?php foreach ($dashboards as $dashboard) :
                ?><tr><th scope="row"><?= $escape->escapeText($dashboard['code']) ?></th><td><?= $escape->escapeText((string) $dashboard['widget_count']) ?></td><td><?= $escape->escapeText((string) ($dashboard['freshness_at'] ?? '—')) ?></td></tr><?php
            endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    <?php elseif ($section === 'reports') : ?>
        <h2><?= $escape->escapeText($translator->trans('analytics.reports')) ?></h2>
        <?php $definitions = $view->list('definitions'); ?>
        <?php foreach ($definitions as $definition) : ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                <input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
                <input type="hidden" name="definition_id" value="<?= $escape->escapeAttribute($definition['public_id']) ?>">
                <p><strong><?= $escape->escapeText($definition['code']) ?></strong><?php if ((int) $definition['approval_required'] === 1) :
                    ?> — <?= $escape->escapeText($translator->trans('analytics.approval_required')) ?><?php
                           endif; ?></p>
                <label><?= $escape->escapeText($translator->trans('analytics.purpose')) ?><textarea name="purpose" maxlength="500" required></textarea></label>
                <button type="submit"><?= $escape->escapeText($translator->trans('analytics.request_report')) ?></button>
            </form>
        <?php endforeach; ?>
        <h2><?= $escape->escapeText($translator->trans('analytics.recent_runs')) ?></h2>
        <?php $runs = $view->list('runs'); ?>
        <?php if ($runs === []) :
            ?><p><?= $escape->escapeText($translator->trans('analytics.empty')) ?></p><?php
        else : ?>
            <table><caption><?= $escape->escapeText($translator->trans('analytics.runs.caption')) ?></caption><thead><tr><th scope="col"><?= $escape->escapeText($translator->trans('analytics.code')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('analytics.status')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('analytics.expires')) ?></th><th scope="col"><?= $escape->escapeText($translator->trans('analytics.export')) ?></th></tr></thead><tbody>
            <?php foreach ($runs as $run) :
                $exportId = is_string($run['export_public_id'] ?? null) ? $run['export_public_id'] : '';
                $exportBase = $scope === 'WORKSPACE' ? '/workspace/exports/' : '/platform/exports/';
                $downloadHref = $exportBase . rawurlencode($exportId) . '/download';
                ?><tr>
                    <th scope="row"><?= $escape->escapeText($run['code']) ?></th>
                    <td><?= $escape->escapeText($run['status_code']) ?></td>
                    <td><?= $escape->escapeText((string) $run['expires_at']) ?></td>
                    <td>
                        <?php if ($exportId !== '') : ?>
                            <a href="<?= $escape->escapeAttribute($downloadHref) ?>"><?= $escape->escapeText($translator->trans('analytics.download')) ?></a>
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr><?php
            endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    <?php else : ?>
        <h2><?= $escape->escapeText($translator->trans('analytics.approvals')) ?></h2>
        <?php $approvals = $view->list('approvals'); ?>
        <?php if ($approvals === []) : ?>
            <p><?= $escape->escapeText($translator->trans('analytics.empty')) ?></p>
        <?php else : ?>
            <?php foreach ($approvals as $approval) :
                $approvalBase = $scope === 'WORKSPACE' ? '/workspace/reports/' : '/platform/reports/';
                ?>
                <form method="post" action="<?= $escape->escapeAttribute($approvalBase . rawurlencode($approval['public_id']) . '/approve') ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                    <input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
                    <p><strong><?= $escape->escapeText($approval['code']) ?></strong></p>
                    <p><?= $escape->escapeText($approval['purpose']) ?></p>
                    <button type="submit"><?= $escape->escapeText($translator->trans('analytics.approve')) ?></button>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
