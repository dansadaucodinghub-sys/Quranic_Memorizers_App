<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use DateTimeImmutable;
use OpenSSLAsymmetricKey;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Application\MultiFactorNotificationService;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Application\WebAuthnService;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremonyPurpose;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence\MySqlIdentityMultiFactorRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Persistence\MySqlAccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\HashChainedSecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\CanonicalSecurityEventMetadataSerializer;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('mysql')]
#[Group('WebAuthnAbuse')]
final class P2WebAuthnCeremonyIntegrationTest extends MySqlIntegrationTestCase
{
    private const NOW = '2026-08-27T12:00:00Z';

    private MySqlConnectionProvider $provider;
    private PDO $connection;
    private MySqlIdentityMultiFactorRepository $repository;
    private WebAuthnService $service;
    private AuthenticatedAccountContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = $this->provider();
        $this->connection = $this->provider->connection();
        $this->rebuildSchema();
        $this->repository = new MySqlIdentityMultiFactorRepository($this->provider);
        $clock = new FixedIdentityClock(new DateTimeImmutable(self::NOW));
        $this->context = $this->seedContext(new DateTimeImmutable(self::NOW));
        $notificationRepository = new MySqlAccountSecurityNotificationRepository($this->provider);
        $notifications = new MultiFactorNotificationService(
            $this->repository,
            $notificationRepository,
            new SecurityNotificationDeduplicationKeyFactory(),
            new SecurityNotificationConfiguration(20, 5, 60, 60, 3600),
        );
        $this->service = new WebAuthnService(
            $this->repository,
            $this->repository,
            $this->repository,
            new StepUpGuard($this->repository, $clock),
            $notifications,
            $this->repository,
            $this->transactionManager($this->provider),
            $this->mfaConfiguration(),
            $this->auditAppender(),
            $clock,
        );
    }

    protected function tearDown(): void
    {
        $this->clearRows();
        parent::tearDown();
    }

    public function testRegistrationUsesRequiredPolicyPersistsPublicMaterialAndRejectsReplayAndDuplicate(): void
    {
        $this->grantRegistration();
        $options = $this->service->registrationOptions($this->context);
        $publicKey = $options['publicKey'];
        $rp = $publicKey['rp'] ?? null;
        $selection = $publicKey['authenticatorSelection'] ?? null;
        $user = $publicKey['user'] ?? null;
        if (!is_array($rp) || !is_array($selection) || !is_array($user)) {
            self::fail('WebAuthn registration options are malformed.');
        }

        self::assertSame('localhost', $rp['id'] ?? null);
        self::assertSame('required', $selection['residentKey'] ?? null);
        self::assertSame('required', $selection['userVerification'] ?? null);
        self::assertSame('none', $publicKey['attestation'] ?? null);
        self::assertSame([], $publicKey['excludeCredentials'] ?? null);
        self::assertNotSame($this->context->accountId->toString(), $user['id'] ?? null);
        $userName = $user['name'] ?? null;
        if (!is_string($userName)) {
            self::fail('WebAuthn user name is malformed.');
        }
        self::assertStringNotContainsString('@', $userName);

        $fixture = $this->registrationFixture($publicKey, 'http://localhost');
        $credential = $this->service->verifyRegistration(
            $this->context,
            $options['ceremonyId'],
            'Platform passkey',
            $fixture['json'],
        );
        self::assertSame($fixture['credentialId'], $credential->credentialIdForVerification());
        self::assertNotSame('', $credential->publicKeyForVerification());
        self::assertCount(1, $this->repository->listPasskeys($this->context->accountInternalId));

        $this->expectException(\DomainException::class);
        $this->service->verifyRegistration(
            $this->context,
            $options['ceremonyId'],
            'Replay',
            $fixture['json'],
        );
    }

    public function testRegistrationRejectsWrongOriginRpUserVerificationAndDuplicateCredential(): void
    {
        $this->grantRegistration();
        foreach (
            [
                'wrong challenge' => ['http://localhost', 'localhost', true, true],
                'wrong origin' => ['https://evil.example', 'localhost', true, false],
                'wrong RP' => ['http://localhost', 'wrong.example', true, false],
                'missing user verification' => ['http://localhost', 'localhost', false, false],
            ] as [$origin, $rpId, $verified, $wrongChallenge]
        ) {
            $options = $this->service->registrationOptions($this->context);
            $fixtureOptions = $options['publicKey'];
            if ($wrongChallenge) {
                $fixtureOptions['challenge'] = self::base64Url(random_bytes(32));
            }
            $fixture = $this->registrationFixture($fixtureOptions, $origin, $rpId, $verified);
            try {
                $this->service->verifyRegistration(
                    $this->context,
                    $options['ceremonyId'],
                    'Rejected passkey',
                    $fixture['json'],
                );
                self::fail('Invalid WebAuthn registration was accepted.');
            } catch (\Throwable) {
                self::assertCount(0, $this->repository->listPasskeys($this->context->accountInternalId));
            }
        }

        $options = $this->service->registrationOptions($this->context);
        $fixture = $this->registrationFixture($options['publicKey'], 'http://localhost');
        $this->service->verifyRegistration(
            $this->context,
            $options['ceremonyId'],
            'First passkey',
            $fixture['json'],
        );
        $this->grantRegistration();
        $duplicateOptions = $this->service->registrationOptions($this->context);
        $duplicate = $this->registrationFixture(
            $duplicateOptions['publicKey'],
            'http://localhost',
            credentialId: $fixture['credentialId'],
        );

        $this->expectException(\DomainException::class);
        $this->service->verifyRegistration(
            $this->context,
            $duplicateOptions['ceremonyId'],
            'Duplicate passkey',
            $duplicate['json'],
        );
    }

    public function testDiscoverableAssertionValidatesSignatureAndPersistsCounterOnce(): void
    {
        $this->grantRegistration();
        $registration = $this->service->registrationOptions($this->context);
        $fixture = $this->registrationFixture($registration['publicKey'], 'http://localhost');
        $this->service->verifyRegistration(
            $this->context,
            $registration['ceremonyId'],
            'Login passkey',
            $fixture['json'],
        );
        $options = $this->service->assertionOptions(WebAuthnCeremonyPurpose::PASSKEY_LOGIN, null, null, null);
        self::assertSame([], $options['publicKey']['allowCredentials'] ?? null);
        self::assertSame('required', $options['publicKey']['userVerification'] ?? null);
        $handle = $this->repository->findOrCreateUserHandle(
            $this->context->accountInternalId,
            new DateTimeImmutable(self::NOW),
        );
        $assertion = $this->assertionFixture(
            $options['publicKey'],
            $fixture['credentialId'],
            $fixture['privateKey'],
            $handle,
            'http://localhost',
            'localhost',
            true,
            1,
        );
        $passkey = $this->service->verifyAssertion(
            $options['ceremonyId'],
            WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
            $assertion,
        );

        self::assertSame($this->context->accountInternalId, $passkey->accountInternalId);
        self::assertSame(1, $passkey->signatureCounter);
        self::assertNotNull($passkey->lastUsedAt);

        $this->expectException(\DomainException::class);
        $this->service->verifyAssertion(
            $options['ceremonyId'],
            WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
            $assertion,
        );
    }

    public function testAssertionsRejectWrongOriginRpMissingVerificationUserHandleAndSignature(): void
    {
        $this->grantRegistration();
        $registration = $this->service->registrationOptions($this->context);
        $fixture = $this->registrationFixture($registration['publicKey'], 'http://localhost');
        $this->service->verifyRegistration(
            $this->context,
            $registration['ceremonyId'],
            'Boundary passkey',
            $fixture['json'],
        );
        $handle = $this->repository->findOrCreateUserHandle(
            $this->context->accountInternalId,
            new DateTimeImmutable(self::NOW),
        );
        foreach (
            [
                'origin' => ['https://evil.example', 'localhost', true, $handle, false, false],
                'RP ID' => ['http://localhost', 'wrong.example', true, $handle, false, false],
                'verification' => ['http://localhost', 'localhost', false, $handle, false, false],
                'user handle' => ['http://localhost', 'localhost', true, random_bytes(32), false, false],
                'signature' => ['http://localhost', 'localhost', true, $handle, true, false],
                'credential ID' => ['http://localhost', 'localhost', true, $handle, false, true],
            ] as [$origin, $rpId, $verified, $userHandle, $corruptSignature, $wrongCredential]
        ) {
            $options = $this->service->assertionOptions(WebAuthnCeremonyPurpose::PASSKEY_LOGIN, null, null, null);
            $assertion = $this->assertionFixture(
                $options['publicKey'],
                $wrongCredential ? random_bytes(32) : $fixture['credentialId'],
                $fixture['privateKey'],
                $userHandle,
                $origin,
                $rpId,
                $verified,
                1,
                $corruptSignature,
            );
            try {
                $this->service->verifyAssertion(
                    $options['ceremonyId'],
                    WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
                    $assertion,
                );
                self::fail('Invalid WebAuthn assertion was accepted.');
            } catch (\Throwable) {
                self::assertSame(0, $this->repository->findPasskeyByCredentialId(
                    $fixture['credentialId'],
                )?->signatureCounter);
            }
        }
    }

    public function testRevokedAndSuspendedCredentialsCannotAuthenticate(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $this->grantRegistration();
        $firstOptions = $this->service->registrationOptions($this->context);
        $firstFixture = $this->registrationFixture($firstOptions['publicKey'], 'http://localhost');
        $first = $this->service->verifyRegistration(
            $this->context,
            $firstOptions['ceremonyId'],
            'Revoked passkey',
            $firstFixture['json'],
        );
        $this->grantRegistration();
        $secondOptions = $this->service->registrationOptions($this->context);
        $secondFixture = $this->registrationFixture($secondOptions['publicKey'], 'http://localhost');
        $second = $this->service->verifyRegistration(
            $this->context,
            $secondOptions['ceremonyId'],
            'Suspended passkey',
            $secondFixture['json'],
        );
        $handle = $this->repository->findOrCreateUserHandle($this->context->accountInternalId, $now);

        self::assertTrue($this->repository->revokePasskey($first, $now));
        $revokedOptions = $this->service->assertionOptions(
            WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
            null,
            null,
            null,
        );
        $revokedAssertion = $this->assertionFixture(
            $revokedOptions['publicKey'],
            $firstFixture['credentialId'],
            $firstFixture['privateKey'],
            $handle,
            'http://localhost',
            'localhost',
            true,
            1,
        );
        try {
            $this->service->verifyAssertion(
                $revokedOptions['ceremonyId'],
                WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
                $revokedAssertion,
            );
            self::fail('Revoked passkey was accepted.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }

        self::assertTrue($this->repository->suspendPasskey($second, $now));
        $suspendedOptions = $this->service->assertionOptions(
            WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
            null,
            null,
            null,
        );
        $suspendedAssertion = $this->assertionFixture(
            $suspendedOptions['publicKey'],
            $secondFixture['credentialId'],
            $secondFixture['privateKey'],
            $handle,
            'http://localhost',
            'localhost',
            true,
            1,
        );
        try {
            $this->service->verifyAssertion(
                $suspendedOptions['ceremonyId'],
                WebAuthnCeremonyPurpose::PASSKEY_LOGIN,
                $suspendedAssertion,
            );
            self::fail('Suspended passkey was accepted.');
        } catch (\DomainException) {
            self::addToAssertionCount(1);
        }
    }

    private function grantRegistration(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $this->repository->createGrant(
            $this->context->accountInternalId,
            $this->context->sessionInternalId,
            StepUpAction::MFA_REGISTER_PASSKEY,
            AuthenticationAssuranceLevel::PRIMARY,
            $now,
            $now->modify('+5 minutes'),
        );
    }

    /** @param array<string, mixed> $publicKey
     *  @return array{json: string, credentialId: string, privateKey: OpenSSLAsymmetricKey}
     */
    private function registrationFixture(
        array $publicKey,
        string $origin,
        string $rpId = 'localhost',
        bool $verified = true,
        ?string $credentialId = null,
    ): array {
        $privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);
        $details = openssl_pkey_get_details($privateKey);
        $ec = is_array($details) ? ($details['ec'] ?? null) : null;
        $x = is_array($ec) ? ($ec['x'] ?? null) : null;
        $y = is_array($ec) ? ($ec['y'] ?? null) : null;
        if (!is_string($x) || !is_string($y)) {
            self::fail('Generated WebAuthn test key has no EC coordinates.');
        }
        if (strlen($x) > 32 || strlen($y) > 32) {
            self::fail('Generated WebAuthn test key has invalid P-256 coordinate width.');
        }
        $x = str_pad($x, 32, "\0", STR_PAD_LEFT);
        $y = str_pad($y, 32, "\0", STR_PAD_LEFT);
        $credentialId ??= random_bytes(32);
        $coseKey = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));
        $flags = 0x01 | 0x40 | ($verified ? 0x04 : 0x00);
        $authenticatorData = hash('sha256', $rpId, true)
            . chr($flags)
            . pack('N', 0)
            . str_repeat("\0", 16)
            . pack('n', strlen($credentialId))
            . $credentialId
            . (string)$coseKey;
        $attestationObject = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authenticatorData));
        $clientData = self::encodeJson([
            'type' => 'webauthn.create',
            'challenge' => self::requiredString($publicKey, 'challenge'),
            'origin' => $origin,
            'crossOrigin' => false,
        ]);
        $id = self::base64Url($credentialId);
        $json = self::encodeJson([
            'id' => $id,
            'rawId' => $id,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::base64Url($clientData),
                'attestationObject' => self::base64Url((string)$attestationObject),
                'transports' => ['internal'],
            ],
        ]);

        return ['json' => $json, 'credentialId' => $credentialId, 'privateKey' => $privateKey];
    }

    /** @param array<string, mixed> $publicKey */
    private function assertionFixture(
        array $publicKey,
        string $credentialId,
        OpenSSLAsymmetricKey $privateKey,
        string $userHandle,
        string $origin,
        string $rpId,
        bool $verified,
        int $counter,
        bool $corruptSignature = false,
    ): string {
        $clientData = self::encodeJson([
            'type' => 'webauthn.get',
            'challenge' => self::requiredString($publicKey, 'challenge'),
            'origin' => $origin,
            'crossOrigin' => false,
        ]);
        $authenticatorData = hash('sha256', $rpId, true)
            . chr(0x01 | ($verified ? 0x04 : 0x00))
            . pack('N', $counter);
        $signature = '';
        self::assertTrue(openssl_sign(
            $authenticatorData . hash('sha256', $clientData, true),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256,
        ));
        if (!is_string($signature) || $signature === '') {
            self::fail('WebAuthn assertion fixture signature generation failed.');
        }
        if ($corruptSignature) {
            $signature[0] = chr(ord($signature[0]) ^ 0x01);
        }
        $id = self::base64Url($credentialId);

        return self::encodeJson([
            'id' => $id,
            'rawId' => $id,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::base64Url($clientData),
                'authenticatorData' => self::base64Url($authenticatorData),
                'signature' => self::base64Url($signature),
                'userHandle' => self::base64Url($userHandle),
            ],
        ]);
    }

    private function seedContext(DateTimeImmutable $now): AuthenticatedAccountContext
    {
        $formatted = $now->format('Y-m-d H:i:s.u');
        $account = AccountId::generate();
        $statement = $this->connection->prepare(
            "INSERT INTO user_accounts (public_id, account_status, preferred_locale, preferred_time_zone, "
            . "version, created_at, updated_at) VALUES (:public_id, 'ACTIVE', 'en', 'UTC', 1, "
            . ':created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $account->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':created_at', $formatted);
        $statement->bindValue(':updated_at', $formatted);
        $statement->execute();
        $accountId = (int)$this->connection->lastInsertId();
        $email = $this->connection->prepare(
            'INSERT INTO account_email_addresses (public_id, user_account_id, email_ciphertext, '
            . 'encryption_key_id, lookup_hash, status_code, verified_at, version, created_at, updated_at) '
            . "VALUES (:public_id, :account_id, :ciphertext, 'test-key', :lookup_hash, 'VERIFIED', "
            . ':verified_at, 1, :created_at, :updated_at)',
        );
        $email->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $email->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $email->bindValue(':ciphertext', random_bytes(32), PDO::PARAM_LOB);
        $email->bindValue(':lookup_hash', random_bytes(32), PDO::PARAM_LOB);
        foreach (['verified_at', 'created_at', 'updated_at'] as $parameter) {
            $email->bindValue(':' . $parameter, $formatted);
        }
        $email->execute();
        $deviceId = DeviceId::generate();
        $device = $this->connection->prepare(
            "INSERT INTO user_devices (public_id, account_id, token_hash, status, version, created_at, "
            . "last_seen_at, updated_at) VALUES (:public_id, :account_id, :token_hash, 'ACTIVE', 1, "
            . ':created_at, :last_seen_at, :updated_at)',
        );
        $device->bindValue(':public_id', $deviceId->toBinary(), PDO::PARAM_LOB);
        $device->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $device->bindValue(':token_hash', random_bytes(32), PDO::PARAM_LOB);
        foreach (['created_at', 'last_seen_at', 'updated_at'] as $parameter) {
            $device->bindValue(':' . $parameter, $formatted);
        }
        $device->execute();
        $deviceInternalId = (int)$this->connection->lastInsertId();
        $sessionId = SessionId::generate();
        $session = $this->connection->prepare(
            "INSERT INTO user_sessions (public_id, account_id, device_id, login_submission_id, "
            . "current_token_hash, status, version, issued_at, authenticated_at, primary_authentication_method, "
            . "secondary_authentication_method, assurance_level, strong_authenticated_at, last_seen_at, "
            . "idle_expires_at, absolute_expires_at, rotated_at, updated_at) VALUES "
            . "(:public_id, :account_id, :device_id, :login_submission_id, :current_token_hash, 'ACTIVE', 1, "
            . ":issued_at, :authenticated_at, 'PASSWORD', NULL, 'PRIMARY', NULL, :last_seen_at, "
            . ':idle_expires_at, :absolute_expires_at, :rotated_at, :updated_at)',
        );
        $session->bindValue(':public_id', $sessionId->toBinary(), PDO::PARAM_LOB);
        $session->bindValue(':login_submission_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $session->bindValue(':current_token_hash', random_bytes(32), PDO::PARAM_LOB);
        $session->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $session->bindValue(':device_id', $deviceInternalId, PDO::PARAM_INT);
        foreach (['issued_at', 'authenticated_at', 'last_seen_at', 'rotated_at', 'updated_at'] as $parameter) {
            $session->bindValue(':' . $parameter, $formatted);
        }
        $session->bindValue(':idle_expires_at', $now->modify('+30 minutes')->format('Y-m-d H:i:s.u'));
        $session->bindValue(':absolute_expires_at', $now->modify('+8 hours')->format('Y-m-d H:i:s.u'));
        $session->execute();

        return new AuthenticatedAccountContext(
            $accountId,
            $account,
            (int)$this->connection->lastInsertId(),
            $sessionId,
            $deviceInternalId,
            $deviceId,
            $now,
            1,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSWORD,
                null,
                AuthenticationAssuranceLevel::PRIMARY,
                $now,
                null,
            ),
        );
    }

    private function mfaConfiguration(): IdentityMultiFactorConfiguration
    {
        return new IdentityMultiFactorConfiguration(
            false,
            300,
            5,
            300,
            5,
            1,
            'QMDB',
            30,
            6,
            1,
            600,
            10,
            16,
            'localhost',
            'QMDB test',
            ['http://localhost'],
            300,
            65536,
            'required',
            'none',
            true,
            300,
            5,
            300,
            5,
        );
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @param array<string, mixed> $values */
    private static function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException('Required WebAuthn fixture value is missing.');
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function encodeJson(array $values): string
    {
        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    private function clearRows(): void
    {
        foreach (
            [
                'privileged_access_reviews', 'privileged_access_events', 'privileged_access_activations',
                'privileged_access_approvals', 'privileged_access_request_permissions',
                'privileged_access_requests', 'privileged_access_permission_policies',
                'workspace_role_assignments', 'platform_role_assignments', 'authorization_role_permissions',
                'authorization_roles', 'authorization_permissions',
                'account_webauthn_ceremonies', 'account_passkey_credentials', 'account_webauthn_user_handles',
                'account_recovery_codes', 'account_recovery_code_sets', 'account_totp_authenticators',
                'account_step_up_grants', 'account_authentication_transactions', 'account_mfa_policies',
                'account_security_notification_events', 'account_security_notifications', 'user_sessions',
                'user_devices', 'account_email_verification_challenges', 'identity_rate_limit_buckets',
                'identity_idempotency_records', 'workspace_memberships', 'account_status_events',
                'account_credentials', 'account_phone_numbers', 'account_email_addresses', 'user_accounts',
                'workspaces',
            ] as $table
        ) {
            $this->connection->exec('DELETE FROM ' . $table);
        }
    }

    private function auditAppender(): SecurityAuditEventAppender
    {
        $configuration = new SecurityAuditConfiguration(false, 4096, 1000, 3600, 10000, 1);
        $keys = new class implements SecurityAuditIntegrityKeyProvider {
            public function keyForVersion(int $version): string
            {
                if ($version !== 1) {
                    throw new \RuntimeException('Unexpected test integrity-key version.');
                }

                return hash('sha256', 'QMDB-NON-PRODUCTION-SECURITY-AUDIT-KEY-V1', true);
            }
        };

        return new SecurityAuditEventAppender(new HashChainedSecurityAuditRecorder(
            $this->provider,
            new CanonicalSecurityEventMetadataSerializer(4096),
            $keys,
            $configuration,
            new SecurityAuditHashChain(),
        ));
    }

    private function rebuildSchema(): void
    {
        foreach (
            [
                'organization_affiliation_status_events', 'organization_affiliation_role_assignments',
                'organization_affiliation_unit_assignments', 'organization_affiliations',
                'organization_affiliation_role_definitions', 'organization_unit_locations',
                'organization_unit_names', 'organization_units', 'organization_jurisdictions',
                'organization_classification_assignments', 'organization_names', 'organizations',
                'organization_classifications',
                'people_profile_operation_results', 'people_guardianships', 'people_memorizer_progress',
                'people_role_profiles', 'people_person_geographies', 'people_account_links', 'people_person_names',
                'people_persons',
                'geography_administrative_areas', 'geography_dataset_versions', 'geography_countries',
                'account_state_operations', 'security_audit_checkpoint_heads', 'security_audit_checkpoints',
                'security_audit_events', 'security_audit_streams',
                'privileged_access_reviews', 'privileged_access_events', 'privileged_access_activations',
                'privileged_access_approvals', 'privileged_access_request_permissions',
                'privileged_access_requests', 'privileged_access_permission_policies',
                'workspace_role_assignments', 'platform_role_assignments', 'authorization_role_permissions',
                'authorization_roles', 'authorization_permissions', 'account_webauthn_ceremonies',
                'account_passkey_credentials', 'account_webauthn_user_handles',
                'account_recovery_codes', 'account_recovery_code_sets', 'account_totp_authenticators',
                'account_step_up_grants', 'account_authentication_transactions', 'account_mfa_policies',
                'account_security_notification_events', 'account_security_notifications',
                'account_password_recovery_events', 'account_password_recovery_challenges', 'user_sessions',
                'user_devices', 'account_email_verification_challenges', 'identity_rate_limit_buckets',
                'identity_idempotency_records', 'workspace_memberships', 'account_status_events',
                'account_credentials', 'account_phone_numbers', 'account_email_addresses', 'user_accounts',
                'workspaces', 'qmdb_scheduled_task_runs',
            ] as $table
        ) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $factory = require dirname(__DIR__, 3) . '/database/migrations.php';
        self::assertIsCallable($factory);
        $registry = $factory();
        self::assertInstanceOf(MigrationRegistry::class, $registry);
        foreach ($registry->ordered() as $migration) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
    }
}
