<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Html;

final readonly class SafeHtml
{
    private function __construct(private string $html)
    {
    }

    public static function fromTrustedTemplate(string $html): self
    {
        return new self($html);
    }

    public function trustedHtml(): string
    {
        return $this->html;
    }

    /** @return array{type: string, length: int} */
    public function __debugInfo(): array
    {
        return ['type' => 'trusted-rendered-html', 'length' => strlen($this->html)];
    }
}
