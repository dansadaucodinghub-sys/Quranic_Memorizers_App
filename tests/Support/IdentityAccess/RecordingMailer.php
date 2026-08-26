<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\IdentityAccess;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class RecordingMailer implements MailerInterface
{
    public ?Email $message = null;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (!$message instanceof Email) {
            throw new \InvalidArgumentException('Expected a MIME email.');
        }
        $this->message = $message;
    }
}
