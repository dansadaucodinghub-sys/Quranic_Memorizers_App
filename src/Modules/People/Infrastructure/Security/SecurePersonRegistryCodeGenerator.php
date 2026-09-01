<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Infrastructure\Security;

use Qmdb\Modules\People\Domain\PersonRegistryCode;
use Qmdb\Modules\People\Domain\PersonRegistryCodeGenerator;

final readonly class SecurePersonRegistryCodeGenerator implements PersonRegistryCodeGenerator
{
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';

    public function generate(): PersonRegistryCode
    {
        $body = '';
        for ($index = 0; $index < 16; $index++) {
            $body .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return new PersonRegistryCode('QMP-' . $body);
    }
}
