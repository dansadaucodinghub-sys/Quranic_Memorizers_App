<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicy;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicyStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPreferredMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransaction;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionSecretHash;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\EncryptedTotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\PasskeyCredential;
use Qmdb\Modules\IdentityMultiFactor\Domain\PasskeyCredentialStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\RecoveryCodeHash;
use Qmdb\Modules\IdentityMultiFactor\Domain\RecoveryCodeSet;
use Qmdb\Modules\IdentityMultiFactor\Domain\RecoveryCodeSetStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AuthenticationTransactionRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\PasskeyCredentialRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\StepUpGrantRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\TotpAuthenticatorRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\WebAuthnCeremonyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\WebAuthnUserHandleRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpGrant;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpGrantStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpAuthenticator;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpAuthenticatorStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremony;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremonyPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremonyStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnChallenge;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use UnexpectedValueException;

readonly class MySqlIdentityMultiFactorRepository implements
    AuthenticationTransactionRepository,
    StepUpGrantRepository,
    AccountMfaPolicyRepository,
    TotpAuthenticatorRepository,
    RecoveryCodeSetRepository,
    WebAuthnUserHandleRepository,
    MultiFactorNotificationTargetRepository,
    PasskeyCredentialRepository,
    WebAuthnCeremonyRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function createTransaction(
        ?int $accountInternalId,
        ?int $sessionInternalId,
        AuthenticationTransactionPurpose $purpose,
        ?StepUpAction $targetAction,
        ?AuthenticationMethod $primaryMethod,
        array $allowedMethods,
        int $maximumAttempts,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): AuthenticationTransactionCookieValue {
        $publicId = UuidV7::generate();
        $secret = AuthenticationTransactionSecret::generate();
        $allowed = json_encode(
            array_map(static fn (AuthenticationMethod $method): string => $method->value, $allowedMethods),
            JSON_THROW_ON_ERROR,
        );
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_authentication_transactions '
            . '(public_id, secret_hash, account_id, session_id, purpose, target_action, '
            . 'primary_authentication_method, allowed_methods, status, attempt_count, maximum_attempts, '
            . 'expires_at, version, created_at, updated_at) VALUES '
            . "(:public_id, :secret_hash, :account_id, :session_id, :purpose, :target_action, "
            . ":primary_method, :allowed_methods, 'PENDING', 0, :maximum_attempts, "
            . ':expires_at, 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':secret_hash', $secret->hash()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(
            ':account_id',
            $accountInternalId,
            $accountInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );
        $statement->bindValue(
            ':session_id',
            $sessionInternalId,
            $sessionInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );
        $statement->bindValue(':purpose', $purpose->value);
        $statement->bindValue(':target_action', $targetAction?->value);
        $statement->bindValue(':primary_method', $primaryMethod?->value);
        $statement->bindValue(':allowed_methods', $allowed);
        $statement->bindValue(':maximum_attempts', $maximumAttempts, PDO::PARAM_INT);
        $statement->bindValue(':expires_at', self::format($expiresAt));
        $statement->bindValue(':created_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->execute();

        return new AuthenticationTransactionCookieValue($publicId, $secret);
    }

    public function findTransaction(
        AuthenticationTransactionCookieValue $cookie,
        bool $forUpdate = false,
    ): ?AuthenticationTransaction {
        $statement = $this->pdo()->prepare(
            'SELECT id, public_id, secret_hash, account_id, session_id, purpose, target_action, '
            . 'primary_authentication_method, allowed_methods, status, attempt_count, maximum_attempts, '
            . 'expires_at, completed_at, revoked_at, version, created_at, updated_at '
            . 'FROM account_authentication_transactions WHERE public_id = :public_id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':public_id', $cookie->transactionId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $transaction = $this->transaction(self::row($row));

        return $transaction->secretHash->matches($cookie->secretForVerification()) ? $transaction : null;
    }

    public function recordTransactionFailure(AuthenticationTransaction $transaction, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_authentication_transactions SET attempt_count = attempt_count + 1, "
            . "status = IF(attempt_count + 1 >= maximum_attempts, 'REVOKED', status), "
            . 'revoked_at = IF(attempt_count + 1 >= maximum_attempts, :revoked_at, revoked_at), '
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND version = :version AND status = 'PENDING' AND attempt_count < maximum_attempts",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $transaction->internalId,
            ':version' => $transaction->version,
        ]);

        return $statement->rowCount() === 1;
    }

    public function completeTransaction(AuthenticationTransaction $transaction, DateTimeImmutable $now): bool
    {
        return $this->terminalUpdate(
            'account_authentication_transactions',
            'completed_at',
            'COMPLETED',
            $transaction->internalId,
            $transaction->version,
            $now,
        );
    }

    public function revokePendingStepUpForSession(int $sessionInternalId, DateTimeImmutable $now): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_authentication_transactions SET status = 'REVOKED', revoked_at = :revoked_at, "
            . "version = version + 1, updated_at = :updated_at WHERE session_id = :session_id "
            . "AND purpose = 'STEP_UP' AND status = 'PENDING'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':session_id' => $sessionInternalId,
        ]);
    }

    public function createGrant(
        int $accountInternalId,
        int $sessionInternalId,
        StepUpAction $action,
        AuthenticationAssuranceLevel $assurance,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): StepUpGrant {
        $this->revokeActiveGrant($sessionInternalId, $action, $now);
        $publicId = UuidV7::generate();
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_step_up_grants '
            . '(public_id, account_id, session_id, action, assurance_level, status, issued_at, expires_at, '
            . "version, created_at, updated_at) VALUES (:public_id, :account_id, :session_id, :action, "
            . ":assurance, 'ACTIVE', :issued_at, :expires_at, 1, :created_at, :updated_at)",
        );
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':session_id', $sessionInternalId, PDO::PARAM_INT);
        $statement->bindValue(':action', $action->value);
        $statement->bindValue(':assurance', $assurance->value);
        foreach (['issued_at', 'created_at', 'updated_at'] as $name) {
            $statement->bindValue(':' . $name, self::format($now));
        }
        $statement->bindValue(':expires_at', self::format($expiresAt));
        $statement->execute();

        return new StepUpGrant(
            (int)$this->pdo()->lastInsertId(),
            $publicId->toString(),
            $accountInternalId,
            $sessionInternalId,
            $action,
            $assurance,
            StepUpGrantStatus::ACTIVE,
            $now,
            $expiresAt,
            null,
            null,
            1,
        );
    }

    public function findActiveGrant(
        int $accountInternalId,
        int $sessionInternalId,
        StepUpAction $action,
        bool $forUpdate = false,
    ): ?StepUpGrant {
        $statement = $this->pdo()->prepare(
            'SELECT id, public_id, account_id, session_id, action, assurance_level, status, issued_at, '
            . 'expires_at, consumed_at, revoked_at, version FROM account_step_up_grants '
            . "WHERE account_id = :account_id AND session_id = :session_id AND action = :action "
            . "AND status = 'ACTIVE' LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([
            ':account_id' => $accountInternalId,
            ':session_id' => $sessionInternalId,
            ':action' => $action->value,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->grant(self::row($row)) : null;
    }

    public function consumeGrant(StepUpGrant $grant, DateTimeImmutable $now): bool
    {
        return $this->terminalUpdate(
            'account_step_up_grants',
            'consumed_at',
            'CONSUMED',
            $grant->internalId,
            $grant->version,
            $now,
            'ACTIVE',
        );
    }

    public function findPolicy(int $accountInternalId, bool $forUpdate = false): AccountMfaPolicy
    {
        if ($forUpdate) {
            $now = self::format(new DateTimeImmutable('now'));
            $insert = $this->pdo()->prepare(
                "INSERT IGNORE INTO account_mfa_policies "
                . "(account_id, status, preferred_method, version, created_at, updated_at) "
                . "VALUES (:account_id, 'DISABLED', NULL, 1, :created_at, :updated_at)",
            );
            $insert->execute([':account_id' => $accountInternalId, ':created_at' => $now, ':updated_at' => $now]);
        }
        $statement = $this->pdo()->prepare(
            'SELECT account_id, status, preferred_method, version, enabled_at, disabled_at '
            . 'FROM account_mfa_policies WHERE account_id = :account_id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return AccountMfaPolicy::disabled($accountInternalId);
        }

        return $this->policy(self::row($row));
    }

    public function activeMethods(int $accountInternalId): array
    {
        $methods = [];
        if ($this->count("account_totp_authenticators", $accountInternalId, 'ACTIVE') > 0) {
            $methods[] = AuthenticationMethod::TOTP;
        }
        if ($this->count("account_passkey_credentials", $accountInternalId, 'ACTIVE') > 0) {
            $methods[] = AuthenticationMethod::PASSKEY;
        }
        if ($this->count("account_recovery_code_sets", $accountInternalId, 'ACTIVE') > 0) {
            $methods[] = AuthenticationMethod::RECOVERY_CODE;
        }

        return $methods;
    }

    public function activeStrongFactorCount(int $accountInternalId): int
    {
        return $this->count('account_totp_authenticators', $accountInternalId, 'ACTIVE')
            + $this->count('account_passkey_credentials', $accountInternalId, 'ACTIVE');
    }

    public function enablePolicy(
        AccountMfaPolicy $policy,
        AccountMfaPreferredMethod $preferredMethod,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            "UPDATE account_mfa_policies SET status = 'ENABLED', preferred_method = :preferred_method, "
            . 'enabled_at = :enabled_at, disabled_at = NULL, version = version + 1, updated_at = :updated_at '
            . 'WHERE account_id = :account_id AND version = :version',
        );
        $statement->execute([
            ':preferred_method' => $preferredMethod->value,
            ':enabled_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':account_id' => $policy->accountInternalId,
            ':version' => $policy->version,
        ]);

        return $statement->rowCount() === 1;
    }

    public function disablePolicy(AccountMfaPolicy $policy, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_mfa_policies SET status = 'DISABLED', preferred_method = NULL, "
            . 'disabled_at = :disabled_at, version = version + 1, updated_at = :updated_at '
            . 'WHERE account_id = :account_id AND version = :version',
        );
        $statement->execute([
            ':disabled_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':account_id' => $policy->accountInternalId,
            ':version' => $policy->version,
        ]);

        return $statement->rowCount() === 1;
    }

    public function createPendingTotp(
        int $accountInternalId,
        string $publicId,
        EncryptedTotpSecret $secret,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): TotpAuthenticator {
        $this->revokePendingTotp($accountInternalId, $now);
        $uuid = UuidV7::fromString($publicId);
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_totp_authenticators '
            . '(public_id, account_id, secret_ciphertext, secret_nonce, encryption_key_version, '
            . 'algorithm, digits, period_seconds, status, enrollment_expires_at, version, created_at, updated_at) '
            . "VALUES (:public_id, :account_id, :ciphertext, :nonce, :key_version, 'SHA1', 6, 30, "
            . "'PENDING', :expires_at, 1, :created_at, :updated_at)",
        );
        $statement->bindValue(':public_id', $uuid->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':ciphertext', $secret->ciphertext(), PDO::PARAM_LOB);
        $statement->bindValue(':nonce', $secret->nonce(), PDO::PARAM_LOB);
        $statement->bindValue(':key_version', $secret->keyVersion, PDO::PARAM_INT);
        $statement->bindValue(':expires_at', self::format($expiresAt));
        $statement->bindValue(':created_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->execute();

        return new TotpAuthenticator(
            (int)$this->pdo()->lastInsertId(),
            $publicId,
            $accountInternalId,
            $secret,
            TotpAuthenticatorStatus::PENDING,
            null,
            $expiresAt,
            null,
            null,
            1,
            $now,
        );
    }

    public function findTotp(
        int $accountInternalId,
        string $publicId,
        bool $forUpdate = false,
    ): ?TotpAuthenticator {
        $statement = $this->pdo()->prepare(
            'SELECT id, public_id, account_id, secret_ciphertext, secret_nonce, encryption_key_version, '
            . 'status, last_accepted_counter, enrollment_expires_at, confirmed_at, revoked_at, version, created_at '
            . 'FROM account_totp_authenticators WHERE account_id = :account_id AND public_id = :public_id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->totp(self::row($row)) : null;
    }

    public function findActiveTotp(int $accountInternalId, bool $forUpdate = false): ?TotpAuthenticator
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, public_id, account_id, secret_ciphertext, secret_nonce, encryption_key_version, '
            . 'status, last_accepted_counter, enrollment_expires_at, confirmed_at, revoked_at, version, created_at '
            . "FROM account_totp_authenticators WHERE account_id = :account_id AND status = 'ACTIVE' LIMIT 1"
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->totp(self::row($row)) : null;
    }

    public function confirmTotp(TotpAuthenticator $authenticator, int $counter, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_totp_authenticators SET status = 'ACTIVE', last_accepted_counter = :counter, "
            . 'confirmed_at = :confirmed_at, version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND account_id = :account_id AND version = :version AND status = 'PENDING' "
            . 'AND enrollment_expires_at > :now',
        );
        $statement->execute([
            ':counter' => $counter,
            ':confirmed_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $authenticator->internalId,
            ':account_id' => $authenticator->accountInternalId,
            ':version' => $authenticator->version,
            ':now' => self::format($now),
        ]);

        return $statement->rowCount() === 1;
    }

    public function acceptTotpCounter(TotpAuthenticator $authenticator, int $counter, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo()->prepare(
            'UPDATE account_totp_authenticators SET last_accepted_counter = :accepted_counter, '
            . 'version = version + 1, updated_at = :updated_at WHERE id = :id AND account_id = :account_id '
            . "AND version = :version AND status = 'ACTIVE' "
            . 'AND (last_accepted_counter IS NULL OR last_accepted_counter < :minimum_counter)',
        );
        $statement->execute([
            ':accepted_counter' => $counter,
            ':minimum_counter' => $counter,
            ':updated_at' => self::format($now),
            ':id' => $authenticator->internalId,
            ':account_id' => $authenticator->accountInternalId,
            ':version' => $authenticator->version,
        ]);

        return $statement->rowCount() === 1;
    }

    public function revokeTotp(TotpAuthenticator $authenticator, DateTimeImmutable $now): bool
    {
        return $this->ownedTerminalUpdate(
            'account_totp_authenticators',
            'revoked_at',
            'REVOKED',
            $authenticator->internalId,
            $authenticator->accountInternalId,
            $authenticator->version,
            $now,
        );
    }

    public function replaceRecoveryCodeSet(
        int $accountInternalId,
        array $hashes,
        DateTimeImmutable $now,
    ): RecoveryCodeSet {
        if ($hashes === []) {
            throw new \InvalidArgumentException('Recovery-code set must not be empty.');
        }
        $this->revokeActiveRecoveryCodeSet($accountInternalId, $now);
        $publicId = UuidV7::generate();
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_recovery_code_sets '
            . "(public_id, account_id, status, version, generated_at, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, 'ACTIVE', 1, :generated_at, :created_at, :updated_at)",
        );
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        foreach (['generated_at', 'created_at', 'updated_at'] as $name) {
            $statement->bindValue(':' . $name, self::format($now));
        }
        $statement->execute();
        $setId = (int)$this->pdo()->lastInsertId();
        $insert = $this->pdo()->prepare(
            'INSERT INTO account_recovery_codes '
            . "(public_id, recovery_code_set_id, code_hash, position, status, version, created_at, updated_at) "
            . "VALUES (:public_id, :set_id, :code_hash, :position, 'ACTIVE', 1, :created_at, :updated_at)",
        );
        foreach ($hashes as $position => $hash) {
            $codeId = UuidV7::generate();
            $insert->bindValue(':public_id', $codeId->toBinary(), PDO::PARAM_LOB);
            $insert->bindValue(':set_id', $setId, PDO::PARAM_INT);
            $insert->bindValue(':code_hash', $hash->toBinary(), PDO::PARAM_LOB);
            $insert->bindValue(':position', $position + 1, PDO::PARAM_INT);
            $insert->bindValue(':created_at', self::format($now));
            $insert->bindValue(':updated_at', self::format($now));
            $insert->execute();
        }

        return new RecoveryCodeSet(
            $setId,
            $publicId->toString(),
            $accountInternalId,
            RecoveryCodeSetStatus::ACTIVE,
            count($hashes),
            1,
            $now,
        );
    }

    public function findActiveRecoveryCodeSet(
        int $accountInternalId,
        bool $forUpdate = false,
    ): ?RecoveryCodeSet {
        $statement = $this->pdo()->prepare(
            'SELECT s.id, s.public_id, s.account_id, s.status, s.version, s.generated_at, '
            . "SUM(CASE WHEN c.status = 'ACTIVE' THEN 1 ELSE 0 END) AS remaining_codes "
            . 'FROM account_recovery_code_sets s LEFT JOIN account_recovery_codes c '
            . "ON c.recovery_code_set_id = s.id WHERE s.account_id = :account_id AND s.status = 'ACTIVE' "
            . 'GROUP BY s.id, s.public_id, s.account_id, s.status, s.version, s.generated_at LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->recoverySet(self::row($row)) : null;
    }

    public function consumeRecoveryCode(
        RecoveryCodeSet $set,
        RecoveryCodeHash $hash,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            "UPDATE account_recovery_codes SET status = 'CONSUMED', consumed_at = :consumed_at, "
            . "version = version + 1, updated_at = :updated_at WHERE recovery_code_set_id = :set_id "
            . "AND code_hash = :code_hash AND status = 'ACTIVE'",
        );
        $statement->bindValue(':consumed_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':set_id', $set->internalId, PDO::PARAM_INT);
        $statement->bindValue(':code_hash', $hash->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            return false;
        }
        $remaining = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM account_recovery_codes WHERE recovery_code_set_id = :set_id AND status = 'ACTIVE'",
        );
        $remaining->execute([':set_id' => $set->internalId]);
        if ((int)$remaining->fetchColumn() === 0) {
            $exhaust = $this->pdo()->prepare(
                "UPDATE account_recovery_code_sets SET status = 'EXHAUSTED', exhausted_at = :exhausted_at, "
                . "version = version + 1, updated_at = :updated_at WHERE id = :id AND status = 'ACTIVE'",
            );
            $exhaust->execute([
                ':exhausted_at' => self::format($now),
                ':updated_at' => self::format($now),
                ':id' => $set->internalId,
            ]);
        }

        return true;
    }

    public function revokeActiveRecoveryCodeSet(int $accountInternalId, DateTimeImmutable $now): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_recovery_code_sets SET status = 'REVOKED', revoked_at = :revoked_at, "
            . "version = version + 1, updated_at = :updated_at WHERE account_id = :account_id AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':account_id' => $accountInternalId,
        ]);
    }

    public function findOrCreateUserHandle(int $accountInternalId, DateTimeImmutable $now): string
    {
        $statement = $this->pdo()->prepare(
            'SELECT user_handle FROM account_webauthn_user_handles WHERE account_id = :account_id LIMIT 1',
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $existing = $statement->fetchColumn();
        if (is_string($existing)) {
            return $existing;
        }
        $handle = random_bytes(32);
        $insert = $this->pdo()->prepare(
            'INSERT INTO account_webauthn_user_handles (account_id, user_handle, created_at, updated_at) '
            . 'VALUES (:account_id, :user_handle, :created_at, :updated_at)',
        );
        $insert->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $insert->bindValue(':user_handle', $handle, PDO::PARAM_LOB);
        $insert->bindValue(':created_at', self::format($now));
        $insert->bindValue(':updated_at', self::format($now));
        $insert->execute();

        return $handle;
    }

    public function notificationTarget(int $accountInternalId): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT e.id AS email_internal_id, a.preferred_locale AS locale, a.public_id '
            . 'FROM user_accounts a INNER JOIN account_email_addresses e ON e.user_account_id = a.id '
            . "AND e.status_code = 'VERIFIED' "
            . 'WHERE a.id = :account_id LIMIT 1',
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row = self::row($row);

        return [
            'email_internal_id' => self::integer($row, 'email_internal_id'),
            'locale' => self::string($row, 'locale'),
            'account_public_id' => UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
        ];
    }

    public function findAccountByUserHandle(string $userHandle): ?int
    {
        if (strlen($userHandle) !== 32) {
            return null;
        }
        $statement = $this->pdo()->prepare(
            'SELECT account_id FROM account_webauthn_user_handles WHERE user_handle = :user_handle LIMIT 1',
        );
        $statement->bindValue(':user_handle', $userHandle, PDO::PARAM_LOB);
        $statement->execute();
        $value = $statement->fetchColumn();

        return is_int($value) || is_string($value) && ctype_digit($value) ? (int)$value : null;
    }

    public function listPasskeys(int $accountInternalId, bool $includeInactive = false): array
    {
        $statement = $this->pdo()->prepare(
            $this->passkeySelect() . ' WHERE account_id = :account_id '
            . ($includeInactive ? '' : "AND status = 'ACTIVE' ")
            . 'ORDER BY created_at DESC, id DESC LIMIT 50',
        );
        $statement->execute([':account_id' => $accountInternalId]);
        $records = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $records[] = $this->passkey(self::row($row));
        }

        return $records;
    }

    public function findPasskeyByCredentialId(string $credentialId, bool $forUpdate = false): ?PasskeyCredential
    {
        $statement = $this->pdo()->prepare(
            $this->passkeySelect() . ' WHERE credential_id = :credential_id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':credential_id', $credentialId, PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->passkey(self::row($row)) : null;
    }

    public function findPasskey(
        int $accountInternalId,
        string $publicId,
        bool $forUpdate = false,
    ): ?PasskeyCredential {
        $statement = $this->pdo()->prepare(
            $this->passkeySelect() . ' WHERE account_id = :account_id AND public_id = :public_id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->passkey(self::row($row)) : null;
    }

    public function createPasskey(
        int $accountInternalId,
        string $publicId,
        string $credentialId,
        string $credentialPublicKey,
        int $signatureCounter,
        ?string $aaguid,
        array $transports,
        bool $backupEligible,
        bool $backupState,
        string $attestationFormat,
        string $displayName,
        DateTimeImmutable $now,
    ): PasskeyCredential {
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_passkey_credentials '
            . '(public_id, account_id, credential_id, credential_public_key, signature_counter, aaguid, '
            . 'transports, backup_eligible, backup_state, user_verification_required, attestation_format, '
            . "display_name, status, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, :credential_id, :public_key, :counter, :aaguid, :transports, "
            . ":backup_eligible, :backup_state, 1, :attestation_format, :display_name, 'ACTIVE', 1, "
            . ':created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':credential_id', $credentialId, PDO::PARAM_LOB);
        $statement->bindValue(':public_key', $credentialPublicKey, PDO::PARAM_LOB);
        $statement->bindValue(':counter', $signatureCounter, PDO::PARAM_INT);
        $statement->bindValue(':aaguid', $aaguid, $aaguid === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $statement->bindValue(':transports', json_encode($transports, JSON_THROW_ON_ERROR));
        $statement->bindValue(':backup_eligible', $backupEligible ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':backup_state', $backupState ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':attestation_format', $attestationFormat);
        $statement->bindValue(':display_name', $displayName);
        $statement->bindValue(':created_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->execute();

        return $this->findPasskey($accountInternalId, $publicId)
            ?? throw new UnexpectedValueException('Persisted passkey could not be reloaded.');
    }

    public function recordPasskeyUse(
        PasskeyCredential $credential,
        int $newCounter,
        bool $backupState,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->pdo()->prepare(
            'UPDATE account_passkey_credentials SET signature_counter = GREATEST(signature_counter, :counter), '
            . 'backup_state = :backup_state, last_used_at = :last_used_at, version = version + 1, '
            . "updated_at = :updated_at WHERE id = :id AND version = :version AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':counter' => $newCounter,
            ':backup_state' => $backupState ? 1 : 0,
            ':last_used_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $credential->internalId,
            ':version' => $credential->version,
        ]);

        return $statement->rowCount() === 1;
    }

    public function suspendPasskey(PasskeyCredential $credential, DateTimeImmutable $now): bool
    {
        return $this->ownedTerminalUpdate(
            'account_passkey_credentials',
            'suspended_at',
            'SUSPENDED',
            $credential->internalId,
            $credential->accountInternalId,
            $credential->version,
            $now,
        );
    }

    public function revokePasskey(PasskeyCredential $credential, DateTimeImmutable $now): bool
    {
        return $this->ownedTerminalUpdate(
            'account_passkey_credentials',
            'revoked_at',
            'REVOKED',
            $credential->internalId,
            $credential->accountInternalId,
            $credential->version,
            $now,
        );
    }

    public function createCeremony(
        ?int $accountInternalId,
        ?int $sessionInternalId,
        ?int $authenticationTransactionInternalId,
        WebAuthnCeremonyPurpose $purpose,
        WebAuthnChallenge $challenge,
        int $maximumAttempts,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): WebAuthnCeremony {
        $publicId = UuidV7::generate();
        $statement = $this->pdo()->prepare(
            'INSERT INTO account_webauthn_ceremonies '
            . '(public_id, account_id, session_id, authentication_transaction_id, purpose, challenge_hash, '
            . "status, attempt_count, maximum_attempts, expires_at, version, created_at, updated_at) VALUES "
            . "(:public_id, :account_id, :session_id, :transaction_id, :purpose, :challenge_hash, 'PENDING', "
            . '0, :maximum_attempts, :expires_at, 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(
            ':account_id',
            $accountInternalId,
            $accountInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );
        $statement->bindValue(
            ':session_id',
            $sessionInternalId,
            $sessionInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );
        $statement->bindValue(
            ':transaction_id',
            $authenticationTransactionInternalId,
            $authenticationTransactionInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
        );
        $statement->bindValue(':purpose', $purpose->value);
        $statement->bindValue(':challenge_hash', $challenge->hash(), PDO::PARAM_LOB);
        $statement->bindValue(':maximum_attempts', $maximumAttempts, PDO::PARAM_INT);
        $statement->bindValue(':expires_at', self::format($expiresAt));
        $statement->bindValue(':created_at', self::format($now));
        $statement->bindValue(':updated_at', self::format($now));
        $statement->execute();

        return new WebAuthnCeremony(
            (int)$this->pdo()->lastInsertId(),
            $publicId->toString(),
            $accountInternalId,
            $sessionInternalId,
            $authenticationTransactionInternalId,
            $purpose,
            $challenge->hash(),
            WebAuthnCeremonyStatus::PENDING,
            0,
            $maximumAttempts,
            $expiresAt,
            1,
        );
    }

    public function revokePendingCeremonies(
        ?int $sessionInternalId,
        ?int $authenticationTransactionInternalId,
        WebAuthnCeremonyPurpose $purpose,
        DateTimeImmutable $now,
    ): void {
        if ($sessionInternalId === null && $authenticationTransactionInternalId === null) {
            return;
        }
        $binding = $authenticationTransactionInternalId === null
            ? 'session_id = :binding_id'
            : 'authentication_transaction_id = :binding_id';
        $bindingId = $authenticationTransactionInternalId ?? $sessionInternalId;
        $statement = $this->pdo()->prepare(
            "UPDATE account_webauthn_ceremonies SET status = 'REVOKED', revoked_at = :revoked_at, "
            . 'version = version + 1, updated_at = :updated_at WHERE ' . $binding
            . " AND purpose = :purpose AND status = 'PENDING'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':binding_id' => $bindingId,
            ':purpose' => $purpose->value,
        ]);
    }

    public function findCeremony(string $publicId, bool $forUpdate = false): ?WebAuthnCeremony
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, public_id, account_id, session_id, authentication_transaction_id, purpose, '
            . 'challenge_hash, status, attempt_count, maximum_attempts, expires_at, version '
            . 'FROM account_webauthn_ceremonies WHERE public_id = :public_id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->ceremony(self::row($row)) : null;
    }

    public function recordCeremonyFailure(WebAuthnCeremony $ceremony, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_webauthn_ceremonies SET attempt_count = attempt_count + 1, "
            . "status = IF(attempt_count + 1 >= maximum_attempts, 'REVOKED', status), "
            . 'revoked_at = IF(attempt_count + 1 >= maximum_attempts, :revoked_at, revoked_at), '
            . 'version = version + 1, updated_at = :updated_at '
            . "WHERE id = :id AND version = :version AND status = 'PENDING' AND attempt_count < maximum_attempts",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $ceremony->internalId,
            ':version' => $ceremony->version,
        ]);

        return $statement->rowCount() === 1;
    }

    public function consumeCeremony(WebAuthnCeremony $ceremony, DateTimeImmutable $now): bool
    {
        return $this->terminalUpdate(
            'account_webauthn_ceremonies',
            'consumed_at',
            'CONSUMED',
            $ceremony->internalId,
            $ceremony->version,
            $now,
        );
    }

    private function revokeActiveGrant(int $sessionInternalId, StepUpAction $action, DateTimeImmutable $now): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_step_up_grants SET status = 'REVOKED', revoked_at = :revoked_at, "
            . "version = version + 1, updated_at = :updated_at WHERE session_id = :session_id "
            . "AND action = :action AND status = 'ACTIVE'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':session_id' => $sessionInternalId,
            ':action' => $action->value,
        ]);
    }

    private function revokePendingTotp(int $accountInternalId, DateTimeImmutable $now): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE account_totp_authenticators SET status = 'REVOKED', revoked_at = :revoked_at, "
            . "version = version + 1, updated_at = :updated_at WHERE account_id = :account_id "
            . "AND status = 'PENDING'",
        );
        $statement->execute([
            ':revoked_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':account_id' => $accountInternalId,
        ]);
    }

    private function count(string $table, int $accountInternalId, string $status): int
    {
        $approved = ['account_totp_authenticators', 'account_passkey_credentials', 'account_recovery_code_sets'];
        if (!in_array($table, $approved, true)) {
            throw new \LogicException('Unsupported factor table.');
        }
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE account_id = :account_id AND status = :status',
        );
        $statement->execute([':account_id' => $accountInternalId, ':status' => $status]);

        return (int)$statement->fetchColumn();
    }

    private function terminalUpdate(
        string $table,
        string $timestampColumn,
        string $status,
        int $internalId,
        int $version,
        DateTimeImmutable $now,
        string $fromStatus = 'PENDING',
    ): bool {
        $approved = [
            'account_authentication_transactions:completed_at:COMPLETED',
            'account_step_up_grants:consumed_at:CONSUMED',
            'account_webauthn_ceremonies:consumed_at:CONSUMED',
        ];
        if (!in_array($table . ':' . $timestampColumn . ':' . $status, $approved, true)) {
            throw new \LogicException('Unsupported terminal update.');
        }
        $sql = sprintf(
            'UPDATE %s SET status = :status, %s = :occurred_at, version = version + 1, '
            . 'updated_at = :updated_at WHERE id = :id AND version = :version AND status = :from_status',
            $table,
            $timestampColumn,
        );
        $statement = $this->pdo()->prepare($sql);
        $statement->execute([
            ':status' => $status,
            ':occurred_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $internalId,
            ':version' => $version,
            ':from_status' => $fromStatus,
        ]);

        return $statement->rowCount() === 1;
    }

    private function ownedTerminalUpdate(
        string $table,
        string $timestampColumn,
        string $status,
        int $internalId,
        int $accountInternalId,
        int $version,
        DateTimeImmutable $now,
    ): bool {
        $approved = [
            'account_totp_authenticators:revoked_at:REVOKED',
            'account_passkey_credentials:suspended_at:SUSPENDED',
            'account_passkey_credentials:revoked_at:REVOKED',
        ];
        if (!in_array($table . ':' . $timestampColumn . ':' . $status, $approved, true)) {
            throw new \LogicException('Unsupported owned terminal update.');
        }
        $sql = sprintf(
            'UPDATE %s SET status = :status, %s = :occurred_at, version = version + 1, '
            . "updated_at = :updated_at WHERE id = :id AND account_id = :account_id "
            . "AND version = :version AND status IN ('PENDING','ACTIVE')",
            $table,
            $timestampColumn,
        );
        $statement = $this->pdo()->prepare($sql);
        $statement->execute([
            ':status' => $status,
            ':occurred_at' => self::format($now),
            ':updated_at' => self::format($now),
            ':id' => $internalId,
            ':account_id' => $accountInternalId,
            ':version' => $version,
        ]);

        return $statement->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    private function transaction(array $row): AuthenticationTransaction
    {
        $decoded = json_decode(self::string($row, 'allowed_methods'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new UnexpectedValueException('Authentication transaction methods are invalid.');
        }
        $methods = [];
        foreach ($decoded as $value) {
            if (!is_string($value)) {
                throw new UnexpectedValueException('Authentication transaction methods are invalid.');
            }
            $methods[] = AuthenticationMethod::from($value);
        }
        $target = self::nullableString($row, 'target_action');
        $primary = self::nullableString($row, 'primary_authentication_method');

        return new AuthenticationTransaction(
            self::integer($row, 'id'),
            UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
            new AuthenticationTransactionSecretHash(self::string($row, 'secret_hash')),
            self::nullableInteger($row, 'account_id'),
            self::nullableInteger($row, 'session_id'),
            AuthenticationTransactionPurpose::from(self::string($row, 'purpose')),
            $target === null ? null : StepUpAction::from($target),
            $primary === null ? null : AuthenticationMethod::from($primary),
            $methods,
            AuthenticationTransactionStatus::from(self::string($row, 'status')),
            self::integer($row, 'attempt_count'),
            self::integer($row, 'maximum_attempts'),
            new DateTimeImmutable(self::string($row, 'expires_at')),
            self::date($row, 'completed_at'),
            self::date($row, 'revoked_at'),
            self::integer($row, 'version'),
            new DateTimeImmutable(self::string($row, 'created_at')),
            new DateTimeImmutable(self::string($row, 'updated_at')),
        );
    }

    /** @param array<string, mixed> $row */
    private function grant(array $row): StepUpGrant
    {
        return new StepUpGrant(
            self::integer($row, 'id'),
            UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
            self::integer($row, 'account_id'),
            self::integer($row, 'session_id'),
            StepUpAction::from(self::string($row, 'action')),
            AuthenticationAssuranceLevel::from(self::string($row, 'assurance_level')),
            StepUpGrantStatus::from(self::string($row, 'status')),
            new DateTimeImmutable(self::string($row, 'issued_at')),
            new DateTimeImmutable(self::string($row, 'expires_at')),
            self::date($row, 'consumed_at'),
            self::date($row, 'revoked_at'),
            self::integer($row, 'version'),
        );
    }

    /** @param array<string, mixed> $row */
    private function policy(array $row): AccountMfaPolicy
    {
        $preferred = self::nullableString($row, 'preferred_method');

        return new AccountMfaPolicy(
            self::integer($row, 'account_id'),
            AccountMfaPolicyStatus::from(self::string($row, 'status')),
            $preferred === null ? null : AccountMfaPreferredMethod::from($preferred),
            self::integer($row, 'version'),
            self::date($row, 'enabled_at'),
            self::date($row, 'disabled_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function totp(array $row): TotpAuthenticator
    {
        return new TotpAuthenticator(
            self::integer($row, 'id'),
            UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
            self::integer($row, 'account_id'),
            new EncryptedTotpSecret(
                self::string($row, 'secret_ciphertext'),
                self::string($row, 'secret_nonce'),
                self::integer($row, 'encryption_key_version'),
            ),
            TotpAuthenticatorStatus::from(self::string($row, 'status')),
            self::nullableInteger($row, 'last_accepted_counter'),
            self::date($row, 'enrollment_expires_at'),
            self::date($row, 'confirmed_at'),
            self::date($row, 'revoked_at'),
            self::integer($row, 'version'),
            new DateTimeImmutable(self::string($row, 'created_at')),
        );
    }

    /** @param array<string, mixed> $row */
    private function recoverySet(array $row): RecoveryCodeSet
    {
        return new RecoveryCodeSet(
            self::integer($row, 'id'),
            UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
            self::integer($row, 'account_id'),
            RecoveryCodeSetStatus::from(self::string($row, 'status')),
            self::integer($row, 'remaining_codes'),
            self::integer($row, 'version'),
            new DateTimeImmutable(self::string($row, 'generated_at')),
        );
    }

    private function passkeySelect(): string
    {
        return 'SELECT id, public_id, account_id, credential_id, credential_public_key, signature_counter, '
            . 'aaguid, transports, backup_eligible, backup_state, attestation_format, display_name, status, '
            . 'version, created_at, last_used_at FROM account_passkey_credentials';
    }

    /** @param array<string, mixed> $row */
    private function passkey(array $row): PasskeyCredential
    {
        $decoded = json_decode(self::string($row, 'transports'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new UnexpectedValueException('Passkey transports are invalid.');
        }
        $transports = [];
        foreach ($decoded as $transport) {
            if (!is_string($transport)) {
                throw new UnexpectedValueException('Passkey transports are invalid.');
            }
            $transports[] = $transport;
        }

        return new PasskeyCredential(
            self::integer($row, 'id'),
            UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
            self::integer($row, 'account_id'),
            self::string($row, 'credential_id'),
            self::string($row, 'credential_public_key'),
            self::integer($row, 'signature_counter'),
            self::nullableString($row, 'aaguid'),
            $transports,
            self::integer($row, 'backup_eligible') === 1,
            self::integer($row, 'backup_state') === 1,
            self::string($row, 'attestation_format'),
            self::string($row, 'display_name'),
            PasskeyCredentialStatus::from(self::string($row, 'status')),
            self::integer($row, 'version'),
            new DateTimeImmutable(self::string($row, 'created_at')),
            self::date($row, 'last_used_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function ceremony(array $row): WebAuthnCeremony
    {
        return new WebAuthnCeremony(
            self::integer($row, 'id'),
            UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
            self::nullableInteger($row, 'account_id'),
            self::nullableInteger($row, 'session_id'),
            self::nullableInteger($row, 'authentication_transaction_id'),
            WebAuthnCeremonyPurpose::from(self::string($row, 'purpose')),
            self::string($row, 'challenge_hash'),
            WebAuthnCeremonyStatus::from(self::string($row, 'status')),
            self::integer($row, 'attempt_count'),
            self::integer($row, 'maximum_attempts'),
            new DateTimeImmutable(self::string($row, 'expires_at')),
            self::integer($row, 'version'),
        );
    }

    private function pdo(): PDO
    {
        return $this->provider->connection();
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $row */
    private static function date(array $row, string $column): ?DateTimeImmutable
    {
        $value = self::nullableString($row, $column);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Identity multi-factor persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }
        throw new UnexpectedValueException('Identity multi-factor persistence row is invalid.');
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new UnexpectedValueException('Identity multi-factor persistence row is invalid.');
        }

        return (int)$value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableInteger(array $row, string $column): ?int
    {
        return ($row[$column] ?? null) === null ? null : self::integer($row, $column);
    }

    /**
     * @param array<mixed, mixed> $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        $normalized = [];
        foreach ($row as $column => $value) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Identity multi-factor persistence row is invalid.');
            }
            $normalized[$column] = $value;
        }

        return $normalized;
    }
}
