<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Identity\Domain\Value\EmailAddress;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryRequestSubmissionId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordResetSubmissionId;

final readonly class RecoveryFormInputMapper
{
    private const MAX_FIELDS = 12;

    public function __construct(private int $maximumBodyBytes, private PasswordPolicy $passwordPolicy)
    {
    }

    public function request(ServerRequestInterface $request): RecoveryRequestFormInput
    {
        $data = $this->form($request, ['email', 'csrf_token', 'recovery_request_submission_id']);
        $emailInput = $this->scalar($data, 'email');
        try {
            $email = EmailAddress::fromInput($emailInput)->normalized();
        } catch (\InvalidArgumentException) {
            throw new RecoveryFormInputException(
                ['email' => 'form.error.email'],
                $this->safeEmail($emailInput),
            );
        }
        try {
            $submissionId = PasswordRecoveryRequestSubmissionId::fromString(
                $this->scalar($data, 'recovery_request_submission_id'),
            );
        } catch (\InvalidArgumentException) {
            throw new RecoveryFormInputException(
                ['recovery_request_submission_id' => 'form.error.submission'],
                $email,
            );
        }

        return new RecoveryRequestFormInput($email, $this->scalar($data, 'csrf_token'), $submissionId);
    }

    public function reset(ServerRequestInterface $request): PasswordResetFormInput
    {
        $data = $this->form(
            $request,
            ['token', 'new_password', 'new_password_confirmation', 'csrf_token', 'password_reset_submission_id'],
        );
        try {
            $token = new PasswordRecoveryToken($this->scalar($data, 'token'));
        } catch (\InvalidArgumentException) {
            throw new RecoveryFormInputException(['token' => 'form.error.recovery']);
        }
        $password = new SensitivePlaintextPassword($this->scalar($data, 'new_password', false));
        $confirmation = $this->scalar($data, 'new_password_confirmation', false);
        if (!$this->passwordPolicy->evaluate($password, $confirmation)->accepted()) {
            throw new RecoveryFormInputException(
                ['new_password' => 'form.error.password'],
                safeToken: $token->revealForProof(),
            );
        }
        try {
            $submissionId = PasswordResetSubmissionId::fromString(
                $this->scalar($data, 'password_reset_submission_id'),
            );
        } catch (\InvalidArgumentException) {
            throw new RecoveryFormInputException(
                ['password_reset_submission_id' => 'form.error.submission'],
                safeToken: $token->revealForProof(),
            );
        }

        return new PasswordResetFormInput(
            $token,
            $password,
            $confirmation,
            $this->scalar($data, 'csrf_token'),
            $submissionId,
        );
    }

    /**
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private function form(ServerRequestInterface $request, array $allowed): array
    {
        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($contentType !== 'application/x-www-form-urlencoded') {
            throw new RecoveryFormInputException(['form' => 'form.error.media_type'], status: 415);
        }
        $raw = (string)$request->getBody();
        if (strlen($raw) > $this->maximumBodyBytes) {
            throw new RecoveryFormInputException(['form' => 'form.error.size']);
        }
        $parsed = $request->getParsedBody();
        if ($parsed === null) {
            parse_str($raw, $parsed);
        }
        if (!is_array($parsed) || count($parsed) > self::MAX_FIELDS) {
            throw new RecoveryFormInputException(['form' => 'form.error.fields']);
        }
        $validated = [];
        foreach ($parsed as $field => $value) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new RecoveryFormInputException(['form' => 'form.error.fields']);
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
            throw new RecoveryFormInputException([$field => 'form.error.invalid']);
        }

        return $trim ? trim($value) : $value;
    }

    private function safeEmail(string $value): string
    {
        return mb_substr(str_replace(["\r", "\n", "\0"], '', $value), 0, 320, 'UTF-8');
    }
}
