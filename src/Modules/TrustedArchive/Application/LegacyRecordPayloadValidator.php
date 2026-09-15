<?php

declare(strict_types=1);

namespace Qmdb\Modules\TrustedArchive\Application;

/** Strict allow-list parser for staged legacy JSON rows; raw input remains encrypted. */
final class LegacyRecordPayloadValidator
{
    /** @return array{record_type:string,subject_reference:string,occurred_on:?string,issuer_label:?string} */
    public function validateJson(string $payload): array
    {
        if ($payload === '' || strlen($payload) > 65_536 || !preg_match('//u', $payload)) {
            throw new \InvalidArgumentException('Legacy payload is invalid.');
        }
        $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \InvalidArgumentException('Legacy payload must be an object.');
        }
        $allowed = ['record_type' => true, 'subject_reference' => true, 'occurred_on' => true, 'issuer_label' => true];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key]) || (!is_string($value) && $value !== null)) {
                throw new \InvalidArgumentException('Legacy payload contains an unsupported field.');
            }
        }
        $type = $decoded['record_type'] ?? null;
        $subject = $decoded['subject_reference'] ?? null;
        if (!is_string($type) || !in_array($type, ['CERTIFICATE', 'RESULT', 'RECOGNITION', 'OTHER_APPROVED'], true) || !is_string($subject) || trim($subject) === '') {
            throw new \InvalidArgumentException('Legacy payload required fields are invalid.');
        }
        $occurred = $decoded['occurred_on'] ?? null;
        if ($occurred !== null && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $occurred) !== 1) {
            throw new \InvalidArgumentException('Legacy payload date is invalid.');
        }
        $issuer = $decoded['issuer_label'] ?? null;
        if ($issuer !== null && (str_contains($issuer, "\0") || preg_match('//u', $issuer) !== 1)) {
            throw new \InvalidArgumentException('Legacy payload issuer is invalid.');
        }

        return ['record_type' => $type, 'subject_reference' => trim($subject), 'occurred_on' => $occurred, 'issuer_label' => $issuer];
    }
}
