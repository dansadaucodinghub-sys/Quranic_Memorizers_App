<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentityRecovery;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallenge;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeStatus;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenHash;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Security\SecurePasswordRecoveryTokenGenerator;

final class PasswordRecoveryChallengeTest extends TestCase
{
    public function testOnlyPendingUnexpiredUnexhaustedChallengeAcceptsTheCorrectToken(): void
    {
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();
        $now = new DateTimeImmutable('2026-08-26T12:00:00Z');
        $challenge = $this->challenge($token, PasswordRecoveryChallengeStatus::PENDING, 0, $now->modify('+30 minutes'));

        self::assertTrue($challenge->accepts($token, $now));
        self::assertFalse($challenge->accepts((new SecurePasswordRecoveryTokenGenerator())->generate(), $now));
        self::assertFalse($challenge->accepts($token, $now->modify('+30 minutes')));
        self::assertSame(32, strlen($challenge->tokenHash->toBinary()));
    }

    #[DataProvider('terminalStatuses')]
    public function testTerminalStatusesNeverAccept(PasswordRecoveryChallengeStatus $status): void
    {
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();
        self::assertFalse($this->challenge(
            $token,
            $status,
            0,
            new DateTimeImmutable('+30 minutes'),
        )->accepts($token, new DateTimeImmutable()));
    }

    /** @return iterable<string, array{PasswordRecoveryChallengeStatus}> */
    public static function terminalStatuses(): iterable
    {
        yield 'consumed' => [PasswordRecoveryChallengeStatus::CONSUMED];
        yield 'expired' => [PasswordRecoveryChallengeStatus::EXPIRED];
        yield 'revoked' => [PasswordRecoveryChallengeStatus::REVOKED];
    }

    public function testInvalidLocaleAndCountersAreRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->challenge(
            (new SecurePasswordRecoveryTokenGenerator())->generate(),
            PasswordRecoveryChallengeStatus::PENDING,
            0,
            new DateTimeImmutable('+30 minutes'),
            'fr',
        );
    }

    private function challenge(
        PasswordRecoveryToken $token,
        PasswordRecoveryChallengeStatus $status,
        int $attempts,
        DateTimeImmutable $expiresAt,
        string $locale = 'en',
    ): PasswordRecoveryChallenge {
        return new PasswordRecoveryChallenge(
            1,
            PasswordRecoveryChallengeId::generate(),
            2,
            AccountId::generate(),
            3,
            AccountStatus::ACTIVE,
            AccountContactStatus::VERIFIED,
            PasswordRecoveryTokenHash::fromToken($token),
            $locale,
            $status,
            $attempts,
            5,
            $expiresAt,
            1,
        );
    }
}
