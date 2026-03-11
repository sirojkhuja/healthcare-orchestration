<?php

namespace App\Modules\Scheduling\Application\Data;

use App\Modules\Notifications\Application\Data\NotificationData;

final readonly class AppointmentNotificationDispatchData
{
    /**
     * @param  list<NotificationData>  $notifications
     */
    public function __construct(
        public AppointmentData $appointment,
        public string $notificationType,
        public ?string $windowKey,
        public array $notifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'appointment' => $this->appointment->toArray(),
            'notification_type' => $this->notificationType,
            'window_key' => $this->windowKey,
            'notifications' => array_map(
                static fn (NotificationData $notification): array => $notification->toArray(),
                $this->notifications,
            ),
        ];
    }
}
