<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Infrastructure\Mail;

use Qmdb\Modules\IdentityAccess\Application\Mail\EmailDeliveryException;
use Qmdb\Modules\IdentityRecovery\Application\Mail\PasswordRecoveryMessage;
use Qmdb\Modules\IdentityRecovery\Application\Mail\PasswordRecoveryNotifier;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SymfonyMailerPasswordRecoveryNotifier implements PasswordRecoveryNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName,
    ) {
    }

    public function send(PasswordRecoveryMessage $message): void
    {
        try {
            $this->mailer->send(
                (new Email())
                    ->from(new Address($this->fromAddress, $this->fromName))
                    ->to($message->recipient)
                    ->subject($message->subject)
                    ->text($message->textBody)
                    ->html($message->htmlBody),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new EmailDeliveryException('Password recovery email delivery failed safely.', 0, $exception);
        }
    }
}
