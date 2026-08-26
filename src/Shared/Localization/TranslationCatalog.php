<?php

declare(strict_types=1);

namespace Qmdb\Shared\Localization;

use RuntimeException;

final readonly class TranslationCatalog
{
    /** @var array<string, array<string, string>> */
    private array $catalogs;

    /** @param array<string, string> $files */
    public static function fromFiles(array $files): self
    {
        $catalogs = [];
        foreach ($files as $locale => $file) {
            if (!is_file($file)) {
                throw new RuntimeException('A registered translation catalog is missing.');
            }
            $values = require $file;
            if (!is_array($values)) {
                throw new RuntimeException('A translation catalog is invalid.');
            }
            $validated = [];
            foreach ($values as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new RuntimeException('Translation catalogs must contain string keys and values.');
                }
                $canonical = (new TranslationKey($key))->value();
                if (str_contains($value, '<') || str_contains($value, '>')) {
                    throw new RuntimeException('Translation catalogs must contain plain text only.');
                }
                $validated[$canonical] = $value;
            }
            ksort($validated);
            $catalogs[(new Locale($locale))->value()] = $validated;
        }
        $englishKeys = array_keys($catalogs['en'] ?? []);
        foreach ($catalogs as $values) {
            if (array_keys($values) !== $englishKeys) {
                throw new RuntimeException('Translation catalog keys do not match.');
            }
        }

        return new self($catalogs);
    }

    /** @param array<string, array<string, string>> $catalogs */
    private function __construct(array $catalogs)
    {
        $this->catalogs = $catalogs;
    }

    public function value(Locale $locale, TranslationKey $key): string
    {
        return $this->catalogs[$locale->value()][$key->value()]
            ?? $this->catalogs['en'][$key->value()]
            ?? throw new RuntimeException('Unknown translation key: ' . $key->value());
    }

    public function count(): int
    {
        return count($this->catalogs['en'] ?? []);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->catalogs['en'] ?? []);
    }
}
