<?php

declare(strict_types=1);

$route = $view->string('route_name');
$params = $view->array('route_parameters');
$claimId = is_string($params['claimId'] ?? null) ? $params['claimId'] : '';
$pairingId = is_string($params['pairingId'] ?? null) ? $params['pairingId'] : '';
$personId = is_string($params['personId'] ?? null) ? $params['personId'] : '';
$caseId = is_string($params['caseId'] ?? null) ? $params['caseId'] : '';
$assertionId = is_string($params['assertionId'] ?? null) ? $params['assertionId'] : '';
$isDependentDuplicate = str_starts_with($route, 'account.dependent_duplicate.');
$localeQuery = '?lang=' . rawurlencode($translator->locale()->value());
$duplicateCasePath = $isDependentDuplicate
    ? '/account/dependents/' . rawurlencode($personId) . '/duplicate-cases/' . rawurlencode($caseId)
    : '/account/profile/duplicate-cases/' . rawurlencode($caseId);
$submission = $view->string('submission_id');
$csrf = $view->string('csrf_token');
$message = $view->string('global_message');
$pairing = $view->array('pairing');
$claim = $view->array('claim');
$case = $view->array('case');
$person = $view->array('person');
$firstPerson = $view->array('first_person');
$secondPerson = $view->array('second_person');
$value = static fn (array $data, string $key): string => is_string($data[$key] ?? null) || is_int($data[$key] ?? null) ? (string) $data[$key] : '';
$t = static fn (string $key, array $parameters = []): string => $escape->escapeText($translator->trans('identity_resolution.' . $key, $parameters));
$ta = static fn (string $key, array $parameters = []): string => $escape->escapeAttribute($translator->trans('identity_resolution.' . $key, $parameters));
$form = static function (string $action) use ($escape, $csrf, $submission): string {
    return '<input type="hidden" name="csrf_token" value="' . $escape->escapeAttribute($csrf) . '">'
        . '<input type="hidden" name="submission_id" value="' . $escape->escapeAttribute($submission) . '" data-qmdb-idempotency-key>';
};
?>
<section class="identity-form-region" data-qmdb-fragment-root data-qmdb-form-region data-qmdb-person-identity-resolution>
    <?php if ($message !== '') : ?>
        <p class="notice" role="status"><?= $escape->escapeText($message) ?></p>
    <?php endif; ?>

    <?php if ($route === 'account.profile_claim_pairing.form' || $route === 'account.profile_claim_pairing.create') : ?>
        <p><?= $t('pairing_intro') ?></p>
        <?php if ($view->string('pairing_code') !== '') : ?>
            <section aria-labelledby="pairing-code-heading" class="private-secret" data-qmdb-pairing-code-once>
                <h2 id="pairing-code-heading"><?= $t('pairing_code_heading') ?></h2>
                <p><code><?= $escape->escapeText($view->string('pairing_code')) ?></code></p>
                <p><?= $t('pairing_code_notice') ?></p>
            </section>
        <?php elseif ($pairing !== []) : ?>
            <dl class="detail-list"><dt><?= $t('pairing_status') ?></dt><dd><?= $escape->escapeText($value($pairing, 'status')) ?></dd><dt><?= $t('expires') ?></dt><dd><?= $escape->escapeText($value($pairing, 'expires_at')) ?></dd></dl>
            <form method="post" action="/account/profile/claim-pairing/<?= $escape->escapeAttribute($value($pairing, 'public_id')) ?>/revoke<?= $localeQuery ?>" data-qmdb-progressive-form>
                <?= $form('/account/profile/claim-pairing') ?>
                <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($pairing, 'version')) ?>">
                <button class="button danger" type="submit" data-qmdb-person-confirm data-qmdb-person-confirm-title="<?= $ta('revoke_pairing_title') ?>" data-qmdb-person-confirm-message="<?= $ta('revoke_pairing_message') ?>" data-qmdb-person-confirm-cancel="<?= $ta('cancel') ?>" data-qmdb-person-confirm-submit="<?= $ta('revoke_pairing') ?>"><?= $t('revoke_pairing') ?></button>
            </form>
        <?php else : ?>
            <form method="post" action="/account/profile/claim-pairing<?= $localeQuery ?>" data-qmdb-progressive-form autocomplete="off">
                <?= $form('/account/profile/claim-pairing') ?>
                <button class="button primary" type="submit"><?= $t('create_pairing') ?></button>
            </form>
        <?php endif; ?>

    <?php elseif (str_starts_with($route, 'account.dependent_profile_claim.')) : ?>
        <?php if ($route === 'account.dependent_profile_claim.authorization.form') : ?>
            <p><?= $t('dependent_authorization_intro') ?></p>
            <p><?= $t('dependent_authorization_notice') ?></p>
            <form method="post" action="/account/dependents/<?= $escape->escapeAttribute($personId) ?>/profile-claim/authorization<?= $localeQuery ?>" data-qmdb-progressive-form autocomplete="off">
                <?= $form('') ?>
                <div class="form-field"><label for="pairing_code"><?= $t('claimant_pairing_code') ?></label><input id="pairing_code" name="pairing_code" required autocomplete="off" spellcheck="false"></div>
                <button class="button primary" type="submit"><?= $t('authorize_claim') ?></button>
            </form>
        <?php else : ?>
            <p><?= $t('revoke_claim_intro') ?></p>
            <form method="post" action="/account/dependents/<?= $escape->escapeAttribute($personId) ?>/profile-claims/<?= $escape->escapeAttribute($claimId) ?>/revoke<?= $localeQuery ?>" data-qmdb-progressive-form>
                <?= $form('') ?><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($claim, 'version')) ?>"><button class="button danger" type="submit" data-qmdb-person-confirm data-qmdb-person-confirm-title="<?= $ta('revoke_claim_title') ?>" data-qmdb-person-confirm-message="<?= $ta('revoke_claim_message') ?>" data-qmdb-person-confirm-cancel="<?= $ta('cancel') ?>" data-qmdb-person-confirm-submit="<?= $ta('revoke_pending_claim') ?>"><?= $t('revoke_pending_claim') ?></button>
            </form>
        <?php endif; ?>

    <?php elseif (str_starts_with($route, 'account.profile_claim.')) : ?>
        <?php if ($route === 'account.profile_claim.detail' && $claim !== []) : ?>
            <dl class="detail-list"><dt><?= $t('person') ?></dt><dd><?= $escape->escapeText($value($claim, 'display_name')) ?></dd><dt><?= $t('status') ?></dt><dd><?= $escape->escapeText($value($claim, 'status')) ?></dd><dt><?= $t('authorization') ?></dt><dd><?= $escape->escapeText($value($claim, 'authorization_type')) ?></dd><dt><?= $t('expires') ?></dt><dd><?= $escape->escapeText($value($claim, 'expires_at')) ?></dd></dl>
            <?php if ($value($claim, 'status') === 'PENDING_ACCEPTANCE') : ?>
                <p><?= $t('claim_acceptance_notice') ?></p>
                <form method="post" action="/account/profile/claims/<?= $escape->escapeAttribute($claimId) ?>/accept<?= $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($claim, 'version')) ?>"><button class="button primary" type="submit"><?= $t('accept_claim') ?></button></form>
                <form method="post" action="/account/profile/claims/<?= $escape->escapeAttribute($claimId) ?>/decline<?= $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($claim, 'version')) ?>"><button class="button" type="submit" data-qmdb-person-confirm data-qmdb-person-confirm-title="<?= $ta('decline_claim_title') ?>" data-qmdb-person-confirm-message="<?= $ta('decline_claim_message') ?>" data-qmdb-person-confirm-cancel="<?= $ta('cancel') ?>" data-qmdb-person-confirm-submit="<?= $ta('decline_claim') ?>"><?= $t('decline_claim') ?></button></form>
            <?php endif; ?>
        <?php else : ?>
            <p><?= $t('private_claims_notice') ?></p><ul class="stack-list"><?php foreach ($view->list('claims') as $item) : ?><?php if (is_array($item)) : ?><li><span><?= $escape->escapeText($value($item, 'display_name')) ?> — <?= $escape->escapeText($value($item, 'status')) ?></span> <a href="/account/profile/claims/<?= $escape->escapeAttribute($value($item, 'public_id')) . $localeQuery ?>"><?= $t('view') ?></a></li><?php endif; ?><?php endforeach; ?></ul>
        <?php endif; ?>

    <?php elseif (str_starts_with($route, 'platform.profile_claim.')) : ?>
        <?php if ($route === 'platform.profile_claim.authorization.form') : ?>
            <p><?= $t('platform_authorization_intro') ?></p>
            <form method="post" action="/platform/people/profile-claims/authorization<?= $localeQuery ?>" data-qmdb-progressive-form autocomplete="off">
                <?= $form('') ?>
                <div class="form-field"><label for="registry_code"><?= $t('exact_registry_code') ?></label><input id="registry_code" name="registry_code" required autocomplete="off"></div>
                <div class="form-field"><label for="pairing_code"><?= $t('claimant_pairing_code') ?></label><input id="pairing_code" name="pairing_code" required autocomplete="off"></div>
                <div class="form-field"><label for="review_reference"><?= $t('review_reference') ?></label><input id="review_reference" name="review_reference" required></div>
                <div class="form-field"><label for="review_justification"><?= $t('confidential_review_justification') ?></label><textarea id="review_justification" name="review_justification" required></textarea></div>
                <button class="button primary" type="submit"><?= $t('authorize_claim') ?></button>
            </form>
        <?php else : ?>
            <p><?= $t('bounded_claim_queue_notice') ?></p><ul class="stack-list"><?php foreach ($view->list('claims') as $item) : ?><?php if (is_array($item)) : ?><li><?= $escape->escapeText($value($item, 'registry_code')) ?> — <?= $escape->escapeText($value($item, 'status')) ?></li><?php endif; ?><?php endforeach; ?></ul>
        <?php endif; ?>

    <?php elseif (str_starts_with($route, 'platform.profile_verification.')) : ?>
        <p><?= $t('verification_intro') ?></p>
        <?php if ($personId !== '') : ?>
            <dl class="detail-list"><dt><?= $t('registry_code') ?></dt><dd><?= $escape->escapeText($value($person, 'registry_code')) ?></dd><dt><?= $t('status') ?></dt><dd><?= $escape->escapeText($value($person, 'status')) ?></dd></dl>
            <form method="post" action="/platform/people/profile-verifications/<?= $escape->escapeAttribute($personId) ?><?= $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><div class="form-field"><label for="review_reference"><?= $t('review_reference') ?></label><input id="review_reference" name="review_reference" required></div><div class="form-field"><label for="review_justification"><?= $t('confidential_justification') ?></label><textarea id="review_justification" name="review_justification" required></textarea></div><button class="button primary" type="submit"><?= $t('record_review') ?></button></form>
        <?php else : ?>
            <p><?= $t('verification_no_person') ?></p>
        <?php endif; ?>

    <?php elseif (str_starts_with($route, 'platform.person_duplicate.')) : ?>
        <?php if ($case !== []) : ?>
            <dl class="detail-list"><dt><?= $t('case') ?></dt><dd><?= $escape->escapeText($value($case, 'public_id')) ?></dd><dt><?= $t('status') ?></dt><dd><?= $escape->escapeText($value($case, 'status')) ?></dd><dt><?= $t('first_person') ?></dt><dd><?= $escape->escapeText($value($firstPerson, 'registry_code')) ?> — <?= $escape->escapeText($value($firstPerson, 'display_name')) ?></dd><dt><?= $t('second_person') ?></dt><dd><?= $escape->escapeText($value($secondPerson, 'registry_code')) ?> — <?= $escape->escapeText($value($secondPerson, 'display_name')) ?></dd></dl>
            <?php if (str_contains($route, '.resolve')) : ?>
                <p><?= $t('resolution_intro') ?></p><form method="post" action="/platform/people/duplicates/<?= $escape->escapeAttribute($caseId) ?>/resolve<?= $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($case, 'version')) ?>"><div class="form-field"><label for="canonical_person_id"><?= $t('canonical_person_id') ?></label><input id="canonical_person_id" name="canonical_person_id" required></div><div class="form-field"><label for="duplicate_person_id"><?= $t('source_person_id') ?></label><input id="duplicate_person_id" name="duplicate_person_id" required></div><div class="form-field"><label for="review_reference"><?= $t('review_reference') ?></label><input id="review_reference" name="review_reference" required></div><div class="form-field"><label for="review_justification"><?= $t('confidential_justification') ?></label><textarea id="review_justification" name="review_justification" required></textarea></div><button class="button danger" type="submit" data-qmdb-person-confirm data-qmdb-person-confirm-title="<?= $ta('resolve_case_title') ?>" data-qmdb-person-confirm-message="<?= $ta('resolve_case_message') ?>" data-qmdb-person-confirm-cancel="<?= $ta('cancel') ?>" data-qmdb-person-confirm-submit="<?= $ta('resolve_case') ?>"><?= $t('resolve_case') ?></button></form>
            <?php else : ?>
                <form method="post" action="/platform/people/duplicates/<?= $escape->escapeAttribute($caseId) ?>/dismiss<?= $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($case, 'version')) ?>"><div class="form-field"><label for="review_reference"><?= $t('review_reference') ?></label><input id="review_reference" name="review_reference" required></div><div class="form-field"><label for="review_justification"><?= $t('confidential_justification') ?></label><textarea id="review_justification" name="review_justification" required></textarea></div><button class="button danger" type="submit" data-qmdb-person-confirm data-qmdb-person-confirm-title="<?= $ta('dismiss_case_title') ?>" data-qmdb-person-confirm-message="<?= $ta('dismiss_case_message') ?>" data-qmdb-person-confirm-cancel="<?= $ta('cancel') ?>" data-qmdb-person-confirm-submit="<?= $ta('dismiss_case') ?>"><?= $t('dismiss_case') ?></button></form>
            <?php endif; ?>
        <?php else : ?>
            <p><?= $t('duplicate_queue_notice') ?></p><ul class="stack-list"><?php foreach ($view->list('cases') as $item) : ?><?php if (is_array($item)) : ?><li><?= $escape->escapeText($value($item, 'public_id')) ?> — <?= $escape->escapeText($value($item, 'status')) ?> <a href="/platform/people/duplicates/<?= $escape->escapeAttribute($value($item, 'public_id')) . $localeQuery ?>"><?= $t('view') ?></a><?php if ($value($item, 'status') === 'READY_FOR_REVIEW') : ?><form method="post" action="/platform/people/duplicates/<?= $escape->escapeAttribute($value($item, 'public_id')) ?>/review<?= $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($item, 'version')) ?>"><button class="button" type="submit"><?= $t('start_review') ?></button></form><?php endif; ?></li><?php endif; ?><?php endforeach; ?></ul>
        <?php endif; ?>

    <?php else : ?>
        <?php if (str_contains($route, '.report.')) : ?>
            <p><?= $t('duplicate_report_intro') ?></p><form method="post" action="<?= $escape->escapeAttribute(str_starts_with($route, 'account.dependent') ? '/account/dependents/' . rawurlencode($personId) . '/duplicates/report' : '/account/profile/duplicates/report') . $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><?php if (!str_starts_with($route, 'account.dependent')) : ?><div class="form-field"><label for="managed_person_id"><?= $t('managed_person_id') ?></label><input id="managed_person_id" name="managed_person_id" required></div><?php endif; ?><div class="form-field"><label for="other_registry_code"><?= $t('other_registry_code') ?></label><input id="other_registry_code" name="other_registry_code" required></div><button class="button primary" type="submit"><?= $t('report_duplicate') ?></button></form>
        <?php elseif ($case !== []) : ?>
            <dl class="detail-list"><dt><?= $t('case') ?></dt><dd><?= $escape->escapeText($value($case, 'public_id')) ?></dd><dt><?= $t('status') ?></dt><dd><?= $escape->escapeText($value($case, 'status')) ?></dd></dl><?php foreach ($view->list('requirements') as $requirement) : ?><?php if (is_array($requirement) && $value($requirement, 'decision') === 'PENDING') : ?><p><?= $t('duplicate_consent_notice') ?></p><form method="post" action="<?= $escape->escapeAttribute($duplicateCasePath . '/consent') . $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><input type="hidden" name="requirement_id" value="<?= $escape->escapeAttribute($value($requirement, 'public_id')) ?>"><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($requirement, 'version')) ?>"><button class="button primary" type="submit"><?= $t('approve_consent') ?></button></form><form method="post" action="<?= $escape->escapeAttribute($duplicateCasePath . '/decline') . $localeQuery ?>" data-qmdb-progressive-form><?= $form('') ?><input type="hidden" name="requirement_id" value="<?= $escape->escapeAttribute($value($requirement, 'public_id')) ?>"><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute($value($requirement, 'version')) ?>"><button class="button danger" type="submit" data-qmdb-person-confirm data-qmdb-person-confirm-title="<?= $ta('decline_consent_title') ?>" data-qmdb-person-confirm-message="<?= $ta('decline_consent_message') ?>" data-qmdb-person-confirm-cancel="<?= $ta('cancel') ?>" data-qmdb-person-confirm-submit="<?= $ta('decline_consent') ?>"><?= $t('decline_consent') ?></button></form><?php endif; ?><?php endforeach; ?>
        <?php else : ?>
            <p><?= $t('private_case_list_notice') ?></p><p><a class="button primary" href="/account/profile/duplicates/report<?= $localeQuery ?>"><?= $t('report_duplicate') ?></a></p><ul class="stack-list"><?php foreach ($view->list('cases') as $item) : ?><?php if (is_array($item)) : ?><li><?= $escape->escapeText($value($item, 'public_id')) ?> — <?= $escape->escapeText($value($item, 'status')) ?> <a href="/account/profile/duplicate-cases/<?= $escape->escapeAttribute($value($item, 'public_id')) . $localeQuery ?>"><?= $t('view') ?></a></li><?php endif; ?><?php endforeach; ?></ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
