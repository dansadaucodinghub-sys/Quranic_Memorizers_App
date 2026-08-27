<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentityRecovery;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenHash;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Security\SecurePasswordRecoveryTokenGenerator;

final class PasswordRecoveryTokenTest extends TestCase
{
    public function testGeneratorProducesUniqueBase64UrlTokensWithSha256Proofs(): void
    {
        $generator = new SecurePasswordRecoveryTokenGenerator();
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $first->revealForProof());
        self::assertNotSame($first->revealForProof(), $second->revealForProof());
        $hash = PasswordRecoveryTokenHash::fromToken($first);
        self::assertSame(32, strlen($hash->toBinary()));
        self::assertTrue($hash->matches($first));
        self::assertFalse($hash->matches($second));
    }

    public function testTokenRejectsInvalidInputAndRedactsDebugOutput(): void
    {
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();

        self::assertSame(['value' => '[REDACTED]'], $token->__debugInfo());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password recovery token is invalid.');
        new PasswordRecoveryToken('not-a-valid-token');
    }

    public function testTokenCannotBeJsonSerialized(): void
    {
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();
        $this->expectException(LogicException::class);
        $token->jsonSerialize();
    }

    public function testTokenCannotBePhpSerialized(): void
    {
        $token = (new SecurePasswordRecoveryTokenGenerator())->generate();
        $this->expectException(LogicException::class);
        serialize($token);
    }
}
