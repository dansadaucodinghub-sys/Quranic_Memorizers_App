<?php

declare(strict_types=1);

namespace Qmdb\Shared\Localization;

final readonly class SupportedLocales
{
    /** @var array<string, Locale> */
    private array $locales;

    public function __construct()
    {
        $this->locales = ['en' => new Locale('en'), 'ar' => new Locale('ar')];
    }

    public function supports(string $value): bool
    {
        return isset($this->locales[$value]);
    }

    public function get(string $value): ?Locale
    {
        return $this->locales[$value] ?? null;
    }

    public function default(): Locale
    {
        return $this->locales['en'];
    }

    /** @return list<Locale> */
    public function all(): array
    {
        return array_values($this->locales);
    }
}
