<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\Tenancy;

use LogicException;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;

final class TenantContextTest extends TestCase
{
    public function testTrustedContextCarriesBothInternalAndOpaqueWorkspaceIdentity(): void
    {
        $workspaceId = WorkspaceId::generate();
        $context = TenantContext::trusted(41, $workspaceId);

        self::assertFalse($context->isSystem());
        self::assertSame(41, $context->workspaceInternalId());
        self::assertSame($workspaceId->toString(), $context->workspaceId()->toString());
    }

    public function testSystemContextCannotBeUsedAsTenantAuthority(): void
    {
        $context = TenantContext::platform();
        self::assertTrue($context->isSystem());

        $this->expectException(LogicException::class);
        $context->workspaceInternalId();
    }
}
