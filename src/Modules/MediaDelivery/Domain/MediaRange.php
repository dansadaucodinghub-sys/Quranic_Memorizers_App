<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaDelivery\Domain;

/** Bounded single-range parser; multipart ranges are intentionally unsupported. */
final readonly class MediaRange
{
    private function __construct(public int $start, public int $end) {}
    public static function fromHeader(?string $header, int $length): ?self
    {
        if ($header === null || $header === '') return null;
        if ($length < 1 || preg_match('/\Abytes=(\d*)-(\d*)\z/', $header, $parts) !== 1) throw new \InvalidArgumentException('Range is invalid.');
        $start = $parts[1] === '' ? null : (int) $parts[1]; $end = $parts[2] === '' ? null : (int) $parts[2];
        if ($start === null && $end === null) throw new \InvalidArgumentException('Range is invalid.');
        if ($start === null) { if ($end < 1) throw new \InvalidArgumentException('Range is invalid.'); $start=max(0,$length-$end); $end=$length-1; }
        else { $end ??= $length-1; if ($start >= $length || $end < $start) throw new \InvalidArgumentException('Range is unsatisfiable.'); $end=min($end,$length-1); }
        return new self($start,$end);
    }
    public function length(): int { return $this->end-$this->start+1; }
}
