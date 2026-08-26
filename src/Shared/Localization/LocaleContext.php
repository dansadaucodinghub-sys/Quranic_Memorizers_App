<?php

declare(strict_types=1);

namespace Qmdb\Shared\Localization;

final readonly class LocaleContext
{
    public function __construct(private Locale $locale)
    {
    }

    public function locale(): Locale
    {
        return $this->locale;
    }
}
