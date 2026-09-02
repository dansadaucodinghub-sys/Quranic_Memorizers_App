<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Security;

use Qmdb\Modules\OrganizationAffiliations\Domain\OrganizationAffiliationCodeGenerator;

final readonly class SecureOrganizationAffiliationCodeGenerator implements OrganizationAffiliationCodeGenerator
{
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        $body = '';
        for ($position = 0; $position < 16; ++$position) {
            $body .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'QMA-' . $body;
    }
}
