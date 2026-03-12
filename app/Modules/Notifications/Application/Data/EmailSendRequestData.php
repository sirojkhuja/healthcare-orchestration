<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class EmailSendRequestData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $tenantId,
        public string $recipientEmail,
        public ?string $recipientName,
        public string $subject,
        public string $body,
        public array $metadata,
        public string $fromAddress,
        public string $fromName,
        public ?string $replyToAddress = null,
        public ?string $replyToName = null,
        public ?string $notificationId = null,
    ) {}
}
