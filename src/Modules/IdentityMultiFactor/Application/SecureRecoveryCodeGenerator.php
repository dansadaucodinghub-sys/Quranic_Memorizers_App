<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\SensitiveRecoveryCode;

final readonly class SecureRecoveryCodeGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private IdentityMultiFactorConfiguration $configuration)
    {
    }

    /** @return list<SensitiveRecoveryCode> */
    public function generateSet(): array
    {
        $codes = [];
        while (count($codes) < $this->configuration->recoveryCodeCount) {
            $bytes = random_bytes(max(1, $this->configuration->recoveryCodeBytes));
            $encoded = $this->encode($bytes);
            $display = implode('-', str_split($encoded, 4));
            $codes[$display] = new SensitiveRecoveryCode($display);
        }

        return array_values($codes);
    }

    private function encode(string $bytes): string
    {
        $bits = '';
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            if (!is_int($byte)) {
                throw new \UnexpectedValueException('Recovery-code entropy could not be encoded.');
            }
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 5) as $chunk) {
            $index = bindec(str_pad($chunk, 5, '0'));
            if (!is_int($index)) {
                throw new \UnexpectedValueException('Recovery-code entropy could not be encoded.');
            }
            $character = substr(self::ALPHABET, $index, 1);
            if ($character === '') {
                throw new \UnexpectedValueException('Recovery-code entropy could not be encoded.');
            }
            $result .= $character;
        }

        return $result;
    }
}
