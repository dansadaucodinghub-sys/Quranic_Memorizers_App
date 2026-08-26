<?php

declare(strict_types=1);

namespace Qmdb\Shared\Localization;

use RuntimeException;

final readonly class Translator
{
    public function __construct(private TranslationCatalog $catalog, private Locale $locale)
    {
    }

    /** @param array<string, scalar|null> $parameters */
    public function trans(TranslationKey|string $key, array $parameters = []): string
    {
        $translationKey = is_string($key) ? new TranslationKey($key) : $key;
        $value = $this->catalog->value($this->locale, $translationKey);
        foreach ($parameters as $name => $replacement) {
            if (preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $name) !== 1) {
                throw new RuntimeException('Translation parameter name is invalid.');
            }
            $value = str_replace('{' . $name . '}', (string) $replacement, $value);
        }
        if (preg_match('/\{[a-z][a-z0-9_]*\}/', $value) === 1) {
            throw new RuntimeException('A required translation parameter is missing.');
        }

        return $value;
    }

    public function locale(): Locale
    {
        return $this->locale;
    }
}
