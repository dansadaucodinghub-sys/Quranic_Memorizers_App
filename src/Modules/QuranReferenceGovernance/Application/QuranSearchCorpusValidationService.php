<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

/** Recomputes persisted integrity evidence; it never changes corpus text or lifecycle state. */
final readonly class QuranSearchCorpusValidationService
{
    public function __construct(
        private QuranSearchCorpusIntegrityRepository $corpora,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{replayed:bool,corpus_public_id:string,version:int} */
    public function validate(QuranSearchCorpusValidationCommand $command): array
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode('platform.quran_search_corpus.validate'),
            new PlatformAuthorizationScope(),
        ));
        $policy = new IdentityRateLimitPolicy(60, 10, 60);
        if (
            !$this->rateLimits->consume([
            new IdentityRateLimitAttempt(IdentityRateLimitScope::QURAN_GOVERNANCE_MUTATION_ACCOUNT, $this->fingerprints->generate('quran-search-corpus-validation-account', (string) $command->actor->accountInternalId), $policy),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::QURAN_GOVERNANCE_MUTATION_PEER, $this->fingerprints->generate('quran-search-corpus-validation-session', (string) $command->actor->sessionInternalId), $policy),
            ], $this->clock->now())->allowed
        ) {
            throw new \DomainException('Qur’an corpus validation is temporarily unavailable.');
        }

        return $this->transactions->transactional(fn (): array => $this->inTransaction($command), TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(1, 0, 0)));
    }

    /** @return array{replayed:bool,corpus_public_id:string,version:int} */
    private function inTransaction(QuranSearchCorpusValidationCommand $command): array
    {
        $fingerprint = $this->fingerprints->generate('quran-search-corpus-validation', implode("\0", [(string) $command->actor->accountInternalId, $command->releasePublicId->toString(), (string) $command->expectedCorpusVersion]));
        $completed = $this->corpora->completed($command->submissionId, $fingerprint->toBinary());
        if ($completed !== null) {
            return ['replayed' => true, 'corpus_public_id' => (string) $completed['corpus_public_id'], 'version' => (int) $completed['expected_version']];
        }
        $corpus = $this->corpora->lockForValidation($command->releasePublicId);
        if ($corpus === null || (int) $corpus['version'] !== $command->expectedCorpusVersion || (string) $corpus['status'] !== 'ACTIVE') {
            throw new \DomainException('Qur’an corpus validation is stale or unavailable.');
        }
        $rows = $this->corpora->alignedRows((int) $corpus['id']);
        if (count($rows) !== (int) $corpus['ayah_count']) {
            throw new \DomainException('Qur’an corpus row count differs from the approved corpus.');
        }
        $records = [];
        $ayahs = [];
        foreach ($rows as $row) {
            $text = $this->string($row, 'simple_clean_text');
            $surah = (int) $row['surah_number'];
            $ayah = (int) $row['ayah_number'];
            $hash = QuranSearchHasher::row($surah, $ayah, $text);
            if (!hash_equals($hash, strtolower($this->string($row, 'text_sha256')))) {
                throw new \DomainException('Qur’an corpus row checksum differs.');
            }
            $records[] = ['surah_number' => $surah, 'ayah_number' => $ayah, 'text' => $text, 'byte_size' => strlen($text), 'sha256' => $hash];
            $ayahs[] = ['public_id' => $this->string($row, 'ayah_public_id'), 'surah_number' => $surah, 'ayah_number' => $ayah, 'global_ayah_ordinal' => (int) $row['global_ayah_ordinal'], 'text_sha256' => strtolower($this->string($row, 'canonical_text_sha256'))];
        }
        $corpusHash = QuranSearchHasher::corpus($records);
        $alignmentHash = QuranSearchHasher::alignment($this->string($corpus, 'release_public_id'), $this->string($corpus, 'public_id'), $ayahs, $records);
        if (!hash_equals($this->string($corpus, 'simple_text_sha256'), $corpusHash) || !hash_equals($this->string($corpus, 'alignment_sha256'), $alignmentHash)) {
            throw new \DomainException('Qur’an corpus aggregate checksum differs.');
        }
        $grant = $this->stepUp->consumeWithGrant($command->actor, StepUpAction::QURAN_SEARCH_CORPUS_VALIDATE);
        $now = $this->clock->now();
        $validationPublicId = $this->corpora->appendValidation((int) $corpus['id'], $command->actor->accountInternalId, $alignmentHash, $now);
        $audit = $this->audit->platform(SecurityEventCode::QURAN_SEARCH_CORPUS_VALIDATED, SecurityEventSubjectKind::QURAN_SEARCH_CORPUS, $this->string($corpus, 'public_id'), $command->actor->accountId->toString(), $now, ['canonical_release_public_id' => $this->string($corpus, 'release_public_id'), 'search_source_code' => $this->string($corpus, 'source_code'), 'corpus_version' => $this->string($corpus, 'corpus_version'), 'corpus_row_count' => count($records), 'corpus_sha256' => $corpusHash, 'alignment_sha256' => $alignmentHash, 'normalization_policy_version' => $this->string($corpus, 'normalization_policy_version'), 'validation_result' => 'PASS']);
        $this->corpora->recordOperation($command->submissionId, $fingerprint->toBinary(), (int) $corpus['id'], $command->actor, $command->expectedCorpusVersion, $validationPublicId, $audit->eventPublicId, $grant->internalId, $now);

        return ['replayed' => false, 'corpus_public_id' => $this->string($corpus, 'public_id'), 'version' => (int) $corpus['version']];
    }

    /** @param array<string, int|string> $row */
    private function string(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \DomainException('Qur’an corpus validation data is invalid.');
        }

        return $value;
    }
}
