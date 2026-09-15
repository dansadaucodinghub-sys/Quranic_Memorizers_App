<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Domain;

/** Explicit forward-only certificate state graph. */
final readonly class CertificateLifecycle
{
    /** @var array<string,list<string>> */
    private const array TRANSITIONS = [
        'PREPARED' => ['ISSUED', 'VOIDED', 'ARCHIVED'],
        'ISSUED' => ['REVOKED', 'SUPERSEDED', 'ARCHIVED'],
        'REVOKED' => ['ARCHIVED'],
        'SUPERSEDED' => ['ARCHIVED'],
        'VOIDED' => ['ARCHIVED'],
        'ARCHIVED' => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \DomainException("Certificate transition {$from} to {$to} is not permitted.");
        }
    }
}
