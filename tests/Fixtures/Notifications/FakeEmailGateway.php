<?php

namespace Tests\Fixtures\Notifications;

use App\Modules\Notifications\Application\Contracts\EmailGateway;
use App\Modules\Notifications\Application\Data\EmailSendRequestData;
use App\Modules\Notifications\Application\Data\EmailSendResultData;
use App\Modules\Notifications\Application\Exceptions\EmailGatewayException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class FakeEmailGateway implements EmailGateway
{
    /**
     * @var list<array{type: string, message_id?: string, error_code?: string, error_message?: string}>
     */
    private array $plannedResults = [];

    /**
     * @var list<EmailSendRequestData>
     */
    private array $requests = [];

    public function queueFailure(
        string $errorCode = 'email_rejected',
        string $errorMessage = 'The email provider rejected the message.',
    ): void {
        $this->plannedResults[] = [
            'type' => 'failure',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];
    }

    public function queueSuccess(?string $messageId = null): void
    {
        $this->plannedResults[] = [
            'type' => 'success',
            'message_id' => $messageId ?? 'email-'.Str::uuid()->toString(),
        ];
    }

    /**
     * @return list<EmailSendRequestData>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    #[\Override]
    public function providerKey(): string
    {
        return 'email';
    }

    #[\Override]
    public function send(EmailSendRequestData $request): EmailSendResultData
    {
        $this->requests[] = $request;
        $planned = array_shift($this->plannedResults);

        if (($planned['type'] ?? null) === 'failure') {
            throw new EmailGatewayException(
                $planned['error_code'] ?? 'email_rejected',
                $planned['error_message'] ?? 'The email provider rejected the message.',
            );
        }

        return new EmailSendResultData(
            providerKey: $this->providerKey(),
            recipientEmail: $request->recipientEmail,
            recipientName: $request->recipientName,
            subject: $request->subject,
            status: 'sent',
            occurredAt: CarbonImmutable::now(),
            messageId: $planned['message_id'] ?? 'email-'.Str::uuid()->toString(),
        );
    }
}
