<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\IdentityAccountState;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateJustification;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateOperationType;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReasonCode;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReference;

final class AccountStateDomainTest extends TestCase
{
    public function testReasonCodesAreBoundToTheirPermittedOperation(): void
    {
        self::assertTrue(AccountStateReasonCode::SECURITY_INCIDENT->permits(AccountStateOperationType::SUSPEND));
        self::assertFalse(AccountStateReasonCode::SECURITY_INCIDENT->permits(AccountStateOperationType::REACTIVATE));
        self::assertFalse(AccountStateReasonCode::SECURITY_REMEDIATION_COMPLETE->permits(AccountStateOperationType::SUSPEND));
        self::assertTrue(AccountStateReasonCode::SECURITY_REMEDIATION_COMPLETE->permits(AccountStateOperationType::REACTIVATE));
    }

    public function testJustificationAcceptsBoundedPlainTextAndRejectsMarkupAndControls(): void
    {
        $justification = new AccountStateJustification("Incident reference reviewed\nSuspension approved", 128);
        self::assertSame("Incident reference reviewed\nSuspension approved", $justification->value);

        $this->expectException(\InvalidArgumentException::class);
        new AccountStateJustification('<script>alert(1)</script>', 128);
    }

    public function testReferenceIsOptionalButConstrainedToSafeCaseNotation(): void
    {
        self::assertNull((new AccountStateReference(null, 128))->value);
        self::assertSame('INC-2026/0819', (new AccountStateReference('INC-2026/0819', 128))->value);

        $this->expectException(\InvalidArgumentException::class);
        new AccountStateReference('INC#secret', 128);
    }
}
