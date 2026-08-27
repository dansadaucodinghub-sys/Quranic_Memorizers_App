<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class IdentityRecoveryArchitectureTest extends TestCase
{
    public function testRecoveryAndNotificationModulesExposeExplicitBoundaries(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['IdentityRecovery', 'IdentitySecurityNotifications'] as $module) {
            self::assertDirectoryExists($root . '/src/Modules/' . $module . '/Application');
            self::assertDirectoryExists($root . '/src/Modules/' . $module . '/Domain');
            self::assertDirectoryExists($root . '/src/Modules/' . $module . '/Infrastructure');
        }

        $recoveryModule = $this->read($root . '/src/Bootstrap/Module/IdentityRecoveryModule.php');
        $notificationModule = $this->read(
            $root . '/src/Bootstrap/Module/IdentitySecurityNotificationsModule.php',
        );
        self::assertStringContainsString("private const ID = 'identity.recovery'", $recoveryModule);
        self::assertStringContainsString("private const ID = 'identity.security_notifications'", $notificationModule);
        self::assertStringNotContainsString("new ModuleId('identity.recovery')", $notificationModule);
    }

    public function testRecoveryTokenIsHashOnlyAtPersistenceAndCannotBeRenderedOrSerialized(): void
    {
        $root = dirname(__DIR__, 2);
        $token = $this->read($root . '/src/Modules/IdentityRecovery/Domain/PasswordRecoveryToken.php');
        $repository = $this->read(
            $root . '/src/Modules/IdentityRecovery/Infrastructure/Persistence/MySqlPasswordRecoveryRepository.php',
        );

        self::assertStringNotContainsString('function __toString', $token);
        self::assertStringContainsString('jsonSerialize(): never', $token);
        self::assertStringContainsString('function __serialize(): array', $token);
        self::assertStringContainsString(
            "hash('sha256'",
            $this->read($root . '/src/Modules/IdentityRecovery/Domain/PasswordRecoveryTokenHash.php'),
        );
        self::assertStringContainsString(':token_hash', $repository);
        self::assertStringNotContainsString(':token,', $repository);
    }

    public function testControllersDoNotOwnPersistenceCryptographyOrMailDelivery(): void
    {
        $root = dirname(__DIR__, 2) . '/src/Modules/IdentityRecovery/Interface/Http';
        foreach (glob($root . '/*Controller.php') ?: [] as $path) {
            $source = $this->read($path);
            self::assertStringNotContainsString('PDO', $source, $path);
            self::assertStringNotContainsString('password_hash(', $source, $path);
            self::assertStringNotContainsString('MailerInterface', $source, $path);
            self::assertStringNotContainsString('createSession(', $source, $path);
        }
    }

    public function testResetIsAuthoritativeOnlyOnPostAndNeverCreatesAnAuthenticatedSession(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = $this->read($root . '/routes/web.php');
        $service = $this->read($root . '/src/Modules/IdentityRecovery/Application/PasswordResetService.php');
        $formController = $this->read(
            $root . '/src/Modules/IdentityRecovery/Interface/Http/PasswordResetFormController.php',
        );

        self::assertMatchesRegularExpression(
            "/HttpMethod::POST[\\s\\S]+new RoutePattern\\('\\/reset-password\\/\\{challengeId}\\'\\)/",
            $routes,
        );
        self::assertStringNotContainsString('PasswordResetService', $formController);
        self::assertStringNotContainsString('createSession(', $service);
        self::assertStringContainsString('SessionRevocationReason::PASSWORD_RESET', $service);
    }

    public function testRecoveryViewsRemainFullPageProgressiveWorkflowsWithoutModalOrBrowserStorage(): void
    {
        $root = dirname(__DIR__, 2);
        $source = '';
        $viewPaths = array_merge(
            glob($root . '/resources/views/pages/password-*.php') ?: [],
            glob($root . '/resources/views/fragments/password-*.php') ?: [],
        );
        foreach ($viewPaths as $path) {
            $source .= $this->read($path);
        }

        self::assertStringNotContainsString('data-qmdb-modal', $source);
        self::assertStringNotContainsString('localStorage', $source);
        self::assertStringNotContainsString('sessionStorage', $source);
        self::assertStringContainsString('data-qmdb-progressive-form', $source);
    }

    public function testSecurityNotificationDeliveryIsSchedulerOnlyAndNotHttpReachable(): void
    {
        $root = dirname(__DIR__, 2);
        $module = $this->read($root . '/src/Bootstrap/Module/IdentitySecurityNotificationsModule.php');
        $routes = $this->read($root . '/routes/web.php');

        self::assertStringContainsString("new ScheduledTaskId('identity.security_notifications.deliver')", $module);
        self::assertStringNotContainsString('identity.security_notifications.deliver', $routes);
        self::assertStringNotContainsString('ScheduledSecurityNotificationTask', $routes);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
