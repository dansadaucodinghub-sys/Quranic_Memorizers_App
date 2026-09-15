<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Domain;

/** Closed template language: presentation tokens, never executable content. */
final class CertificateTemplateConfigurationValidator
{
    /** @var array<string, true> */
    private const ALLOWED = [
        'approved_logo_asset' => true, 'approved_seal_asset' => true, 'border_style_code' => true,
        'typography_token' => true, 'signatory_role_labels' => true, 'result_fields' => true,
        'competition_fields' => true, 'display_name_mode' => true, 'date_format_code' => true,
        'qr_position_code' => true, 'orientation' => true, 'page_size' => true, 'locale_layout' => true,
    ];

    /** @param array<string,mixed> $configuration */
    public function validate(array $configuration): void
    {
        foreach ($configuration as $key => $value) {
            if (!isset(self::ALLOWED[$key])) {
                throw new \InvalidArgumentException('Certificate template configuration contains an unsupported key.');
            }
            $this->value($value);
        }
    }

    private function value(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->value($item);
            }

            return;
        }
        if (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null) {
            throw new \InvalidArgumentException('Certificate template configuration contains an invalid value.');
        }
        if (is_string($value) && (str_contains($value, "\0") || preg_match('//u', $value) !== 1 || preg_match('/(?:<|>|javascript:|https?:\/\/|\\b(?:select|insert|update|delete|function)\\b)/i', $value) === 1)) {
            throw new \InvalidArgumentException('Certificate template configuration contains prohibited content.');
        }
    }
}
