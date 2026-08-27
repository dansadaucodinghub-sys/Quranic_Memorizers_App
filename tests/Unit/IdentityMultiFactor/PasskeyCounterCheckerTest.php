<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\IdentityMultiFactor;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Security\PasskeyCounterChecker;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\Exception\CounterException;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\TrustPath\EmptyTrustPath;

final class PasskeyCounterCheckerTest extends TestCase
{
    public function testIncreasingSingleDeviceCounterIsAccepted(): void
    {
        (new PasskeyCounterChecker())->check($this->credential(5, false), 6);

        self::addToAssertionCount(1);
    }

    public function testNonIncreasingSingleDeviceCounterRaisesCloneSignal(): void
    {
        $this->expectException(CounterException::class);
        (new PasskeyCounterChecker())->check($this->credential(5, false), 5);
    }

    public function testZeroCounterIsAcceptedForCounterlessAuthenticator(): void
    {
        (new PasskeyCounterChecker())->check($this->credential(0, false), 0);

        self::addToAssertionCount(1);
    }

    public function testBackupEligiblePasskeyUsesMultiDeviceCounterSemantics(): void
    {
        (new PasskeyCounterChecker())->check($this->credential(8, true), 3);

        self::addToAssertionCount(1);
    }

    private function credential(int $counter, bool $backupEligible): CredentialRecord
    {
        return CredentialRecord::create(
            'credential-id',
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            [],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            'credential-public-key',
            'opaque-user-handle',
            $counter,
            backupEligible: $backupEligible,
            backupStatus: $backupEligible,
            uvInitialized: true,
        );
    }
}
