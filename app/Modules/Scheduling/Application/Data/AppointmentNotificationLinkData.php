<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AppointmentNotificationLinkData
{
    public function __construct(
        public string $appointmentNotificationId,
        public string $tenantId,
        public string $appointmentId,
        public string $notificationId,
        public string $notificationType,
        public string $channel,
        public string $templateId,
        public string $templateCode,
        public string $recipientValue,
        public ?string $windowKey,
        public CarbonImmutable $requestedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
