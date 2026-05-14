<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendReportEmailMessage;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class SendReportEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $users,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendReportEmailMessage $msg): void
    {
        $user = $this->users->find($msg->userId);
        if ($user === null) {
            return;
        }
        try {
            $email = (new Email())
                ->from('no-reply@adomc.local')
                ->to($user->getEmail())
                ->subject($msg->subject)
                ->text($msg->body);
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->warning('E-mail delivery failed: '.$e->getMessage());
        }
    }
}
