<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Module\ApplicationHttpModule;
use Qmdb\Modules\IdentitySessions\Interface\Http\ApplicationReadinessController;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerifier;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditReadinessCheck;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditVerifier;
use ReflectionClass;

final class P2SecurityHardeningArchitectureTest extends TestCase
{
    #[Test]
    public function auditReadinessUsesAControlPortRatherThanAHistoryScanOnEveryProbe(): void
    {
        $verifier = new ReflectionClass(SecurityAuditVerifier::class);
        self::assertContains(SecurityAuditControlVerifier::class, $verifier->getInterfaceNames());
        $check = new ReflectionClass(SecurityAuditReadinessCheck::class);
        $parameter = $check->getConstructor()?->getParameters()[0] ?? null;

        self::assertNotNull($parameter);
        self::assertSame(SecurityAuditControlVerifier::class, (string) $parameter->getType());
        self::assertFalse($check->hasMethod('verify'));
    }

    #[Test]
    public function auditReadinessIsAnExplicitHttpCompositionAndModuleDependency(): void
    {
        $controller = new ReflectionClass(ApplicationReadinessController::class);
        $types = array_map(
            static fn ($parameter): string => (string) $parameter->getType(),
            $controller->getConstructor()?->getParameters() ?? [],
        );
        self::assertContains(SecurityAuditReadinessCheck::class, $types);

        $dependencies = array_map(
            static fn ($dependency): string => $dependency->value(),
            (new ApplicationHttpModule(dirname(__DIR__, 2)))->dependencies(),
        );
        self::assertContains('security.audit', $dependencies);
    }

    #[Test]
    public function auditControlVerificationProtectsEveryBoundedListingIndex(): void
    {
        $path = (new ReflectionClass(SecurityAuditVerifier::class))->getFileName();
        self::assertIsString($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        foreach (
            [
                'ix_security_audit_events_stream_occurred', 'ix_security_audit_events_code_occurred',
                'ix_security_audit_events_actor_occurred', 'ix_security_audit_events_subject_occurred',
                'ix_security_audit_events_workspace_occurred', 'ix_security_audit_events_severity_occurred',
            ] as $index
        ) {
            self::assertStringContainsString($index, $source);
        }
    }
}
