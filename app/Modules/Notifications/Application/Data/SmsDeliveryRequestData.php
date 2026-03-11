<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class SmsDeliveryRequestData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $tenantId,
        public string $phoneNumber,
        public string $message,
        public string $messageType,
        public array $metadata = [],
        public ?string $notificationId = null,
    ) {}
}
