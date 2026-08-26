<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class IdentityAccessConfigurationFactory
{
    public function create(
        EnvironmentVariables $variables,
        ApplicationConfiguration $application,
    ): IdentityAccessConfiguration {
        $productionLike = $application->isProductionLike();
        $mailFrom = $variables->optionalString('MAIL_FROM_ADDRESS') ?? 'no-reply@example.test';
        if (filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Configured mail sender is invalid.');
        }
        if ($productionLike && str_ends_with(strtolower($mailFrom), '.test')) {
            throw new InvalidArgumentException('Production mail sender is invalid.');
        }

        return new IdentityAccessConfiguration(
            new PublicApplicationBaseUrl(
                $variables->optionalString('APP_PUBLIC_BASE_URL') ?? 'http://127.0.0.1:8080',
                $productionLike,
            ),
            $productionLike,
            $this->positive($variables, 'AUTH_CSRF_TTL_SECONDS', 1800),
            $this->positive($variables, 'AUTH_FORM_MAX_BYTES', 16384),
            $this->positive($variables, 'AUTH_PASSWORD_MIN_LENGTH', 12),
            $this->positive($variables, 'AUTH_PASSWORD_MAX_BYTES', 1024),
            $this->positive($variables, 'AUTH_EMAIL_VERIFICATION_TTL_SECONDS', 1800),
            $this->positive($variables, 'AUTH_EMAIL_VERIFICATION_MAX_ATTEMPTS', 5),
            $this->positive($variables, 'AUTH_REGISTRATION_WINDOW_SECONDS', 900),
            $this->positive($variables, 'AUTH_REGISTRATION_MAX_ATTEMPTS', 5),
            $this->positive($variables, 'AUTH_VERIFICATION_RESEND_WINDOW_SECONDS', 900),
            $this->positive($variables, 'AUTH_VERIFICATION_RESEND_MAX_ATTEMPTS', 3),
            $this->positive($variables, 'AUTH_PASSWORD_WINDOW_SECONDS', 900),
            $this->positive($variables, 'AUTH_PASSWORD_MAX_ATTEMPTS', 10),
            $this->positive($variables, 'AUTH_RATE_LIMIT_BLOCK_SECONDS', 900),
            $variables->optionalString('AUTH_CONTACT_ENCRYPTION_KEY_ID') ?? 'local-v1',
            $mailFrom,
            $variables->optionalString('MAIL_FROM_NAME') ?? 'Qur’an Memorizer DB',
        );
    }

    private function positive(EnvironmentVariables $variables, string $name, int $default): int
    {
        $raw = $variables->optionalString($name);
        if ($raw === null) {
            return $default;
        }
        if (preg_match('/\A[1-9][0-9]*\z/', $raw) !== 1) {
            throw new InvalidArgumentException($name . ' must be a positive integer.');
        }

        return (int)$raw;
    }
}
