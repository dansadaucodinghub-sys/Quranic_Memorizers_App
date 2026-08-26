<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Infrastructure\Mail;

use Qmdb\Modules\IdentityAccess\Application\Mail\EmailDeliveryException;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailVerificationMessage;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailVerificationNotifier;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SymfonyMailerEmailVerificationNotifier implements EmailVerificationNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName,
    ) {
    }

    public function send(EmailVerificationMessage $message): void
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromAddress, $this->fromName))
                ->to($message->recipient)
                ->subject($message->subject)
                ->text($message->textBody)
                ->html($message->htmlBody);
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            throw new EmailDeliveryException('Email delivery failed safely.', 0, $exception);
        }
    }
}
