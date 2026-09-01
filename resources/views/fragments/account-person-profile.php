<?php

declare(strict_types=1);

$route = $view->string('route_name');
$profile = $view->array('profile');
$roles = $view->list('roles');
$progress = $view->array('memorizer_progress');
$guardianship = $view->array('guardianship');
$params = $view->array('route_parameters');
$personId = is_string($params['person_id'] ?? null) ? $params['person_id'] : '';
$isCreate = $route === 'account.person_profile.create.form';
$isEdit = in_array($route, ['account.person_profile.edit.form', 'account.person_profile.dependent.edit.form'], true);
$isDependentCreate = $route === 'account.person_profile.dependent.create.form';
$isProgress = in_array($route, ['account.person_profile.memorizer_progress.form', 'account.person_profile.dependent.memorizer_progress.form'], true);
$isDependents = $route === 'account.person_profile.dependents.index';
$isDependentView = $route === 'account.person_profile.dependent.view';
$isRevoke = $route === 'account.person_profile.guardianship.revoke.form';
$isRoleDeactivate = $route === 'account.person_profile.role.deactivate.form';
$formAction = match (true) {
    $isCreate => '/account/profile',
    $isEdit && $personId !== '' => '/account/dependents/' . rawurlencode($personId) . '/update',
    $isEdit => '/account/profile/update',
    $isDependentCreate => '/account/dependents',
    $isProgress && $personId !== '' => '/account/dependents/' . rawurlencode($personId) . '/memorizer-progress',
    $isProgress => '/account/profile/memorizer-progress',
    $isRevoke => '/account/dependents/' . rawurlencode($personId) . '/guardianship/revoke',
    $isRoleDeactivate => '/account/profile/roles/' . rawurlencode(is_string($params['role_type'] ?? null) ? $params['role_type'] : '') . '/deactivate',
    default => '',
};
$value = static fn (string $key): string => is_string($profile[$key] ?? null) ? $profile[$key] : '';
$selectedSex = $value('sex_classification') ?: 'NOT_RECORDED';
$roleByType = [];
$levelOneAreas = $view->list('geography_level_one_areas');
foreach ($roles as $role) {
    if (is_array($role) && is_string($role['role_type'] ?? null)) {
        $roleByType[$role['role_type']] = $role;
    }
}
?>
<section class="identity-form-region" data-qmdb-fragment-root data-qmdb-form-region>
    <?= $renderer->render('components.form-error-summary', new \Qmdb\Shared\Presentation\View\ViewData([
        'errors' => $view->array('errors'),
        'global_error' => $view->string('global_error'),
    ]), $translator)->trustedHtml() ?>

    <?php if ($isCreate || $isEdit || $isDependentCreate) : ?>
        <form method="post" action="<?= $escape->escapeAttribute($formAction) ?>" data-qmdb-progressive-form novalidate>
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
            <input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key>
            <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) ($profile['version'] ?? 0)) ?>">
            <input type="hidden" name="expected_name_version" value="<?= $escape->escapeAttribute((string) ($profile['name_version'] ?? 0)) ?>">
            <div class="form-field"><label for="display_name">Primary display name <span aria-hidden="true">*</span></label><input id="display_name" name="display_name" required maxlength="200" autocomplete="name" value="<?= $escape->escapeAttribute($value('display_name')) ?>"></div>
            <div class="form-field"><label for="given_name">Given name</label><input id="given_name" name="given_name" maxlength="200" autocomplete="given-name" value="<?= $escape->escapeAttribute($value('given_name')) ?>"></div>
            <div class="form-field"><label for="middle_names">Middle names</label><input id="middle_names" name="middle_names" maxlength="200" value="<?= $escape->escapeAttribute($value('middle_names')) ?>"></div>
            <div class="form-field"><label for="family_name">Family name</label><input id="family_name" name="family_name" maxlength="200" autocomplete="family-name" value="<?= $escape->escapeAttribute($value('family_name')) ?>"></div>
            <div class="form-field"><label for="preferred_name">Preferred name <span class="form-help">(optional)</span></label><input id="preferred_name" name="preferred_name" maxlength="200" value="<?= $escape->escapeAttribute($value('preferred_name')) ?>"></div>
            <div class="form-field"><label for="arabic_name">Arabic name <span class="form-help">(optional)</span></label><input id="arabic_name" name="arabic_name" maxlength="200" dir="auto" value="<?= $escape->escapeAttribute($value('arabic_name')) ?>"></div>
            <div class="form-field"><label for="birth_date">Birth date <span class="form-help">(optional)</span></label><input id="birth_date" name="birth_date" type="date" autocomplete="bday" value="<?= $escape->escapeAttribute($value('birth_date')) ?>"><p class="form-help">Age policy is calculated only on the server.</p></div>
            <div class="form-field"><label for="sex_classification">Sex classification <span class="form-help">(optional)</span></label><select id="sex_classification" name="sex_classification"><?php foreach (['NOT_RECORDED' => 'Not recorded', 'MALE' => 'Male', 'FEMALE' => 'Female'] as $sex => $label) :
                ?><option value="<?= $sex ?>"<?= $sex === $selectedSex ? ' selected' : '' ?>><?= $label ?></option><?php
                                                                                                                                                                                                  endforeach; ?></select></div>
            <input type="hidden" name="nationality_country_public_id" value="<?= $escape->escapeAttribute($view->string('geography_country_public_id')) ?>">
            <p class="form-help"><?= $escape->escapeText($translator->trans('person_profile.nationality_note')) ?></p>
            <fieldset><legend><?= $escape->escapeText($translator->trans('person_profile.origin')) ?> <span class="form-help">(optional)</span></legend><div class="form-field"><label for="origin_level_one_area_public_id"><?= $escape->escapeText($translator->trans('geography.choose_level_one')) ?></label><select id="origin_level_one_area_public_id" name="origin_level_one_area_public_id" data-qmdb-geography-level-one data-qmdb-geography-child-target="#origin-level-two-region" data-qmdb-geography-child-field="origin_level_two_area_public_id" data-qmdb-geography-selected-child="<?= $escape->escapeAttribute($value('origin_level_two_area_public_id')) ?>" data-qmdb-geography-loading-message="<?= $escape->escapeAttribute($translator->trans('geography.loading_areas')) ?>" data-qmdb-geography-loaded-message="<?= $escape->escapeAttribute($translator->trans('geography.areas_loaded')) ?>" data-qmdb-geography-error-message="<?= $escape->escapeAttribute($translator->trans('geography.unable_to_load_areas')) ?>" data-qmdb-geography-cleared-message="<?= $escape->escapeAttribute($translator->trans('geography.no_area_selected')) ?>"><option value=""><?= $escape->escapeText($translator->trans('person_profile.choose_level_one')) ?></option><?php foreach ($levelOneAreas as $area) :
                ?><?php if (is_array($area) && is_string($area['public_id'] ?? null) && is_string($area['official_name'] ?? null)) :
    ?><option value="<?= $escape->escapeAttribute($area['public_id']) ?>"<?= $area['public_id'] === $value('origin_level_one_area_public_id') ? ' selected' : '' ?>><?= $escape->escapeText($area['official_name']) ?></option><?php
                endif; ?><?php
                              endforeach; ?></select></div><section id="origin-level-two-region" data-qmdb-geography-child-region aria-live="polite" aria-busy="false"><p class="form-help"><?= $escape->escapeText($translator->trans('person_profile.choose_child')) ?></p></section></fieldset>
            <fieldset><legend><?= $escape->escapeText($translator->trans('person_profile.residence')) ?> <span class="form-help">(optional)</span></legend><div class="form-field"><label for="residence_level_one_area_public_id"><?= $escape->escapeText($translator->trans('geography.choose_level_one')) ?></label><select id="residence_level_one_area_public_id" name="residence_level_one_area_public_id" data-qmdb-geography-level-one data-qmdb-geography-child-target="#residence-level-two-region" data-qmdb-geography-child-field="residence_level_two_area_public_id" data-qmdb-geography-selected-child="<?= $escape->escapeAttribute($value('residence_level_two_area_public_id')) ?>" data-qmdb-geography-loading-message="<?= $escape->escapeAttribute($translator->trans('geography.loading_areas')) ?>" data-qmdb-geography-loaded-message="<?= $escape->escapeAttribute($translator->trans('geography.areas_loaded')) ?>" data-qmdb-geography-error-message="<?= $escape->escapeAttribute($translator->trans('geography.unable_to_load_areas')) ?>" data-qmdb-geography-cleared-message="<?= $escape->escapeAttribute($translator->trans('geography.no_area_selected')) ?>"><option value=""><?= $escape->escapeText($translator->trans('person_profile.choose_level_one')) ?></option><?php foreach ($levelOneAreas as $area) :
                ?><?php if (is_array($area) && is_string($area['public_id'] ?? null) && is_string($area['official_name'] ?? null)) :
    ?><option value="<?= $escape->escapeAttribute($area['public_id']) ?>"<?= $area['public_id'] === $value('residence_level_one_area_public_id') ? ' selected' : '' ?>><?= $escape->escapeText($area['official_name']) ?></option><?php
                endif; ?><?php
                              endforeach; ?></select></div><section id="residence-level-two-region" data-qmdb-geography-child-region aria-live="polite" aria-busy="false"><p class="form-help"><?= $escape->escapeText($translator->trans('person_profile.choose_child')) ?></p></section></fieldset>
            <?php if ($isDependentCreate) :
                ?><div class="form-field"><label for="relationship_type">Relationship type</label><select id="relationship_type" name="relationship_type"><?php foreach (['PARENT' => 'Parent', 'LEGAL_GUARDIAN' => 'Legal guardian', 'CAREGIVER' => 'Caregiver', 'OTHER' => 'Other'] as $type => $label) :
    ?><option value="<?= $type ?>"><?= $label ?></option><?php
                endforeach; ?></select></div><p class="form-help">QMDB records a self-declared profile-management relationship. It does not certify legal guardianship.</p><?php
            endif; ?>
            <?php if ($isCreate || $isDependentCreate) :
                ?><fieldset><legend>Participation roles <span class="form-help">(optional)</span></legend><?php foreach (['MEMORIZER' => 'Memorizer', 'RECITER' => 'Reciter', 'COMPETITOR' => 'Competitor', 'GUARDIAN' => 'Guardian'] as $type => $label) :
    ?><label><input type="checkbox" name="role_types[]" value="<?= $type ?>"> <?= $label ?></label><?php
                endforeach; ?></fieldset><?php
            endif; ?>
            <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('person_profile.save')) ?></button>
        </form>
    <?php elseif ($isProgress) : ?>
        <form method="post" action="<?= $escape->escapeAttribute($formAction) ?>" data-qmdb-progressive-form novalidate><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_progress')) ?>"><input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) ($progress['version'] ?? 0)) ?>"><div class="form-field"><label for="memorized_juz_count">Memorized Juz count</label><input id="memorized_juz_count" name="memorized_juz_count" type="number" min="0" max="30" required value="<?= $escape->escapeAttribute((string) ($progress['memorized_juz_count'] ?? '0')) ?>"></div><div class="form-field"><label for="progress_status">Progress status</label><select id="progress_status" name="progress_status"><?php foreach (['NOT_RECORDED' => 'Not recorded', 'IN_PROGRESS' => 'In progress', 'COMPLETE' => 'Complete', 'MAINTENANCE' => 'Maintenance'] as $status => $label) :
            ?><option value="<?= $status ?>"<?= $status === ($progress['progress_status'] ?? '') ? ' selected' : '' ?>><?= $label ?></option><?php
                                    endforeach; ?></select></div><div class="form-field"><label for="completed_on">Completion date <span class="form-help">(complete or maintenance only)</span></label><input id="completed_on" name="completed_on" type="date" value="<?= $escape->escapeAttribute(is_string($progress['completed_on'] ?? null) ? $progress['completed_on'] : '') ?>"></div><button class="button primary" type="submit">Save Memorizer progress</button></form>
    <?php elseif ($isRoleDeactivate) : ?>
        <?php $roleType = is_string($params['role_type'] ?? null) ? $params['role_type'] : '';
        $role = $roleByType[$roleType] ?? []; ?>
        <form method="post" action="<?= $escape->escapeAttribute($formAction) ?>" data-qmdb-progressive-form><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_role_deactivate')) ?>"><input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) ($role['version'] ?? 0)) ?>"><p>Guardian deactivation is unavailable while active dependent relationships remain.</p><button class="button danger" type="submit">Confirm role deactivation</button></form>
    <?php elseif ($isRevoke) : ?>
        <form method="post" action="<?= $escape->escapeAttribute($formAction) ?>" data-qmdb-progressive-form><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_guardianship_revoke')) ?>"><input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key><input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) ($guardianship['version'] ?? 0)) ?>"><p>QMDB records a self-declared profile-management relationship. It does not certify legal guardianship. A minor must retain another active guardian.</p><button class="button danger" type="submit">Revoke relationship</button></form>
    <?php elseif ($isDependents) : ?>
        <p><a class="button primary" href="/account/dependents/create">Create dependent profile</a></p><ul class="stack-list"><?php foreach ($view->list('dependents') as $dependent) :
            ?><?php if (is_array($dependent) && is_string($dependent['public_id'] ?? null) && is_string($dependent['display_name'] ?? null)) :
    ?><li><span><?= $escape->escapeText($dependent['display_name']) ?></span> <a href="/account/dependents/<?= $escape->escapeAttribute($dependent['public_id']) ?>">View</a> <a href="/account/dependents/<?= $escape->escapeAttribute($dependent['public_id']) ?>/edit">Edit</a></li><?php
            endif; ?><?php
                                                                                                                              endforeach; ?></ul>
    <?php else : ?>
        <dl class="detail-list"><dt>Registry code</dt><dd><?= $escape->escapeText($value('registry_code')) ?></dd><dt>Primary name</dt><dd><?= $escape->escapeText($value('display_name')) ?></dd><dt>Profile status</dt><dd><?= $escape->escapeText($value('status')) ?></dd><?php if ($value('birth_date') !== '') :
            ?><dt>Birth date</dt><dd><?= $escape->escapeText($value('birth_date')) ?></dd><?php
                                                          endif; ?></dl>
        <p><a class="button primary" href="<?= $personId === '' ? '/account/profile/edit' : '/account/dependents/' . rawurlencode($personId) . '/edit' ?>">Edit private profile</a><?php if (!$isDependentView) :
            ?> <a class="button" href="/account/dependents">My dependents</a> <a class="button" href="/account/profile/memorizer-progress">Memorizer progress</a><?php
                                           else :
                                                ?> <a class="button" href="/account/dependents/<?= $escape->escapeAttribute($personId) ?>/memorizer-progress">Memorizer progress</a> <a class="button danger" href="/account/dependents/<?= $escape->escapeAttribute($personId) ?>/guardianship/revoke">Revoke relationship</a><?php
                                           endif; ?></p>
        <h2>Participation roles</h2><ul class="stack-list"><?php foreach (['MEMORIZER' => 'Memorizer', 'RECITER' => 'Reciter', 'COMPETITOR' => 'Competitor', 'GUARDIAN' => 'Guardian'] as $type => $label) :
            ?><?php $role = $roleByType[$type] ?? null; ?><li><?= $escape->escapeText($label) ?>: <?= $escape->escapeText(is_array($role) && is_string($role['status'] ?? null) ? $role['status'] : 'INACTIVE') ?><?php if (!$isDependentView && (!is_array($role) || $role['status'] !== 'ACTIVE')) :
    ?><form method="post" action="/account/profile/roles/<?= $escape->escapeAttribute($type) ?>/activate" data-qmdb-progressive-form><input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_role_activate')) ?>"><input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key><button class="button" type="submit">Activate</button></form><?php
            elseif (!$isDependentView && is_array($role)) :
                ?> <a href="/account/profile/roles/<?= $escape->escapeAttribute($type) ?>/deactivate">Deactivate</a><?php
            endif; ?></li><?php
                                                           endforeach; ?></ul>
    <?php endif; ?>
</section>
