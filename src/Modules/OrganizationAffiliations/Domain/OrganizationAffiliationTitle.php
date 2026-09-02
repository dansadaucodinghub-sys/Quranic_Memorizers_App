<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Domain;

final readonly class OrganizationAffiliationTitle
{
    private function __construct(public ?string $value)
    {
    }

    public static function optional(?string $value, int $maximumBytes): self
    {
        if ($value === null || trim($value) === '') {
            return new self(null);
        }
        $value = trim($value);
        if (!mb_check_encoding($value, 'UTF-8') || strlen($value) > $maximumBytes || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1 || preg_match('/<\/?[a-z!][^>]*>/iu', $value) === 1) {
            throw new \InvalidArgumentException('Affiliation title is invalid.');
        }

        return new self($value);
    }
}
