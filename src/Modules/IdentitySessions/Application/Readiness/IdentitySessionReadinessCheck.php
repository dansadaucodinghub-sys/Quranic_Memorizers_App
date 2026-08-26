<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application\Readiness;

use Qmdb\Modules\IdentitySessions\Application\DeviceCookieFactory;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\DeviceTokenSecret;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenSecret;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Throwable;

final readonly class IdentitySessionReadinessCheck
{
    public function __construct(
        private IdentitySessionConfiguration $configuration,
        private SessionCookieFactory $sessionCookies,
        private DeviceCookieFactory $deviceCookies,
        private SchemaHealthCheck $schema,
    ) {
    }

    public function isReady(): bool
    {
        try {
            if (
                !function_exists('random_bytes')
                || !function_exists('hash')
                || !function_exists('hash_equals')
                || !$this->schema->check()->isReady()
                || $this->configuration->previousTokenGraceSeconds
                    >= $this->configuration->rotationIntervalSeconds
                || $this->configuration->idleTtlSeconds > $this->configuration->absoluteTtlSeconds
            ) {
                return false;
            }
            $expectedPrefix = $this->configuration->productionLike ? '__Host-' : '';
            if (
                !str_starts_with($this->sessionCookies->name(), $expectedPrefix)
                || !str_starts_with($this->deviceCookies->name(), $expectedPrefix)
            ) {
                return false;
            }
            if (
                strlen(SessionTokenSecret::generate()->hash()->toBinary()) !== 32
                || strlen(DeviceTokenSecret::generate()->hash()->toBinary()) !== 32
            ) {
                return false;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
