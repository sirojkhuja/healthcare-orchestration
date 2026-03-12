<?php

namespace App\Modules\Notifications\Infrastructure\Integrations;

use App\Modules\Notifications\Application\Contracts\EmailGateway;
use App\Modules\Notifications\Application\Data\EmailSendRequestData;
use App\Modules\Notifications\Application\Data\EmailSendResultData;
use App\Modules\Notifications\Application\Exceptions\EmailGatewayException;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Illuminate\Support\Str;
use Throwable;

final class ConfiguredEmailGateway implements EmailGateway
{
    public function __construct(
        private readonly MailManager $mailManager,
    ) {}

    #[\Override]
    public function providerKey(): string
    {
        return config()->string('notifications.email.provider_key', 'email');
    }

    #[\Override]
    public function send(EmailSendRequestData $request): EmailSendResultData
    {
        $mailer = config()->string('notifications.email.mailer', config()->string('mail.default', 'log'));
        $messageId = 'email-'.Str::uuid()->toString();

        try {
            /** @var Mailer $mailerInstance */
            $mailerInstance = $this->mailManager->mailer($mailer);
            $mailerInstance->html($request->body, function (Message $message) use ($request): void {
                $message->to($request->recipientEmail, $request->recipientName);
                $message->from($request->fromAddress, $request->fromName);
                $message->subject($request->subject);

                if ($request->replyToAddress !== null) {
                    $message->replyTo($request->replyToAddress, $request->replyToName);
                }
            });
        } catch (Throwable $exception) {
            throw new EmailGatewayException('email_provider_error', $exception->getMessage());
        }

        return new EmailSendResultData(
            providerKey: $this->providerKey(),
            recipientEmail: $request->recipientEmail,
            recipientName: $request->recipientName,
            subject: $request->subject,
            status: 'sent',
            occurredAt: CarbonImmutable::now(),
            messageId: $messageId,
        );
    }
}
