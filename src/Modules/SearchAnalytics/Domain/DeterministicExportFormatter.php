<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Domain;

final class DeterministicExportFormatter
{
    /**
     * @param list<array<string,scalar|null>> $rows
     * @param list<string> $columns
     */
    public function csv(array $rows, array $columns): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to allocate export stream.');
        }
        try {
            fputcsv($stream, $columns, ',', '"', '');
            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->safeCell($row[$column] ?? null);
                }
                fputcsv($stream, $values, ',', '"', '');
            }
            rewind($stream);
            $contents = stream_get_contents($stream);
            if (!is_string($contents)) {
                throw new \RuntimeException('Unable to read export stream.');
            }
            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<array<string,scalar|null>> $rows
     * @param list<string> $columns
     */
    public function json(array $rows, array $columns): string
    {
        $ordered = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($columns as $column) {
                $item[$column] = $row[$column] ?? null;
            }
            $ordered[] = $item;
        }
        $encoded = json_encode($ordered, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded . "\n";
    }

    private function safeCell(bool|float|int|string|null $value): string|int|float
    {
        if ($value === null) {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        if (preg_match('/\A[=+\-@\t\r]/', $text) === 1) {
            return "'" . $text;
        }
        return $text;
    }
}
