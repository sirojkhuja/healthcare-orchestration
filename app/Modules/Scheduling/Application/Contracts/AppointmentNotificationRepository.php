<?php

namespace App\Modules\Scheduling\Application\Contracts;

use App\Modules\Scheduling\Application\Data\AppointmentNotificationLinkData;

interface AppointmentNotificationRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): AppointmentNotificationLinkData;

    public function findReusableLink(
        string $tenantId,
        string $appointmentId,
        string $notificationType,
        string $channel,
        ?string $windowKey,
    ): ?AppointmentNotificationLinkData;
}
