<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\SecurityAudit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerificationReport;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerifier;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditReadinessCheck;

#[\PHPUnit\Framework\Attributes\Group('AuditIntegrity')]
#[\PHPUnit\Framework\Attributes\Group('FaultInjection')]
final class SecurityAuditReadinessCheckTest extends TestCase
{
    #[Test]
    public function itFailsClosedForAnUnavailableAuditKeyOrDatabaseControl(): void
    {
        self::assertFalse($this->check(['AUDIT_INTEGRITY_KEY_UNAVAILABLE'])->isReady());
        self::assertFalse($this->check(['AUDIT_IMMUTABILITY_TRIGGER_MISSING'])->isReady());
        self::assertTrue($this->check([])->isReady());
    }

    #[Test]
    public function itFailsClosedWhenControlVerificationThrows(): void
    {
        $check = new SecurityAuditReadinessCheck(new class implements SecurityAuditControlVerifier {
            public function verifyControls(): SecurityAuditControlVerificationReport
            {
                throw new \RuntimeException('Database controls cannot be inspected.');
            }
        });

        self::assertFalse($check->isReady());
    }

    /** @param list<string> $errors */
    private function check(array $errors): SecurityAuditReadinessCheck
    {
        return new SecurityAuditReadinessCheck(new class ($errors) implements SecurityAuditControlVerifier {
            /** @param list<string> $errors */
            public function __construct(private array $errors)
            {
            }

            public function verifyControls(): SecurityAuditControlVerificationReport
            {
                return new SecurityAuditControlVerificationReport($this->errors);
            }
        });
    }
}
