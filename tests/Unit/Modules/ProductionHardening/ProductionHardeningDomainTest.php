<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\ProductionHardening;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\ProductionHardening\Application\WebhookSubscriptionSecretIssuer;
use Qmdb\Modules\ProductionHardening\Domain\ApiCredentialIssuer;
use Qmdb\Modules\ProductionHardening\Domain\BoundedRetryPolicy;
use Qmdb\Modules\ProductionHardening\Domain\IntegrationSecretBox;
use Qmdb\Modules\ProductionHardening\Domain\OperationalLogRedactor;
use Qmdb\Modules\ProductionHardening\Domain\RetentionDispositionPolicy;
use Qmdb\Modules\ProductionHardening\Domain\WebhookEndpointPolicy;
use Qmdb\Modules\ProductionHardening\Domain\WebhookSignature;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class ProductionHardeningDomainTest extends TestCase
{
    public function testApiCredentialsAreOneTimeRandomAndArgonVerified(): void
    {
        $issuer = new ApiCredentialIssuer();
        $credential = $issuer->issue();

        self::assertMatchesRegularExpression('/\Aqmdb_[a-f0-9]{24}\z/', $credential['key_id']);
        self::assertNotSame($credential['secret'], $credential['secret_hash']);
        self::assertTrue($issuer->verify($credential['secret'], $credential['secret_hash']));
        self::assertFalse($issuer->verify($credential['secret'] . 'x', $credential['secret_hash']));
    }

    public function testWebhookSignaturesAreBoundedAndRejectStaleReplay(): void
    {
        $signatures = new WebhookSignature();
        $secret = str_repeat('s', 32);
        $signature = $signatures->sign('key-1', $secret, 'delivery-1', 1_000, '{"ok":true}');

        self::assertTrue($signatures->verify($signature, $secret, 'delivery-1', 1_000, '{"ok":true}', 1_299));
        self::assertFalse($signatures->verify($signature, $secret, 'delivery-1', 1_000, '{"ok":true}', 1_301));
        self::assertFalse($signatures->verify($signature, $secret, 'delivery-2', 1_000, '{"ok":true}', 1_000));
    }

    public function testWebhookEndpointPolicyRejectsPrivateAndInsecureTargets(): void
    {
        $policy = new WebhookEndpointPolicy();
        foreach (['http://example.com/hook', 'https://localhost/hook', 'https://127.0.0.1/hook', 'https://user:pass@example.com/hook'] as $url) {
            try {
                $policy->assertAllowed($url);
                self::fail('Unsafe webhook endpoint was accepted: ' . $url);
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSecretBoxAuthenticatesCiphertextAndFailsClosedWithoutAKey(): void
    {
        $box = new IntegrationSecretBox(new EnvironmentVariables([
            'P12_INTEGRATION_ENCRYPTION_KEY' => base64_encode(str_repeat('k', 32)),
        ]));
        $ciphertext = $box->encrypt('integration-secret');

        self::assertSame('integration-secret', $box->decrypt($ciphertext));
        self::assertNotSame('integration-secret', $ciphertext);
        self::assertFalse((new IntegrationSecretBox(new EnvironmentVariables([])))->configured());

        $issued = (new WebhookSubscriptionSecretIssuer($box))->issue();
        self::assertMatchesRegularExpression('/\Awhk_[a-f0-9]{16}\z/', $issued['key_id']);
        self::assertSame($issued['secret'], $box->decrypt($issued['ciphertext']));
        self::assertNotSame($issued['secret'], $issued['ciphertext']);
    }

    public function testRetryRetentionAndLogRedactionAreBounded(): void
    {
        $retry = new BoundedRetryPolicy(3, 10, 60);
        self::assertTrue($retry->shouldRetry(1, 503));
        self::assertFalse($retry->shouldRetry(1, 400));
        self::assertFalse($retry->shouldRetry(3, 503));
        self::assertGreaterThanOrEqual(10, $retry->delaySeconds(1, 'stable'));
        self::assertLessThanOrEqual(60, $retry->delaySeconds(9, 'stable'));

        $retention = new RetentionDispositionPolicy();
        self::assertSame('ACTIVE_DATA_HOLD', $retention->assess(new DateTimeImmutable('-90 days'), 30, true, new DateTimeImmutable())['reason']);
        self::assertTrue($retention->assess(new DateTimeImmutable('-90 days'), 30, false, new DateTimeImmutable())['eligible']);

        $redacted = (new OperationalLogRedactor())->redact(['token' => 'secret', 'nested' => ['password' => 'secret'], 'count' => 2]);
        self::assertSame('[REDACTED]', $redacted['token']);
        self::assertIsArray($redacted['nested']);
        self::assertSame('[REDACTED]', $redacted['nested']['password']);
        self::assertSame(2, $redacted['count']);
    }
}
