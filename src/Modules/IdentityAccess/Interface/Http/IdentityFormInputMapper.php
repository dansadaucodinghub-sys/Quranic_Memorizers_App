<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Identity\Domain\Value\EmailAddress;
use Qmdb\Modules\IdentityAccess\Domain\RegistrationSubmissionId;
use Qmdb\Modules\IdentityAccess\Domain\VerificationResendSubmissionId;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;

final readonly class IdentityFormInputMapper
{
    private const MAX_FIELDS = 12;

    public function __construct(private int $maximumBodyBytes, private PasswordPolicy $passwordPolicy)
    {
    }

    public function registration(ServerRequestInterface $request): RegistrationFormInput
    {
        $data = $this->form($request);
        $emailInput = $this->scalar($data, 'email');
        $passwordInput = $this->scalar($data, 'password', false);
        $confirmation = $this->scalar($data, 'password_confirmation', false);
        $errors = [];
        try {
            $email = EmailAddress::fromInput($emailInput)->normalized();
        } catch (\InvalidArgumentException) {
            $email = $this->safeEmail($emailInput);
            $errors['email'] = 'form.error.email';
        }
        $password = new SensitivePlaintextPassword($passwordInput);
        if (!$this->passwordPolicy->evaluate($password, $confirmation)->accepted()) {
            $errors['password'] = 'form.error.password';
        }
        try {
            $submission = RegistrationSubmissionId::fromString($this->scalar(
                $data,
                'registration_submission_id',
            ));
        } catch (\InvalidArgumentException) {
            $submission = null;
            $errors['registration_submission_id'] = 'form.error.submission';
        }
        $csrf = $this->scalar($data, 'csrf_token');
        if ($errors !== [] || $submission === null) {
            throw new FormInputException($errors, $email);
        }

        return new RegistrationFormInput($email, $password, $confirmation, $csrf, $submission);
    }

    public function resend(ServerRequestInterface $request): ResendFormInput
    {
        $data = $this->form($request);
        $emailInput = $this->scalar($data, 'email');
        try {
            $email = EmailAddress::fromInput($emailInput)->normalized();
        } catch (\InvalidArgumentException) {
            throw new FormInputException(['email' => 'form.error.email'], $this->safeEmail($emailInput));
        }
        try {
            $submission = VerificationResendSubmissionId::fromString($this->scalar(
                $data,
                'resend_submission_id',
            ));
        } catch (\InvalidArgumentException) {
            throw new FormInputException(['resend_submission_id' => 'form.error.submission'], $email);
        }

        return new ResendFormInput($email, $this->scalar($data, 'csrf_token'), $submission);
    }

    public function verification(ServerRequestInterface $request): VerificationFormInput
    {
        $data = $this->form($request);
        try {
            $token = new \Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken(
                $this->scalar($data, 'token'),
            );
        } catch (\InvalidArgumentException) {
            throw new FormInputException(['token' => 'form.error.verification']);
        }

        return new VerificationFormInput($token, $this->scalar($data, 'csrf_token'));
    }

    /** @return array<string, mixed> */
    private function form(ServerRequestInterface $request): array
    {
        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($contentType !== 'application/x-www-form-urlencoded') {
            throw new FormInputException(['form' => 'form.error.content_type'], '', 415);
        }
        $raw = (string)$request->getBody();
        if (strlen($raw) > $this->maximumBodyBytes) {
            throw new FormInputException(['form' => 'form.error.size']);
        }
        $parsed = $request->getParsedBody();
        if ($parsed === null) {
            parse_str($raw, $parsed);
        }
        if (!is_array($parsed) || count($parsed) > self::MAX_FIELDS) {
            throw new FormInputException(['form' => 'form.error.fields']);
        }

        $validated = [];
        foreach ($parsed as $field => $value) {
            if (!is_string($field)) {
                throw new FormInputException(['form' => 'form.error.fields']);
            }
            $validated[$field] = $value;
        }

        return $validated;
    }

    /** @param array<string, mixed> $data */
    private function scalar(array $data, string $field, bool $trim = true): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
            throw new FormInputException([$field => 'form.error.invalid']);
        }

        return $trim ? trim($value) : $value;
    }

    private function safeEmail(string $value): string
    {
        return mb_substr(str_replace(["\r", "\n", "\0"], '', $value), 0, 320, 'UTF-8');
    }
}
