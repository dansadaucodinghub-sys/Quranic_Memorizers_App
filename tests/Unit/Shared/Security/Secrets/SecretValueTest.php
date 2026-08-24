<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Security\Secrets;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Security\Secrets\SecretValue;
use ReflectionClass;

final class SecretValueTest extends TestCase
{
    private const TEST_SECRET = 'QMDB_TEST_SECRET_8f30f8e1_DO_NOT_EXPOSE';

    public function testNonEmptyValueIsAcceptedAndExplicitRevealWorks(): void
    {
        self::assertSame(self::TEST_SECRET, (new SecretValue(self::TEST_SECRET))->reveal());
    }

    public function testEmptyAndWhitespaceOnlyValuesAreRejected(): void
    {
        foreach (['', '   '] as $invalid) {
            try {
                new SecretValue($invalid);
                self::fail('Empty secret must fail.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString($invalid . 'sensitive', $exception->getMessage());
            }
        }
    }

    public function testImplicitStringCastingIsUnavailable(): void
    {
        self::assertFalse((new ReflectionClass(SecretValue::class))->hasMethod('__toString'));
    }

    public function testLengthPolicyCanBeCheckedWithoutRevealingTheValue(): void
    {
        $secret = new SecretValue(self::TEST_SECRET);

        self::assertTrue($secret->hasLengthAtLeast(16));
        self::assertFalse($secret->hasLengthAtLeast(128));
    }

    public function testLengthPolicyRejectsInvalidMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SecretValue(self::TEST_SECRET))->hasLengthAtLeast(0);
    }

    public function testDebugRepresentationIsRedacted(): void
    {
        ob_start();
        var_dump(new SecretValue(self::TEST_SECRET));
        $output = (string) ob_get_clean();

        self::assertStringContainsString('[REDACTED]', $output);
        self::assertStringNotContainsString(self::TEST_SECRET, $output);
    }

    public function testJsonEncodingIsRedacted(): void
    {
        $json = json_encode(new SecretValue(self::TEST_SECRET), JSON_THROW_ON_ERROR);

        self::assertSame('"[REDACTED]"', $json);
        self::assertStringNotContainsString(self::TEST_SECRET, $json);
    }

    public function testSerializationIsProhibited(): void
    {
        $this->expectException(LogicException::class);

        serialize(new SecretValue(self::TEST_SECRET));
    }

    public function testConstructorExceptionDoesNotExposeTheSubmittedValue(): void
    {
        try {
            new SecretValue('   ');
            self::fail('Whitespace secret must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString(self::TEST_SECRET, $exception->getMessage());
            self::assertStringNotContainsString(__DIR__, $exception->getMessage());
        }
    }

    public function testValueObjectIsFinalAndImmutable(): void
    {
        $reflection = new ReflectionClass(SecretValue::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }
}
