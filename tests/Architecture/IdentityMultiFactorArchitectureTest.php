<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class IdentityMultiFactorArchitectureTest extends TestCase
{
    public function testModuleUsesApprovedSecurityLibrariesAndExplicitDependencies(): void
    {
        $root = dirname(__DIR__, 2);
        $module = $this->read($root . '/src/Bootstrap/Module/IdentityMultiFactorModule.php');
        $webauthn = $this->read($root . '/src/Modules/IdentityMultiFactor/Application/WebAuthnService.php');
        $totp = $this->read($root . '/src/Modules/IdentityMultiFactor/Application/TotpVerifier.php');
        $encryption = $this->read(
            $root . '/src/Modules/IdentityMultiFactor/Infrastructure/Security/SodiumTotpSecretEncryptor.php',
        );

        self::assertStringContainsString("private const ID = 'identity.multifactor'", $module);
        foreach (['identity.access', 'identity.sessions', 'identity.security_notifications'] as $dependency) {
            self::assertStringContainsString("new ModuleId('{$dependency}')", $module);
        }
        self::assertStringNotContainsString("new ModuleId('tenancy", $module);
        self::assertStringContainsString('AuthenticatorAssertionResponseValidator', $webauthn);
        self::assertStringContainsString('AuthenticatorAttestationResponseValidator', $webauthn);
        self::assertStringContainsString('OTPHP\\TOTP', $totp);
        self::assertStringContainsString('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt', $encryption);
        self::assertStringContainsString('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt', $encryption);
    }

    public function testPersistenceStoresOnlyEncryptedOrHashedAuthenticatorMaterial(): void
    {
        $root = dirname(__DIR__, 2) . '/src/Modules/IdentityMultiFactor/Infrastructure/Migration/';
        $authentication = $this->read($root . 'CreateAuthenticationTransactionFoundationMigration.php');
        $totp = $this->read($root . 'CreateTotpRecoveryCodeFoundationMigration.php');
        $passkey = $this->read($root . 'CreatePasskeyFoundationMigration.php');

        self::assertStringContainsString('secret_hash BINARY(32)', $authentication);
        self::assertStringNotContainsString('transaction_secret ', $authentication);
        self::assertStringContainsString('secret_ciphertext BLOB', $totp);
        self::assertStringContainsString('secret_nonce BINARY(24)', $totp);
        self::assertStringContainsString('code_hash BINARY(32)', $totp);
        self::assertStringNotContainsString('plaintext', strtolower($totp));
        self::assertStringContainsString('challenge_hash BINARY(32)', $passkey);
        self::assertStringContainsString('credential_public_key BLOB', $passkey);
        self::assertStringNotContainsString('private_key', strtolower($passkey));
        self::assertStringNotContainsString('challenge BLOB', $passkey);
    }

    public function testControllerOwnsNeitherPersistenceNorCryptographicParsing(): void
    {
        $controller = $this->read(
            dirname(__DIR__, 2) . '/src/Modules/IdentityMultiFactor/Interface/Http/'
            . 'IdentityMultiFactorController.php',
        );

        foreach (['PDO', 'sodium_', 'OTPHP', 'CBOR', 'COSE', 'openssl_', 'AuthenticatorAssertionResponse'] as $term) {
            self::assertStringNotContainsString($term, $controller);
        }
    }

    public function testBrowserCodeDoesNotReadCookiesPersistCredentialsOrRetryAuthentication(): void
    {
        $root = dirname(__DIR__, 2) . '/public/assets/js/';
        $source = '';
        foreach (glob($root . '*passkey*.js') ?: [] as $path) {
            $source .= $this->read($path);
        }
        $source .= $this->read($root . 'webauthn-client.js');
        $source .= $this->read($root . 'mutation-fetch-client.js');

        self::assertStringNotContainsString('document.cookie', $source);
        self::assertStringNotContainsString('localStorage', $source);
        self::assertStringNotContainsString('sessionStorage', $source);
        self::assertStringNotContainsString('setInterval', $source);
        self::assertStringNotContainsString('retryable: true', strtolower($source));
        self::assertStringContainsString('target.origin !== new URL(base).origin', $source);
    }

    public function testAllRequiredMfaRoutesAreRegisteredWithoutAuthorizationScopeExpansion(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = $this->read($root . '/routes/web.php');
        foreach (
            [
                '/login/mfa',
                '/login/mfa/totp',
                '/login/mfa/recovery-code',
                '/login/mfa/passkey/options',
                '/login/mfa/passkey/verify',
                '/login/passkey/options',
                '/login/passkey/verify',
                '/account/step-up/{action}',
                '/account/step-up/password',
                '/account/step-up/totp',
                '/account/step-up/recovery-code',
                '/account/step-up/passkey/options',
                '/account/step-up/passkey/verify',
                '/account/security/authentication',
                '/account/security/mfa/totp/enroll',
                '/account/security/mfa/totp/{authenticatorId}/confirm',
                '/account/security/mfa/totp/{authenticatorId}/qr',
                '/account/security/mfa/totp/{authenticatorId}/revoke',
                '/account/security/passkeys/register',
                '/account/security/passkeys/registration/options',
                '/account/security/passkeys/registration/verify',
                '/account/security/passkeys/{passkeyId}/revoke',
                '/account/security/mfa/enable',
                '/account/security/mfa/disable',
                '/account/security/mfa/recovery-codes',
                '/account/security/mfa/recovery-codes/regenerate',
            ] as $path
        ) {
            self::assertStringContainsString("new RoutePattern('{$path}')", $routes);
        }

        $moduleSource = '';
        foreach (glob($root . '/src/Modules/IdentityMultiFactor/**/*.php') ?: [] as $path) {
            $moduleSource .= $this->read($path);
        }
        self::assertStringNotContainsString('TenantContext', $moduleSource);
        self::assertStringNotContainsString('Permission', $moduleSource);
        self::assertStringNotContainsString('BreakGlass', $moduleSource);
        self::assertStringNotContainsString('SupportAccess', $moduleSource);
    }

    public function testEveryMfaPhpFileUsesStrictTypes(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = array_merge(
            glob($root . '/src/Modules/IdentityMultiFactor/*.php') ?: [],
            glob($root . '/src/Modules/IdentityMultiFactor/*/*.php') ?: [],
            glob($root . '/src/Modules/IdentityMultiFactor/*/*/*.php') ?: [],
        );
        self::assertNotEmpty($paths);
        foreach ($paths as $path) {
            self::assertStringContainsString('declare(strict_types=1);', $this->read($path), $path);
        }
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
