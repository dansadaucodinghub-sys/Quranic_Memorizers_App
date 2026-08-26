<?php

declare(strict_types=1);

namespace Qmdb\Shared\Identifier;

use InvalidArgumentException;

final readonly class UuidV7
{
    private function __construct(private string $binary)
    {
        if (strlen($binary) !== 16) {
            throw new InvalidArgumentException('UUIDv7 binary value must be exactly 16 bytes.');
        }
        if ((ord($binary[6]) >> 4) !== 7 || (ord($binary[8]) & 0xc0) !== 0x80) {
            throw new InvalidArgumentException('Public identifier must be an RFC 9562 UUIDv7.');
        }
    }

    public static function generate(): self
    {
        $milliseconds = (int) floor(microtime(true) * 1000);
        $bytes = random_bytes(16);
        for ($index = 5; $index >= 0; $index--) {
            $bytes[$index] = chr($milliseconds & 0xff);
            $milliseconds >>= 8;
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return new self($bytes);
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) !== 1) {
            throw new InvalidArgumentException('Public identifier must be a canonical UUIDv7.');
        }
        $binary = hex2bin(str_replace('-', '', strtolower($value)));
        if (!is_string($binary)) {
            throw new InvalidArgumentException('Public identifier could not be decoded.');
        }

        return new self($binary);
    }

    public static function fromBinary(string $binary): self
    {
        return new self($binary);
    }

    public function toBinary(): string
    {
        return $this->binary;
    }

    public function toString(): string
    {
        $hex = bin2hex($this->binary);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
