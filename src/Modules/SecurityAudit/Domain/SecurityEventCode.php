<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

use InvalidArgumentException;

enum SecurityEventCode: string
{
    case ACCOUNT_SUSPENDED = 'identity.account.suspended';
    case ACCOUNT_REACTIVATED = 'identity.account.reactivated';
    case PASSWORD_RESET_COMPLETED = 'identity.password_reset.completed';
    case MFA_ENABLED = 'identity.mfa.enabled';
    case MFA_DISABLED = 'identity.mfa.disabled';
    case TOTP_ADDED = 'identity.totp.added';
    case TOTP_REMOVED = 'identity.totp.removed';
    case PASSKEY_ADDED = 'identity.passkey.added';
    case PASSKEY_REMOVED = 'identity.passkey.removed';
    case PASSKEY_SUSPENDED = 'identity.passkey.suspended';
    case RECOVERY_CODE_USED = 'identity.recovery_code.used';
    case RECOVERY_CODES_REGENERATED = 'identity.recovery_codes.regenerated';
    case SESSION_REMOTE_REVOKED = 'identity.session.remote_revoked';
    case DEVICE_REVOKED = 'identity.device.revoked';
    case PLATFORM_ROLE_ASSIGNED = 'authorization.platform_role.assigned';
    case PLATFORM_ROLE_REVOKED = 'authorization.platform_role.revoked';
    case WORKSPACE_ROLE_ASSIGNED = 'authorization.workspace_role.assigned';
    case WORKSPACE_ROLE_REVOKED = 'authorization.workspace_role.revoked';
    case TEMPORARY_ACTIVATED = 'privileged_access.temporary.activated';
    case TEMPORARY_ENDED = 'privileged_access.temporary.ended';
    case TEMPORARY_REVOKED = 'privileged_access.temporary.revoked';
    case TEMPORARY_EXPIRED = 'privileged_access.temporary.expired';
    case SUPPORT_ACTIVATED = 'privileged_access.support.activated';
    case SUPPORT_ENDED = 'privileged_access.support.ended';
    case SUPPORT_REVOKED = 'privileged_access.support.revoked';
    case SUPPORT_EXPIRED = 'privileged_access.support.expired';
    case SUPPORT_REVIEW_COMPLETED = 'privileged_access.support.review_completed';
    case BREAK_GLASS_ACTIVATED = 'privileged_access.break_glass.activated';
    case BREAK_GLASS_ENDED = 'privileged_access.break_glass.ended';
    case BREAK_GLASS_REVOKED = 'privileged_access.break_glass.revoked';
    case BREAK_GLASS_EXPIRED = 'privileged_access.break_glass.expired';
    case BREAK_GLASS_REVIEW_COMPLETED = 'privileged_access.break_glass.review_completed';
    case PERSON_PROFILE_CREATED = 'people.profile.created';
    case PERSON_PROFILE_UPDATED = 'people.profile.updated';
    case PERSON_ROLE_ACTIVATED = 'people.role.activated';
    case PERSON_ROLE_DEACTIVATED = 'people.role.deactivated';
    case MEMORIZER_PROGRESS_UPDATED = 'people.memorizer_progress.updated';
    case DEPENDENT_PROFILE_CREATED = 'people.dependent.created';
    case GUARDIANSHIP_REVOKED = 'people.guardianship.revoked';
    case ORGANIZATION_CREATED = 'organizations.organization.created';
    case ORGANIZATION_UPDATED = 'organizations.organization.updated';
    case ORGANIZATION_RETIRED = 'organizations.organization.retired';
    case ORGANIZATION_UNIT_CREATED = 'organizations.unit.created';
    case ORGANIZATION_UNIT_UPDATED = 'organizations.unit.updated';
    case ORGANIZATION_UNIT_RETIRED = 'organizations.unit.retired';
    case ORGANIZATION_AFFILIATION_REQUESTED = 'organizations.affiliation.requested';
    case ORGANIZATION_AFFILIATION_ACCEPTED = 'organizations.affiliation.accepted';
    case ORGANIZATION_AFFILIATION_DECLINED = 'organizations.affiliation.declined';
    case ORGANIZATION_AFFILIATION_WITHDRAWN = 'organizations.affiliation.withdrawn';
    case ORGANIZATION_AFFILIATION_EXPIRED = 'organizations.affiliation.expired';
    case ORGANIZATION_AFFILIATION_SUSPENDED = 'organizations.affiliation.suspended';
    case ORGANIZATION_AFFILIATION_RESUMED = 'organizations.affiliation.resumed';
    case ORGANIZATION_AFFILIATION_ENDED = 'organizations.affiliation.ended';
    case ORGANIZATION_AFFILIATION_LEFT = 'organizations.affiliation.left';
    case ORGANIZATION_AFFILIATION_ASSIGNMENTS_UPDATED = 'organizations.affiliation.assignments_updated';
    case ORGANIZATION_AFFILIATION_LEADERSHIP_ASSIGNED = 'organizations.affiliation.leadership_assigned';
    case ORGANIZATION_AFFILIATION_LEADERSHIP_REMOVED = 'organizations.affiliation.leadership_removed';
    case PROFILE_CLAIM_PAIRING_CREATED = 'people.claim_pairing.created';
    case PROFILE_CLAIM_PAIRING_REVOKED = 'people.claim_pairing.revoked';
    case PROFILE_CLAIM_PAIRING_EXHAUSTED = 'people.claim_pairing.exhausted';
    case PROFILE_CLAIM_AUTHORIZED = 'people.profile_claim.authorized';
    case PROFILE_CLAIM_ACCEPTED = 'people.profile_claim.accepted';
    case PROFILE_CLAIM_DECLINED = 'people.profile_claim.declined';
    case PROFILE_CLAIM_REVOKED = 'people.profile_claim.revoked';
    case PROFILE_CLAIM_EXPIRED = 'people.profile_claim.expired';
    case PROFILE_VERIFICATION_RECORDED = 'people.profile_verification.recorded';
    case PROFILE_VERIFICATION_REVOKED = 'people.profile_verification.revoked';
    case PERSON_DUPLICATE_REPORTED = 'people.duplicate.reported';
    case PERSON_DUPLICATE_CONSENT_APPROVED = 'people.duplicate.consent_approved';
    case PERSON_DUPLICATE_CONSENT_DECLINED = 'people.duplicate.consent_declined';
    case PERSON_DUPLICATE_DISMISSED = 'people.duplicate.dismissed';
    case PERSON_DUPLICATE_BLOCKED = 'people.duplicate.blocked';
    case PERSON_DUPLICATE_RESOLVED = 'people.duplicate.resolved';
    case PERSON_CANONICALIZED = 'people.person.canonicalized';
    case QURAN_RELEASE_STAGED = 'quran.release.staged';
    case QURAN_RELEASE_VALIDATED = 'quran.release.validated';
    case QURAN_RELEASE_APPROVED = 'quran.release.approved';
    case QURAN_RELEASE_ACTIVATED = 'quran.release.activated';
    case QURAN_RELEASE_SUPERSEDED = 'quran.release.superseded';
    case QURAN_RELEASE_REJECTED = 'quran.release.rejected';
    case QURAN_SEARCH_CORPUS_IMPORTED = 'quran.search_corpus.imported';
    case QURAN_SEARCH_CORPUS_VALIDATED = 'quran.search_corpus.validated';
    case QURAN_SEARCH_CORPUS_ACTIVATED = 'quran.search_corpus.activated';
    case QURAN_SEARCH_CORPUS_RETIRED = 'quran.search_corpus.retired';
    case QURAN_PUBLIC_REFERENCE_INTEGRITY_VERIFIED = 'quran.public_reference.integrity_verified';
    case COMPETITION_SCORE_SHEET_LOCKED = 'competition.score_sheet.locked';
    case COMPETITION_SCORE_SHEET_SUPERSEDED = 'competition.score_sheet.superseded';
    case COMPETITION_RESULT_CALCULATED = 'competition.result.calculated';
    case COMPETITION_RESULT_VERIFIED = 'competition.result.verified';
    case COMPETITION_RESULT_PUBLISHED = 'competition.result.published';
    case COMPETITION_RESULT_SUPERSEDED = 'competition.result.superseded';
    case COMPETITION_PARTICIPANT_DISQUALIFIED = 'competition.participant.disqualified';
    case COMPETITION_APPEAL_SUBMITTED = 'competition.appeal.submitted';
    case COMPETITION_APPEAL_DECIDED = 'competition.appeal.decided';
    case COMPETITION_LIVE_SESSION_OPENED = 'competition.live_session.opened';
    case COMPETITION_LIVE_SESSION_PAUSED = 'competition.live_session.paused';
    case COMPETITION_LIVE_SESSION_RESUMED = 'competition.live_session.resumed';
    case COMPETITION_LIVE_SESSION_RECOVERY_STARTED = 'competition.live_session.recovery_started';
    case COMPETITION_LIVE_SESSION_RECOVERED = 'competition.live_session.recovered';
    case COMPETITION_LIVE_SESSION_CLOSED = 'competition.live_session.closed';
    case COMPETITION_LIVE_SESSION_CANCELLED = 'competition.live_session.cancelled';
    case COMPETITION_LIVE_PARTICIPANT_CHECKED_IN = 'competition.live_participant.checked_in';
    case COMPETITION_LIVE_PARTICIPANT_CALLED = 'competition.live_participant.called';
    case COMPETITION_LIVE_PARTICIPANT_READY = 'competition.live_participant.ready';
    case COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_STARTED = 'competition.live_participant.performance_started';
    case COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_INTERRUPTED = 'competition.live_participant.performance_interrupted';
    case COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_RESUMED = 'competition.live_participant.performance_resumed';
    case COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_COMPLETED = 'competition.live_participant.performance_completed';
    case COMPETITION_LIVE_PARTICIPANT_ABSENT = 'competition.live_participant.absent';
    case COMPETITION_LIVE_PARTICIPANT_WITHDRAWN = 'competition.live_participant.withdrawn';
    case COMPETITION_RESULT_PUBLICATION_PREPARED = 'competition.result_publication.prepared';
    case COMPETITION_RESULT_PUBLICATION_PROVISIONALLY_PUBLISHED = 'competition.result_publication.provisionally_published';
    case COMPETITION_RESULT_PUBLICATION_HELD = 'competition.result_publication.held';
    case COMPETITION_RESULT_PUBLICATION_FINALIZED = 'competition.result_publication.finalized';
    case COMPETITION_RESULT_PUBLICATION_WITHDRAWN = 'competition.result_publication.withdrawn';
    case CERTIFICATE_TEMPLATE_ACTIVATED = 'certificate.template.activated';
    case CERTIFICATE_SIGNING_KEY_ACTIVATED = 'certificate.signing_key.activated';
    case CERTIFICATE_SIGNING_KEY_REVOKED = 'certificate.signing_key.revoked';
    case CERTIFICATE_PREPARED = 'certificate.prepared';
    case CERTIFICATE_ISSUED = 'certificate.issued';
    case CERTIFICATE_REVOKED = 'certificate.revoked';
    case CERTIFICATE_SUPERSEDED = 'certificate.superseded';
    case RECORD_PASSPORT_SHARED = 'record_passport.shared';
    case RECORD_PASSPORT_SHARE_REVOKED = 'record_passport.share_revoked';
    case TRUSTED_ARCHIVE_SEALED = 'trusted_archive.sealed';
    case TRUSTED_ARCHIVE_HOLD_PLACED = 'trusted_archive.hold_placed';
    case LEGACY_RECORD_IMPORT_APPROVED = 'legacy_record_import.approved';
    case MEDIA_ASSET_UPLOADED = 'media.asset.uploaded';
    case MEDIA_GOVERNANCE_CHANGED = 'media.governance.changed';

    public function severity(): SecurityEventSeverity
    {
        if ($this === self::ACCOUNT_SUSPENDED || $this === self::BREAK_GLASS_ACTIVATED) {
            return SecurityEventSeverity::CRITICAL;
        }

        return SecurityEventSeverity::WARNING;
    }

    public static function fromTrustedString(string $value): self
    {
        if (preg_match('/\\A[a-z][a-z0-9_]*(?:\\.[a-z][a-z0-9_]*)+\\z/', $value) !== 1) {
            throw new InvalidArgumentException('Security audit event code is invalid.');
        }

        return self::from($value);
    }
}
